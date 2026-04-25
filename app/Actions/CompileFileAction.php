<?php

namespace App\Actions;

use App\Models\File;
use App\Models\Project;
use App\Services\Compilers\CompilerFactory;
use Illuminate\Support\Facades\Storage;
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

        // Zorg dat de map van de huidige gebruiker ALTIJD de laatste versie heeft van alle bestanden
        // waar hij toegang toe heeft. Dit lost het "I can't write" probleem voor viewers op.
        $this->syncUserWorkspace(auth()->user(), $workspaceDir);
        
        $compiler = CompilerFactory::make($file);
        
        return $compiler->compile($file, $workspaceDir, $options);
    }

    private function syncUserWorkspace($user, string $workspaceDir): void
    {
        $myProjects = $user->projects;
        $sharedProjects = $user->sharedProjects;
        $publicProjects = Project::where('is_public', true)->get();
private function syncUserWorkspace($user, string $workspaceDir): void
{
    // Haal alle projecten op waar deze gebruiker toegang toe heeft
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

                if (!file_exists($fullPath) || file_get_contents($fullPath) !== $content) {
                    file_put_contents($fullPath, $content);
                }
            }
        }
    }
}

