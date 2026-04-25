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
        $userId = $file->project->user_id;
        $workspaceDir = storage_path("app/workspaces/user_{$userId}");
        
        if (!is_dir($workspaceDir)) {
            mkdir($workspaceDir, 0777, true);
        }

        // Synchronize database files to the persistent filesystem
        $this->syncUserWorkspace($file->project->user, $workspaceDir);
        
        $compiler = CompilerFactory::make($file);
        
        // Use the persistent workspace directory
        return $compiler->compile($file, $workspaceDir, $options);
    }

    private function syncUserWorkspace($user, string $workspaceDir): void
    {
        // 1. Get all current files from DB to track what should exist
        $existingFiles = [];
        
        foreach ($user->projects as $project) {
            $projectFolderName = $project->name;
            $projectPath = $workspaceDir . '/' . $projectFolderName;
            
            if (!is_dir($projectPath)) {
                mkdir($projectPath, 0777, true);
            }

            foreach ($project->files as $projectFile) {
                if ($projectFile->type === 'file') {
                    $relativePath = $projectFolderName . '/' . $projectFile->getPath();
                    $fullPath = $workspaceDir . '/' . $relativePath;
                    $existingFiles[] = $fullPath;

                    $dir = dirname($fullPath);
                    if (!is_dir($dir)) {
                        mkdir($dir, 0777, true);
                    }

                    // Only write if content is different or file doesn't exist
                    $content = $projectFile->binary_content ?? $projectFile->content;
                    if (!file_exists($fullPath) || file_get_contents($fullPath) !== $content) {
                        file_put_contents($fullPath, $content);
                    }
                }
            }
        }

        // 2. Optional: Cleanup files in workspace that are no longer in DB
        // (Skipped for now to protect auxiliary compiler files like .aux, .log, .RData)
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
