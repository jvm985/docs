<?php

namespace App\Actions;

use App\Models\File;
use App\Services\Compilers\CompilerFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CompileFileAction
{
    public function execute(File $file, array $options = []): array
    {
        $tempDir = storage_path('app/temp/' . Str::random(10));
        mkdir($tempDir, 0777, true);

        try {
            $this->prepareUserWorkspace($file, $tempDir);
            
            $compiler = CompilerFactory::make($file);
            
            // We pass the relative path including the project name
            return $compiler->compile($file, $tempDir, $options);
        } finally {
            $this->recursiveRemoveDir($tempDir);
        }
    }

    private function prepareUserWorkspace(File $activeFile, string $tempDir): void
    {
        $user = $activeFile->project->user;
        foreach ($user->projects as $project) {
            // Use project name as folder name (sanitized for paths)
            $projectFolderName = $project->name;
            
            foreach ($project->files as $projectFile) {
                if ($projectFile->type === 'file') {
                    $relativePath = $projectFile->getPath();
                    $fullPath = $tempDir . '/' . $projectFolderName . '/' . $relativePath;
                    
                    $dir = dirname($fullPath);
                    if (!is_dir($dir)) {
                        mkdir($dir, 0777, true);
                    }
                    
                    if ($projectFile->binary_content) {
                        file_put_contents($fullPath, $projectFile->binary_content);
                    } else {
                        file_put_contents($fullPath, $projectFile->content);
                    }
                }
            }
        }
    }

    private function recursiveRemoveDir($dir): void
    {
        if (!is_dir($dir)) return;
        foreach (array_diff(scandir($dir), array('.', '..')) as $file) {
            (is_dir("$dir/$file")) ? $this->recursiveRemoveDir("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }
}
