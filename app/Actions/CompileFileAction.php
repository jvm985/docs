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
        $result = $compiler->compile($fullPathOnDisk, $workspaceDir);

        return $result;
    }

    private function syncUserWorkspace(User $user, string $workspaceDir): void
    {
        $t1 = microtime(true);
        
        // 1. Haal project IDs op
        $projectIds = $user->projects()->pluck('projects.id')
            ->merge($user->sharedProjects()->pluck('projects.id'))
            ->merge(Project::where('is_public', true)->pluck('projects.id'))
            ->unique()
            ->values()
            ->toArray();
        $t2 = microtime(true);

        $lastSync = $user->last_synced_at;

        // 2. Check de allerlaatste wijziging in de DB met rauwe SQL voor snelheid
        $lastDatabaseChangeStr = DB::table('files')
            ->whereIn('project_id', $projectIds)
            ->max('updated_at');
        
        $t3 = microtime(true);

        if ($lastSync && $lastDatabaseChangeStr) {
            $lastDatabaseChange = new \Illuminate\Support\Carbon($lastDatabaseChangeStr);
            if ($lastSync->greaterThanOrEqualTo($lastDatabaseChange)) {
                \Log::info(sprintf("TurboSync [SKIP]: User %s, ID: %s. Logic time: %ss", $user->email, $user->id, round($t3-$t1, 4)));
                return;
            }
        }

        // 3. Haal de 'dirty' files op
        $query = File::whereIn('project_id', $projectIds);
        if ($lastSync) {
            $query->where('updated_at', '>', $lastSync);
        }

        $count = 0;
        foreach ($query->cursor() as $projectFile) {
            $count++;
            $project = $projectFile->project;
            $projectPath = $workspaceDir . '/' . $project->name;
            
            if ($projectFile->type === 'file') {
                $fullPath = $projectPath . '/' . $projectFile->getPath();
                if (!is_dir(dirname($fullPath))) {
                    mkdir(dirname($fullPath), 0777, true);
                }
                $content = $projectFile->binary_content ?? $projectFile->content;
                file_put_contents($fullPath, $content);
                touch($fullPath, $projectFile->updated_at->timestamp);
            } elseif ($projectFile->type === 'folder') {
                $folderPath = $projectPath . '/' . $projectFile->getPath();
                if (!is_dir($folderPath)) {
                    mkdir($folderPath, 0777, true);
                }
            }
        }

        // 4. Fallback voor nieuwe mappen
        if (!$lastSync) {
            foreach (Project::whereIn('id', $projectIds)->cursor() as $project) {
                $projectPath = $workspaceDir . '/' . $project->name;
                if (!is_dir($projectPath)) {
                    mkdir($projectPath, 0777, true);
                }
            }
        }

        $t4 = microtime(true);
        $user->update(['last_synced_at' => now()]);
        
        \Log::info(sprintf("TurboSync [EXEC]: User %s, ID: %s. Files synced: %d. Total time: %ss (Logic: %ss, DB: %ss, Loop: %ss)", 
            $user->email, $user->id, $count, round($t4-$t1, 4), round($t2-$t1, 4), round($t3-$t2, 4), round($t4-$t3, 4)));
    }
}
