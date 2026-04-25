<?php

namespace App\Actions;

use App\Models\File;
use App\Models\Project;
use App\Services\Compilers\CompilerFactory;
use Illuminate\Support\Str;

class CompileFileAction
{
    public function execute(File $file, array $options = []): array
    {
        $currentUserId = auth()->id();
        $workspaceDir = storage_path("app/workspaces/user_{$currentUserId}");
        
        if (!is_dir($workspaceDir)) {
            mkdir($workspaceDir, 0777, true);
        }

        // Zorg dat de map van de huidige gebruiker ALTIJD alle bestanden heeft waar hij toegang toe heeft
        $this->syncUserWorkspace(auth()->user(), $workspaceDir);
        
        $compiler = CompilerFactory::make($file);
        
        return $compiler->compile($file, $workspaceDir, $options);
    }

    private function syncUserWorkspace($user, string $workspaceDir): void
    {
        if (!is_dir($workspaceDir)) {
            mkdir($workspaceDir, 0777, true);
        }

        // Forceer een refresh van de relaties om caching-problemen te voorkomen (vooral in tests/audit)
        $user->unsetRelation('projects');
        $user->unsetRelation('sharedProjects');
        
        $myProjects = $user->projects;
        $sharedProjects = $user->sharedProjects;
        $publicProjects = Project::where('is_public', true)->get();

        $allAccessibleProjects = $myProjects->merge($sharedProjects)->merge($publicProjects)->unique('id');
        
        // DEBUG: \Log::info("Syncing workspace for user {$user->id}. Projects: " . $allAccessibleProjects->pluck('name')->implode(', '));

        foreach ($allAccessibleProjects as $project) {
            $projectFolderName = $project->name;
            $projectPath = $workspaceDir . '/' . $projectFolderName;
            
            if (!is_dir($projectPath)) {
                mkdir($projectPath, 0777, true);
            }

            foreach ($project->files as $projectFile) {
                if ($projectFile->type === 'file') {
                    $relativePath = $projectFile->getPath();
                    $fullPath = $projectPath . '/' . $relativePath;

                    $dir = dirname($fullPath);
                    if (!is_dir($dir)) {
                        mkdir($dir, 0777, true);
                    }

                    $content = $projectFile->binary_content ?? $projectFile->content;
                    
                    // Alleen schrijven als de inhoud anders is of het bestand niet bestaat
                    if (!file_exists($fullPath) || file_get_contents($fullPath) !== $content) {
                        file_put_contents($fullPath, $content);
                    }
                }
            }
        }
    }
}
