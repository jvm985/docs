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
            $this->prepareProjectFiles($file, $tempDir);
            
            $compiler = CompilerFactory::make($file);
            
            return $compiler->compile($file, $tempDir, $options);
        } finally {
            $this->recursiveRemoveDir($tempDir);
        }
    }

    private function prepareProjectFiles(File $file, string $tempDir): void
    {
        foreach ($file->project->files as $projectFile) {
            if ($projectFile->type === 'file') {
                $path = $tempDir . '/' . $projectFile->name;
                file_put_contents($path, $projectFile->content);
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
