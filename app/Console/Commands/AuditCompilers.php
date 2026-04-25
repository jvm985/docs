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
        $this->comment("\nTesting Cross-Project Sharing (Viewer perspective) with AAA, BBB and CCC...");

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

        // 2. Setup Project BBB
        $projectBBB = Project::create(['name' => 'bbb', 'user_id' => $owner->id]);
        $projectBBB->files()->create(['name' => 'napoleon.tex', 'type' => 'file', 'extension' => 'tex', 'content' => 'Napoleon content']);

        // 3. Setup Project CCC
        $projectCCC = Project::create(['name' => 'ccc', 'user_id' => $owner->id]);
        $projectCCC->files()->create(['name' => '4_liberalisme.tex', 'type' => 'file', 'extension' => 'tex', 'content' => 'Liberalisme content']);

        // 4. Setup Project AAA (Main)
        $projectAAA = Project::create(['name' => 'aaa', 'user_id' => $owner->id]);
        $mainFile = $projectAAA->files()->create([
            'name' => '5 geschiedenis.tex',
            'type' => 'file',
            'extension' => 'tex',
            'content' => "\\documentclass{article}\n\\begin{document}\nMain\n\\include{../bbb/napoleon}\n\\include{../ccc/4_liberalisme}\n\\end{document}"
        ]);

        // 5. Deel ALLES met de Viewer
        $projectAAA->sharedUsers()->attach($viewer->id, ['role' => 'viewer']);
        $projectBBB->sharedUsers()->attach($viewer->id, ['role' => 'viewer']);
        $projectCCC->sharedUsers()->attach($viewer->id, ['role' => 'viewer']);

        // 6. Compile as Viewer
        $this->info("  -> Attempting triple-project compile as Viewer...");
        auth()->login($viewer);
        
        $action = new \App\Actions\CompileFileAction();
        try {
            $res = $action->execute($mainFile);
            if ($res['type'] === 'pdf') {
                $this->info("  [OK] Triple-project compilation SUCCESSFUL for Viewer.");
            } else {
                $this->error("  [FAIL] Compilation FAILED.");
                $this->line("  Raw Output: " . $res['output']);
            }
        } catch (\Exception $e) {
            $this->error("  [EXCEPTION] " . $e->getMessage());
        }

        // Cleanup
        $projectBBB->delete();
        $projectCCC->delete();
        $projectAAA->delete();
        $owner->delete();
        $viewer->delete();
    }
}
