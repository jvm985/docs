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
        $this->comment("Starting Deep Audit (TEMPORARY WORKSPACE MODEL)...");

        $user = User::first() ?: User::factory()->create(['email' => 'audit@example.com']);
        auth()->login($user);

        $this->testCrossProjectSharing();

        $this->info("\nAudit Complete.");
    }

    protected function testCrossProjectSharing()
    {
        $this->comment("\nTesting Rigorous Scenario: project 'aaa' with '5 geschiedenis.tex'...");

        // 1. Setup users
        $owner = User::create(['name' => 'Owner', 'email' => 'owner_'.Str::random(5).'@test.com', 'password' => 'secret']);
        $viewer = User::create(['name' => 'Viewer', 'email' => 'viewer_'.Str::random(5).'@test.com', 'password' => 'secret']);

        // 2. Setup Project BBB (dependency)
        $projectBBB = Project::create(['name' => 'bbb', 'user_id' => $owner->id]);
        $projectBBB->files()->create(['name' => 'napoleon.tex', 'type' => 'file', 'extension' => 'tex', 'content' => 'Napoleon was hier.']);

        // 3. Setup Project AAA (main)
        $projectAAA = Project::create(['name' => 'aaa', 'user_id' => $owner->id]);
        
        // Nested file in AAA
        $projectAAA->files()->create([
            'name' => 'hoofdstukken/1_congres.tex',
            'type' => 'file',
            'extension' => 'tex',
            'content' => 'Content van het congres.'
        ]);

        // Main file in AAA
        $mainFile = $projectAAA->files()->create([
            'name' => '5 geschiedenis.tex',
            'type' => 'file',
            'extension' => 'tex',
            'content' => "\\documentclass{article}\n\\begin{document}\nStart\n\\include{hoofdstukken/1_congres}\n\\include{../bbb/napoleon}\n\\end{document}"
        ]);

        // 4. Share both with Viewer
        $projectAAA->sharedUsers()->attach($viewer->id, ['role' => 'viewer']);
        $projectBBB->sharedUsers()->attach($viewer->id, ['role' => 'viewer']);

        // 5. Compile as Viewer
        $this->info("  -> Attempting rigorous compile as Viewer (ID: {$viewer->id})...");
        auth()->login($viewer);
        
        $action = new \App\Actions\CompileFileAction();
        try {
            $res = $action->execute($mainFile);
            if ($res['type'] === 'pdf') {
                $this->info("  [OK] Rigorous compilation SUCCESSFUL for Viewer.");
            } else {
                $this->error("  [FAIL] Compilation FAILED.");
                $this->line("  Raw Output: \n" . $res['output']);
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
