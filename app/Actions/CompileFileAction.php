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
        // We maken voor ELKE compilatie een verse, unieke, tijdelijke map aan
        // Dit garandeert dat we altijd mogen schrijven, ook als we 'Viewer' zijn.
        $tempDir = storage_path('app/temp/run_' . Str::random(10));
        mkdir($tempDir, 0777, true);

        try {
            // 1. Bouw de volledige workspace na in deze tijdelijke map
            $this->buildTemporaryWorkspace(auth()->user(), $tempDir);
            
            // 2. Het project-specifieke pad bepalen
            $projectDir = $tempDir . '/' . $file->project->name;
            
            $compiler = CompilerFactory::make($file);
            
            // 3. Draai de compiler in de project-map van de tijdelijke omgeving
            return $compiler->compile($file, $projectDir, $options);
        } finally {
            // 4. Schoon de tijdelijke rommel direct weer op
            $this->recursiveRemoveDir($tempDir);
        }
    }

    private function buildTemporaryWorkspace($user, string $tempDir): void
    {
        $allProjects = $user->projects->merge($user->sharedProjects)->merge(Project::where('is_public', true)->get())->unique('id');

        foreach ($allAccessibleProjects = $allProjects as $project) {
            $projectPath = $tempDir . '/' . $project->name;
            mkdir($projectPath, 0777, true);

            foreach ($project->files as $projectFile) {
                if ($projectFile->type === 'file') {
                    $fullPath = $projectPath . '/' . $projectFile->getPath();
                    
                    if (!is_dir(dirname($fullPath))) {
                        mkdir(dirname($fullPath), 0777, true);
                    }

                    $content = $projectFile->binary_content ?? $projectFile->content;
                    file_put_contents($fullPath, $content);
                }
            }
        }
    }

    private function recursiveRemoveDir($dir): void
    {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->recursiveRemoveDir("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }
}
