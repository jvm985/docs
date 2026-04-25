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
    protected $description = 'Deep audit of all document compilers including migrated projects';

    public function handle()
    {
        $this->comment("Starting Migrated Project Audit...");

        $this->testMigratedProjectIntegrity();

        $this->info("\nAudit Complete.");
    }

    protected function testMigratedProjectIntegrity()
    {
        $this->comment("\nTesting Migrated Project: 6SWO (Complex Structure)...");

        $user = User::where('email', 'joachim.vanmeirvenne@atheneumkapellen.be')->first();
        if (!$user) {
            $this->error("  [FAIL] User not found!");
            return;
        }

        $project = Project::where('user_id', $user->id)->where('name', '6SWO')->first();
        if (!$project) {
            $this->error("  [FAIL] Project 6SWO not found!");
            return;
        }

        $mainFile = $project->files()->where('name', '6SWO.tex')->first();
        if (!$mainFile) {
            $mainFile = $project->files()->where('name', 'cursus_irishof.tex')->first();
        }
        if (!$mainFile) {
            $this->error("  [FAIL] main file cursus_irishof.tex not found!");
            return;
        }
        
        $this->info("  -> Attempting compile of 6SWO/cursus_irishof.tex...");
        auth()->login($user);
        
        $action = new \App\Actions\CompileFileAction();
        try {
            $res = $action->execute($mainFile);
            if ($res['type'] === 'pdf') {
                $this->info("  [OK] Migrated project 6SWO compilation SUCCESSFUL.");
                $this->info("  [OK] PDF Generated at: " . $res['url']);
            } else {
                $this->error("  [FAIL] Migrated project compilation FAILED.");
                $this->line("  Raw Output: \n" . $res['output']);
            }
        } catch (\Exception $e) {
            $this->error("  [EXCEPTION] " . $e->getMessage());
        }
    }
}
