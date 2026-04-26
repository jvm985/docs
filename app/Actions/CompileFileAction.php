<?php

namespace App\Actions;

use App\Models\File;
use App\Models\Project;
use App\Models\User;
use App\Services\Compilers\CompilerFactory;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class CompileFileAction
{
    public function execute(File $file)
    {
        $user = auth()->user();
        if (!$user) {
             throw new \Exception("User not authenticated");
        }

        // 1. Bepaal de persistente workspace van deze specifieke gebruiker
        $workspaceDir = storage_path('app/workspaces/user_' . $user->id);
        
        if (!is_dir($workspaceDir)) {
            mkdir($workspaceDir, 0777, true);
        }

        // 2. Incrementele Sync: Alleen wat veranderd is in de DB naar schijf schrijven
        $this->syncUserWorkspace($user, $workspaceDir);

        // 3. Voer de compilatie uit IN de persistente map
        // Het pad naar de file is nu relatief binnen de user-workspace
        $project = $file->project;
        $relativeFilePath = $project->name . '/' . $file->getPath();
        $fullPathOnDisk = $workspaceDir . '/' . $relativeFilePath;

        if (!file_exists($fullPathOnDisk)) {
            throw new \Exception("File not found in workspace after sync: " . $relativeFilePath);
        }

        $compiler = CompilerFactory::make($file);
        
        // We draaien de compiler in de root van de user workspace zodat cross-project includes werken
        $result = $compiler->compile($fullPathOnDisk, $workspaceDir);

        return $result;
    }

    /**
     * Incrementele sync: Alleen bestanden die in de DB nieuwer zijn dan op schijf worden overschreven.
     */
    private function syncUserWorkspace(User $user, string $workspaceDir): void
    {
        // Haal alle project IDs op waar de gebruiker toegang tot heeft
        $projectIds = $user->projects()->pluck('projects.id')
            ->merge($user->sharedProjects()->pluck('projects.id'))
            ->merge(Project::where('is_public', true)->pluck('projects.id'))
            ->unique()
            ->values();

        foreach (Project::whereIn('id', $projectIds)->cursor() as $project) {
            $projectPath = $workspaceDir . '/' . $project->name;
            
            if (!is_dir($projectPath)) {
                mkdir($projectPath, 0777, true);
            }

            // Sync files van dit project
            foreach ($project->files()->cursor() as $projectFile) {
                if ($projectFile->type === 'file') {
                    $fullPath = $projectPath . '/' . $projectFile->getPath();
                    
                    // Check of de file op schijf ouder is dan in de DB
                    $needsUpdate = !file_exists($fullPath) || 
                                  filemtime($fullPath) < $projectFile->updated_at->timestamp;

                    if ($needsUpdate) {
                        if (!is_dir(dirname($fullPath))) {
                            mkdir(dirname($fullPath), 0777, true);
                        }

                        $content = $projectFile->binary_content ?? $projectFile->content;
                        file_put_contents($fullPath, $content);
                        
                        // Forceer de mtime op de timestamp van de DB voor de volgende check
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
    }
}
