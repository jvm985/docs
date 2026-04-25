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

        // Zorg dat ALLES waar deze gebruiker bij mag, fysiek op schijf staat in ZIJN eigen map.
        $this->syncUserWorkspace(auth()->user(), $workspaceDir);
        
        $compiler = CompilerFactory::make($file);
        
        return $compiler->compile($file, $workspaceDir, $options);
    }

    private function syncUserWorkspace($user, string $workspaceDir): void
    {
        $user->refresh();
        $myProjects = $user->projects;
        $sharedProjects = $user->sharedProjects;
        $publicProjects = Project::where('is_public', true)->get();

        $allAccessibleProjects = $myProjects->merge($sharedProjects)->merge($publicProjects)->unique('id');

        foreach ($allAccessibleProjects as $project) {
            $projectPath = $workspaceDir . '/' . $project->name;
            if (!is_dir($projectPath)) {
                mkdir($projectPath, 0777, true);
            }

            foreach ($project->files as $projectFile) {
                if ($projectFile->type === 'file') {
                    $fullPath = $projectPath . '/' . $projectFile->getPath();
                    if (!is_dir(dirname($fullPath))) {
                        mkdir(dirname($fullPath), 0777, true);
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
