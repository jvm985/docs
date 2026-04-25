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

        // 1. Flatten and synchronize the entire user workspace
        $this->syncFlatWorkspace(auth()->user(), $workspaceDir);
        
        // 2. Prepare the active file (with path rewriting for TeX/Typst/R)
        $compiledFile = $this->prepareActiveFileForCompilation($file, $workspaceDir);
        
        $compiler = CompilerFactory::make($file);
        
        // 3. Run the compiler in the ROOT directory
        return $compiler->compile($compiledFile, $workspaceDir, $options);
    }

    private function syncFlatWorkspace($user, string $workspaceDir): void
    {
        $user->refresh();
        $allProjects = $user->projects->merge($user->sharedProjects)->merge(Project::where('is_public', true)->get())->unique('id');

        foreach ($allProjects as $project) {
            foreach ($project->files as $projectFile) {
                if ($projectFile->type === 'file') {
                    // Flattened name: ProjectName___RelativePath.ext
                    $flatName = $project->name . '___' . str_replace('/', '___', $projectFile->getPath());
                    $fullPath = $workspaceDir . '/' . $flatName;

                    $content = $projectFile->binary_content ?? $projectFile->content;
                    if (!file_exists($fullPath) || file_get_contents($fullPath) !== $content) {
                        file_put_contents($fullPath, $content);
                    }
                }
            }
        }
    }

    private function prepareActiveFileForCompilation(File $file, string $workspaceDir): File
    {
        $flatName = $file->project->name . '___' . str_replace('/', '___', $file->getPath());
        $content = $file->content;

        // Rewrite paths for LaTeX: \include{../project/file} -> \include{project___file}
        if (in_array(strtolower($file->extension), ['tex', 'rmd', 'md', 'typ'])) {
            // Match ../Project/Path patterns
            $content = preg_replace_callback('/(\\include|\\input|#include|#import)\{?"?\.{2}\/([^\/\}"]+)\/([^\}"]+)"?\}?/', function($m) {
                $project = $m[2];
                $path = str_replace('/', '___', $m[3]);
                // Remove extension for TeX includes if present
                $path = preg_replace('/\.tex$/i', '', $path);
                
                if (str_starts_with($m[1], '#')) { // Typst
                    return "{$m[1]} \"{$project}___{$path}.typ\"";
                }
                return "{$m[1]}{{$project}___{$path}}";
            }, $content);
        }

        // Create a temporary File model for the compiler to use
        $tempFile = $file->replicate();
        $tempFile->name = $flatName;
        $tempFile->content = $content;
        
        return $tempFile;
    }
}
