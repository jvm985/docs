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
        
        // 1. Zorg dat de basis-workspace bestaat
        if (!is_dir($workspaceDir)) {
            mkdir($workspaceDir, 0775, true);
        }

        // 2. Synchroniseer alle bestanden uit de DB naar de schijf
        $this->syncUserWorkspace(auth()->user(), $workspaceDir);
        
        // 3. DE TRUC: Maak in elk project een link naar de bovenliggende map
        // Hierdoor kan LaTeX "schrijven" naar ../ via een lokale referentie.
        $this->createWorkspaceLinks(auth()->user(), $workspaceDir);

        $compiler = CompilerFactory::make($file);
        return $compiler->compile($file, $workspaceDir, $options);
    }

    private function createWorkspaceLinks($user, string $workspaceDir): void
    {
        $projects = $user->projects->merge($user->sharedProjects)->unique('id');
        foreach ($projects as $project) {
            $projectPath = $workspaceDir . '/' . $project->name;
            $linkPath = $projectPath . '/__workspace__';
            
            if (is_dir($projectPath) && !file_exists($linkPath)) {
                // Maak een symlink van Project/.. naar de workspace root
                // Dit omzeilt de "paranoid" check van LaTeX omdat we nu via een submap werken
                @symlink('..', $linkPath);
            }
        }
    }

    private function syncUserWorkspace($user, string $workspaceDir): void
    {
        $user->unsetRelation('projects');
        $user->unsetRelation('sharedProjects');
        
        $myProjects = $user->projects;
        $sharedProjects = $user->sharedProjects;
        $publicProjects = Project::where('is_public', true)->get();

        $allAccessibleProjects = $myProjects->merge($sharedProjects)->merge($publicProjects)->unique('id');

        foreach ($allAccessibleProjects as $project) {
            $projectFolderName = $project->name;
            $projectPath = $workspaceDir . '/' . $projectFolderName;
            
            if (!is_dir($projectPath)) {
                mkdir($projectPath, 0775, true);
            }

            foreach ($project->files as $projectFile) {
                if ($projectFile->type === 'file') {
                    $relativePath = $projectFile->getPath();
                    $fullPath = $projectPath . '/' . $relativePath;

                    if (!is_dir(dirname($fullPath))) {
                        mkdir(dirname($fullPath), 0775, true);
                    }

                    $content = $projectFile->binary_content ?? $projectFile->content;
                    
                    if (!file_exists($fullPath) || file_get_contents($fullPath) !== $content) {
                        file_put_contents($fullPath, $content);
                    }
                }
            }
        }
    }
}
