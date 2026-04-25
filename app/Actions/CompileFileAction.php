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
            if (!mkdir($workspaceDir, 0777, true)) {
                $error = error_get_last();
                throw new \Exception("Failed to create workspace directory: {$workspaceDir}. Error: " . ($error['message'] ?? 'Unknown'));
            }
        }

        // 1. Synchroniseer alle bestanden
        $this->syncUserWorkspace(auth()->user(), $workspaceDir);
        
        // 2. DE TRUC: Maak in elk project een link naar de workspace root
        // Hierdoor kun je via 'workspace/project/bestand' overal bij zonder '../'
        $this->createWorkspaceLinks(auth()->user(), $workspaceDir);

        $compiler = CompilerFactory::make($file);
        
        return $compiler->compile($file, $workspaceDir, $options);
    }

    private function createWorkspaceLinks($user, string $workspaceDir): void
    {
        $myProjects = $user->projects;
        $sharedProjects = $user->sharedProjects;
        $publicProjects = Project::where('is_public', true)->get();
        $allProjects = $myProjects->merge($sharedProjects)->merge($publicProjects)->unique('id');

        foreach ($allProjects as $project) {
            $projectPath = $workspaceDir . '/' . $project->name;
            if (is_dir($projectPath)) {
                $linkPath = $projectPath . '/workspace';
                if (!file_exists($linkPath)) {
                    // Link 'project/workspace' naar '..' (de workspace root)
                    @symlink('..', $linkPath);
                }
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
                    
                    $shouldWrite = false;
                    if (!file_exists($fullPath)) {
                        $shouldWrite = true;
                    } else {
                        if ($projectFile->binary_content) {
                            if (file_get_contents($fullPath) !== $content) $shouldWrite = true;
                        } else {
                            $shouldWrite = true;
                        }
                    }

                    if ($shouldWrite) {
                        file_put_contents($fullPath, $content);
                    }
                }
            }
        }
    }
}
