<?php

namespace App\Console\Commands;

use App\Models\File;
use App\Models\Project;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Controllers\FileController;

class AuditCompilers extends Command
{
    protected $signature = 'app:audit-compilers';
    protected $description = 'Deep audit of all document compilers including cross-project sharing';

    public function handle()
    {
        $this->header("Starting Deep Audit of Compilers");

        $user = User::first() ?: User::factory()->create(['email' => 'audit@example.com']);
        auth()->login($user);

        $project = Project::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Audit Project'],
            ['description' => 'Automatic audit project']
        );

        $tests = [
            'LaTeX' => [
                'name' => 'test_audit.tex',
                'content' => "\\documentclass{article}\\begin{document}Audit LaTeX OK\\end{document}",
                'ext' => 'tex'
            ],
            'Typst' => [
                'name' => 'test_audit.typ',
                'content' => "= Audit Typst OK",
                'ext' => 'typ'
            ],
            'Markdown' => [
                'name' => 'test_audit.md',
                'content' => "# Audit Markdown OK",
                'ext' => 'md'
            ],
            'R' => [
                'name' => 'test_audit.R',
                'content' => "audit_val <- 42\nprint(paste('Audit R OK', audit_val))\nplot(1:10)",
                'ext' => 'R'
            ]
        ];

        foreach ($tests as $type => $data) {
            $this->testCompiler($project, $type, $data);
        }

        $this->testCrossProjectSharing();

        $this->info("\nAudit Complete.");
    }

    protected function testCompiler($project, $label, $data)
    {
        $this->comment("\nTesting $label...");

        $file = File::updateOrCreate(
            ['project_id' => $project->id, 'name' => $data['name']],
            ['type' => 'file', 'extension' => $data['ext'], 'content' => $data['content']]
        );

        $action = new \App\Actions\CompileFileAction();
        $response = $action->execute($file, $data['options'] ?? []);
        $content = (object)$response;

        if ($content->type === 'pdf') {
            $this->info("  [OK] Generated PDF: " . $content->url);
        } elseif ($content->type === 'r') {
            $this->info("  [OK] R Execution Successful");
            if (isset($content->result['structured_output']) && count($content->result['structured_output']) > 0) {
                $this->info("  [OK] Structured output received (" . count($content->result['structured_output']) . " lines)");
                foreach ($content->result['structured_output'] as $line) {
                    $color = $line['type'] === 'code' ? 'cyan' : ($line['type'] === 'error' ? 'red' : 'white');
                    $this->line("    <fg=$color>" . ($line['type'] === 'code' ? '> ' : '  ') . $line['content'] . "</>");
                }
            } else {
                $this->error("  [FAIL] R ran but structured_output is EMPTY");
                $this->line("  Raw Output: " . $content->output);
            }

            if (isset($content->result['variables']) && count($content->result['variables']) > 0) {
                $this->info("  [OK] Variables captured (" . count($content->result['variables']) . ")");
                foreach ($content->result['variables'] as $var) {
                    $this->line("    <fg=yellow>{$var['name']}</> ({$var['type']}): " . \Illuminate\Support\Str::limit($var['value'], 40));
                }
            }
        } else {
            $this->error("  [FAIL] Unexpected response type: " . $content->type);
            $this->line("  Raw Output: " . ($content->output ?? 'NULL'));
        }
    }

    protected function testCrossProjectSharing()
    {
        $this->comment("\nTesting Cross-Project Sharing (Viewer perspective)...");

        // 1. Setup two users
        $owner = User::create([
            'name' => 'Audit Owner',
            'email' => 'owner_' . Str::random(5) . '@audit.com',
            'password' => bcrypt('password')
        ]);
        $viewer = User::create([
            'name' => 'Audit Viewer',
            'email' => 'viewer_' . Str::random(5) . '@audit.com',
            'password' => bcrypt('password')
        ]);

        // 2. Setup Project A (The dependency)
        $projectA = Project::create(['name' => 'shared_lib', 'user_id' => $owner->id]);
        $projectA->files()->create([
            'name' => 'header.tex',
            'type' => 'file',
            'extension' => 'tex',
            'content' => 'DEFINED_IN_SHARED_LIB'
        ]);

        // 3. Setup Project B (The main project)
        $projectB = Project::create(['name' => 'main_doc', 'user_id' => $owner->id]);
        $mainFile = $projectB->files()->create([
            'name' => 'main.tex',
            'type' => 'file',
            'extension' => 'tex',
            'content' => "\\documentclass{article}\n\\begin{document}\nInclude test: \\input{../shared_lib/header.tex}\n\\end{document}"
        ]);

        // 4. Share Project B with Viewer
        $projectB->sharedUsers()->attach($viewer->id, ['role' => 'viewer']);

        // 5. Compile as Viewer
        $this->info("  -> Attempting compile as Viewer (ID: {$viewer->id})...");
        auth()->login($viewer);
        
        // DEBUG DISK
        $workspaceDir = storage_path("app/workspaces/user_{$viewer->id}");
        $this->info("  -> Checking disk contents in: $workspaceDir");
        if (is_dir($workspaceDir)) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($workspaceDir));
            foreach ($files as $f) {
                if (!$f->isDir()) $this->line("     - " . str_replace($workspaceDir, "", $f->getPathname()));
            }
        } else {
            $this->error("     [ERROR] Workspace directory DOES NOT EXIST!");
        }

        $action = new \App\Actions\CompileFileAction();
        try {
            $res = $action->execute($mainFile);
            if ($res['type'] === 'pdf') {
                $this->info("  [OK] Cross-project compilation SUCCESSFUL for Viewer.");
            } else {
                $this->error("  [FAIL] Cross-project compilation FAILED.");
                $this->line("  Raw Output: " . $res['output']);
            }
        } catch (\Exception $e) {
            $this->error("  [EXCEPTION] " . $e->getMessage());
        }

        // Cleanup
        $projectA->delete();
        $projectB->delete();
        $owner->delete();
        $viewer->delete();
    }

    protected function header($text)
    {
        $this->line(str_repeat("=", 50));
        $this->info($text);
        $this->line(str_repeat("=", 50));
    }
}
