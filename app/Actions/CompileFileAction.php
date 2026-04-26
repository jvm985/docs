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

        // Incrementele Sync met timestamp check
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
        // 1. Haal alle project IDs op
        $projectIds = $user->projects()->pluck('projects.id')
            ->merge($user->sharedProjects()->pluck('projects.id'))
            ->merge(Project::where('is_public', true)->pluck('projects.id'))
            ->unique()
            ->values();

        // 2. Check de allerlaatste wijziging in ALLE toegankelijke projecten/bestanden
        $lastDatabaseChange = DB::table('files')
            ->whereIn('project_id', $projectIds)
            ->max('updated_at');

        // 3. Als er niets veranderd is sinds onze laatste sync, stop direct!
        if ($user->last_synced_at && $lastDatabaseChange && $user->last_synced_at->greaterThanOrEqualTo($lastDatabaseChange)) {
            return;
        }

        // 4. Alleen als er wijzigingen zijn, voeren we de tragere loop uit
        foreach (Project::whereIn('id', $projectIds)->cursor() as $project) {
            $projectPath = $workspaceDir . '/' . $project->name;
            
            if (!is_dir($projectPath)) {
                mkdir($projectPath, 0777, true);
            }

            foreach ($project->files()->cursor() as $projectFile) {
                if ($projectFile->type === 'file') {
                    $fullPath = $projectPath . '/' . $projectFile->getPath();
                    
                    $needsUpdate = !file_exists($fullPath) || 
                                  filemtime($fullPath) < $projectFile->updated_at->timestamp;

                    if ($needsUpdate) {
                        if (!is_dir(dirname($fullPath))) {
                            mkdir(dirname($fullPath), 0777, true);
                        }
                        $content = $projectFile->binary_content ?? $projectFile->content;
                        file_put_contents($fullPath, $content);
                        touch($fullPath, $projectFile->updated_at->timestamp);
                    }
                } elseif ($projectFile->type === 'folder') {
                    $folderPath = $projectPath . '/' . $projectFile->getPath();
                    if (!is_dir($folderPath)) {
                        mkdir($folderPath, 0777, true);
                    }
                }
            }
        }

        // 5. Update de sync timestamp van de gebruiker
        $user->update(['last_synced_at' => now()]);
    }
}
