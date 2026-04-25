<?php

namespace App\Actions;

use App\Models\File;
use App\Services\Compilers\CompilerFactory;
use Illuminate\Support\Str;

class CompileFileAction
{
    public function execute(File $file, array $options = []): array
    {
        // De workspace map is er al en is up-to-date gehouden door de FileController
        $currentUserId = auth()->id();
        $workspaceDir = storage_path("app/workspaces/user_{$currentUserId}");
        
        if (!is_dir($workspaceDir)) {
            // Alleen als de map echt weg is (bijv. na server verhuizing)
            // bouwen we hem eenmalig opnieuw op.
            mkdir($workspaceDir, 0777, true);
            $this->rebuildWorkspace(auth()->user(), $workspaceDir);
        }

        $compiler = CompilerFactory::make($file);
        
        return $compiler->compile($file, $workspaceDir, $options);
    }

    /**
     * Nood-herstel van de workspace mocht de schijf leeg zijn.
     */
    private function rebuildWorkspace($user, string $workspaceDir): void
    {
        $allProjects = $user->projects->merge($user->sharedProjects)->unique('id');

        foreach ($allProjects as $project) {
            $projectPath = $workspaceDir . '/' . $project->name;
            if (!is_dir($projectPath)) mkdir($projectPath, 0777, true);

            foreach ($project->files as $projectFile) {
                if ($projectFile->type === 'file') {
                    $fullPath = $workspaceDir . '/' . $project->name . '/' . $projectFile->getPath();
                    if (!is_dir(dirname($fullPath))) mkdir(dirname($fullPath), 0777, true);
                    
                    $content = $projectFile->binary_content ?? $projectFile->content;
                    file_put_contents($fullPath, $content);
                }
            }
        }
    }
}
