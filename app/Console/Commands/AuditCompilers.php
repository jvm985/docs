<?php

namespace App\Console\Commands;

use App\Models\File;
use App\Models\Project;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AuditCompilers extends Command
{
    protected $signature = 'app:audit-compilers';
    protected $description = 'Deep audit of all document compilers including cross-project sharing';

    public function handle()
    {
        $this->comment("Starting Deep Audit of Compilers...");

        $user = User::first() ?: User::factory()->create(['email' => 'audit@example.com']);
        auth()->login($user);

        $project = Project::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'AuditProject'],
            ['description' => 'Automatic audit project']
        );

        $this->testCrossProjectSharing();

        $this->info("\nAudit Complete.");
    }

    protected function testCrossProjectSharing()
    {
        $this->comment("\nTesting Cross-Project Sharing with \include (Viewer perspective)...");

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
            'content' => "\\documentclass{article}\n\\begin{document}\nInclude test: \\include{../shared_lib/header}\n\\end{document}"
        ]);

        // 4. Share Project B with Viewer
        $projectB->sharedUsers()->attach($viewer->id, ['role' => 'viewer']);

        // 5. Compile as Viewer
        $this->info("  -> Attempting compile as Viewer (ID: {$viewer->id})...");
        auth()->login($viewer);
        
        $action = new \App\Actions\CompileFileAction();
        try {
            $res = $action->execute($mainFile);
            if ($res['type'] === 'pdf') {
                $this->info("  [OK] Cross-project \include SUCCESSFUL for Viewer.");
            } else {
                $this->error("  [FAIL] Cross-project \include FAILED.");
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
}
