<?php

namespace App\Actions;

use App\Models\File;
use App\Models\Project;
use App\Models\User;
use App\Services\Compilers\CompilerFactory;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class CompileFileAction
{
    public function execute(File $file)
    {
        $user = auth()->user();
        if (!$user) {
             throw new \Exception("User not authenticated");
        }

        $workspaceDir = storage_path('app/workspaces/user_' . $user->id);
        
        if (!is_dir($workspaceDir)) {
            mkdir($workspaceDir, 0777, true);
        }

        $this->syncUserWorkspace($user, $workspaceDir);

        $project = $file->project;
        $relativeFilePath = $project->name . '/' . $file->getPath();
        $fullPathOnDisk = $workspaceDir . '/' . $relativeFilePath;

        if (!file_exists($fullPathOnDisk)) {
            throw new \Exception("File not found in workspace after sync: " . $relativeFilePath);
        }

        $compiler = CompilerFactory::make($file);
        return $compiler->compile($fullPathOnDisk, $workspaceDir);
    }

    private function syncUserWorkspace(User $user, string $workspaceDir): void
    {
        // 1. Haal ALLE toegankelijke projecten op (Met ID en UpdatedAt voor de checksum)
        $accessibleProjects = Project::where('user_id', $user->id)
            ->orWhereIn('id', function($query) use ($user) {
                $query->select('project_id')->from('project_user')->where('user_id', $user->id);
            })
            ->orWhere('is_public', true)
            ->get(['id', 'name', 'updated_at']);

        // 2. Bereken een checksum van de huidige staat van ALLE projecten
        // Dit vangt toevegingen, verwijderingen en metadata wijzigingen op.
        $checksumStr = $accessibleProjects->map(fn($p) => "{$p->id}-{$p->updated_at->timestamp}")->implode('|');
        $currentChecksum = md5($checksumStr);

        // 3. Als de checksum gelijk is, is er metadata-technisch niets veranderd.
        // We moeten echter OOK checken of er bestanden BINNEN de projecten zijn gewijzigd.
        $projectIds = $accessibleProjects->pluck('id')->toArray();
        $lastFileChange = DB::table('files')
            ->whereIn('project_id', $projectIds)
            ->max('updated_at');
        
        $fullChecksum = md5($currentChecksum . $lastFileChange);

        // 4. Snelle exit als de totale staat van de DB overeenkomt met de lokale workspace
        if ($user->projects_checksum === $fullChecksum) {
            return;
        }

        // 5. Er is iets veranderd -> Voer de incrementele sync uit
        $projectNames = $accessibleProjects->pluck('name', 'id')->toArray();
        
        // Haal alleen files op die nieuwer zijn dan de vorige sync (voor extra snelheid)
        $query = File::whereIn('project_id', $projectIds);
        if ($user->last_synced_at) {
            $query->where('updated_at', '>', $user->last_synced_at);
        }

        foreach ($query->cursor() as $projectFile) {
            $projectName = $projectNames[$projectFile->project_id] ?? null;
            if (!$projectName) continue;

            $projectPath = $workspaceDir . '/' . $projectName;
            $fullPath = $projectPath . '/' . $projectFile->getPath();
            
            if ($projectFile->type === 'file') {
                if (!is_dir(dirname($fullPath))) {
                    mkdir(dirname($fullPath), 0777, true);
                }
                $content = $projectFile->binary_content ?? $projectFile->content;
                file_put_contents($fullPath, $content);
                touch($fullPath, $projectFile->updated_at->timestamp);
            } elseif ($projectFile->type === 'folder') {
                if (!is_dir($fullPath)) {
                    mkdir($fullPath, 0777, true);
                }
            }
        }

        // 6. Zorg bij nieuwe projecten dat de root mappen bestaan
        foreach ($projectNames as $name) {
            $path = $workspaceDir . '/' . $name;
            if (!is_dir($path)) {
                mkdir($path, 0777, true);
            }
        }

        // 7. Update de staat van de gebruiker
        $user->update([
            'projects_checksum' => $fullChecksum,
            'last_synced_at' => now()
        ]);
    }
}
