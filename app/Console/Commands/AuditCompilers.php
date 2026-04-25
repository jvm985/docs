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

        $this->testCrossProjectSharing();

        $this->info("\nAudit Complete.");
    }

    protected function testCrossProjectSharing()
    {
        $this->comment("\nTesting Cross-Project Sharing with '5 geschiedenis.tex' (Viewer perspective)...");

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

        // 2. Setup Project BBB (The dependency)
        $projectBBB = Project::create(['name' => 'bbb', 'user_id' => $owner->id]);
        $projectBBB->files()->create([
            'name' => 'napoleon.tex',
            'type' => 'file',
            'extension' => 'tex',
            'content' => 'Napoleon was hier in BBB.'
        ]);

        // 3. Setup Project AAA (The main project)
        $projectAAA = Project::create(['name' => 'aaa', 'user_id' => $owner->id]);
        $mainFile = $projectAAA->files()->create([
            'name' => '5 geschiedenis.tex',
            'type' => 'file',
            'extension' => 'tex',
            'content' => "\\documentclass{article}\n\\begin{document}\nHoofdtest: \\include{workspace/bbb/napoleon}\n\\end{document}"
        ]);

        // 4. Share Project AAA with Viewer
        $projectAAA->sharedUsers()->attach($viewer->id, ['role' => 'viewer']);

        // 5. Compile as Viewer
        $this->info("  -> Attempting compile as Viewer (ID: {$viewer->id})...");
        auth()->login($viewer);
        
        $action = new \App\Actions\CompileFileAction();
        try {
            $res = $action->execute($mainFile);
            if ($res['type'] === 'pdf') {
                $this->info("  [OK] '5 geschiedenis.tex' compilation SUCCESSFUL for Viewer.");
            } else {
                $this->error("  [FAIL] Compilation FAILED.");
                $this->line("  Raw Output: " . $res['output']);
            }
        } catch (\Exception $e) {
            $this->error("  [EXCEPTION] " . $e->getMessage());
        }

        // Cleanup
        $projectBBB->delete();
        $projectAAA->delete();
        $owner->delete();
        $viewer->delete();
    }
}
