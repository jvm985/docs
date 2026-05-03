<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('import:overleaf {path : Path to extracted overleaf-export directory} {--dry-run : Show plan without writing}')]
#[Description('Import users, projects and files from an Overleaf MongoDB export directory.')]
class ImportOverleaf extends Command
{
    private array $userMap = [];

    private string $base;

    private bool $dry;

    public function handle(): int
    {
        $this->base = rtrim((string) $this->argument('path'), '/');
        $this->dry = (bool) $this->option('dry-run');

        if (! is_dir($this->base) || ! is_file($this->base.'/users.json') || ! is_file($this->base.'/projects.json')) {
            $this->error("Expected users.json and projects.json under {$this->base}");

            return self::FAILURE;
        }

        $users = json_decode((string) file_get_contents($this->base.'/users.json'), true) ?: [];
        $projects = json_decode((string) file_get_contents($this->base.'/projects.json'), true) ?: [];

        $this->info('Importing '.count($users).' users and '.count($projects).' projects'.($this->dry ? ' [DRY RUN]' : ''));

        $this->mapUsers($users);

        $imported = 0;
        $skipped = 0;
        foreach ($projects as $proj) {
            $name = $proj['name'] ?? '(unnamed)';
            $ownerOid = $this->oid($proj['owner_ref'] ?? null);
            if (! $ownerOid || ! isset($this->userMap[$ownerOid])) {
                $this->warn("Skip '$name' — owner not in users.json");
                $skipped++;

                continue;
            }
            $this->importProject($proj);
            $imported++;
        }

        $this->info("Done. Imported $imported, skipped $skipped.");

        return self::SUCCESS;
    }

    private function mapUsers(array $users): void
    {
        foreach ($users as $u) {
            $oid = $this->oid($u['_id'] ?? null);
            $email = $u['email'] ?? null;
            if (! $oid || ! $email) {
                continue;
            }
            $name = trim(($u['first_name'] ?? '').' '.($u['last_name'] ?? ''));
            if ($name === '') {
                $name = explode('@', $email)[0];
            }
            $existing = User::where('email', $email)->first();
            if ($existing) {
                $this->userMap[$oid] = $existing->id;

                continue;
            }
            if ($this->dry) {
                $this->line("  + would create user $email");
                $this->userMap[$oid] = -1;

                continue;
            }
            $u = User::create([
                'name' => $name,
                'email' => $email,
                'password' => null,
            ]);
            $this->userMap[$oid] = $u->id;
            $this->line("  + created user $email");
        }
    }

    private function importProject(array $proj): void
    {
        $name = $proj['name'] ?? '(unnamed)';
        $ownerId = $this->userMap[$this->oid($proj['owner_ref'])] ?? null;
        $public = match ($proj['publicAccesLevel'] ?? null) {
            'readAndWrite' => 'write',
            'tokenBased' => null,
            default => null,
        };

        $this->line("→ {$name} (owner #{$ownerId})");

        if ($this->dry) {
            $this->walkTree($proj['rootFolder'][0] ?? null, '', $this->oid($proj['_id']), true);

            return;
        }

        DB::transaction(function () use ($proj, $name, $ownerId, $public) {
            $project = Project::create([
                'name' => $name,
                'user_id' => $ownerId,
                'public_permission' => $public,
            ]);
            $project->ensureDirectories();
            $base = storage_path('app/private/'.$project->filesPath());

            $this->walkTree($proj['rootFolder'][0] ?? null, $base, $this->oid($proj['_id']), false);

            $shares = [];
            foreach ($proj['collaberator_refs'] ?? [] as $ref) {
                $uid = $this->userMap[$this->oid($ref)] ?? null;
                if ($uid && $uid !== $ownerId) {
                    $shares[$uid] = ['permission' => 'write'];
                }
            }
            foreach ($proj['readOnly_refs'] ?? [] as $ref) {
                $uid = $this->userMap[$this->oid($ref)] ?? null;
                if ($uid && $uid !== $ownerId && ! isset($shares[$uid])) {
                    $shares[$uid] = ['permission' => 'read'];
                }
            }
            if ($shares) {
                $project->users()->sync($shares);
            }
        });
    }

    private function walkTree(?array $folder, string $absDir, ?string $projectOid, bool $dry): void
    {
        if (! $folder) {
            return;
        }

        if (! $dry && $absDir !== '' && ! is_dir($absDir)) {
            @mkdir($absDir, 0775, true);
        }

        foreach ($folder['docs'] ?? [] as $doc) {
            $docOid = $this->oid($doc['_id']);
            $name = $this->sanitize($doc['name'] ?? $docOid);
            $src = $this->base.'/docs/'.$docOid.'.json';
            if (! is_file($src)) {
                $this->warn("    ! missing doc $docOid ($name)");

                continue;
            }
            if ($dry) {
                $this->line("    doc: $name");

                continue;
            }
            $payload = json_decode((string) file_get_contents($src), true) ?: [];
            $content = implode("\n", $payload['lines'] ?? []);
            $target = $this->uniqueName($absDir, $name);
            file_put_contents($target, $content);
        }

        foreach ($folder['fileRefs'] ?? [] as $ref) {
            $fileOid = $this->oid($ref['_id']);
            $name = $this->sanitize($ref['name'] ?? $fileOid);
            $src = $this->base.'/files/'.$projectOid.'/'.$fileOid;
            if (! is_file($src)) {
                $this->warn("    ! missing file $fileOid ($name)");

                continue;
            }
            if ($dry) {
                $this->line('    file: '.$name.' ('.$this->humanSize(filesize($src)).')');

                continue;
            }
            $target = $this->uniqueName($absDir, $name);
            copy($src, $target);
        }

        foreach ($folder['folders'] ?? [] as $sub) {
            $subName = $this->sanitize($sub['name'] ?? 'folder');
            $subAbs = $absDir === '' ? '' : $absDir.'/'.$subName;
            if ($dry) {
                $this->line('    folder: '.$subName.'/');
            }
            $this->walkTree($sub, $subAbs, $projectOid, $dry);
        }
    }

    private function oid(mixed $v): ?string
    {
        if (is_string($v)) {
            return $v;
        }
        if (is_array($v) && isset($v['$oid'])) {
            return $v['$oid'];
        }

        return null;
    }

    private function sanitize(string $name): string
    {
        $name = trim($name);
        $name = str_replace(['/', '\\', "\0"], '_', $name);
        if ($name === '' || $name === '.' || $name === '..') {
            return 'unnamed';
        }

        return $name;
    }

    private function uniqueName(string $dir, string $name): string
    {
        $target = $dir.'/'.$name;
        if (! file_exists($target)) {
            return $target;
        }
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $stem = $ext === '' ? $name : substr($name, 0, -strlen($ext) - 1);
        $i = 1;
        while (true) {
            $candidate = $dir.'/'.$stem.' ('.$i.')'.($ext === '' ? '' : '.'.$ext);
            if (! file_exists($candidate)) {
                return $candidate;
            }
            $i++;
        }
    }

    private function humanSize(int $bytes): string
    {
        foreach (['B', 'K', 'M', 'G'] as $u) {
            if ($bytes < 1024) {
                return $bytes.$u;
            }
            $bytes = (int) ($bytes / 1024);
        }

        return $bytes.'T';
    }
}
