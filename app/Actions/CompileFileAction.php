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
        $projectIds = $user->projects()->pluck('projects.id')
            ->merge($user->sharedProjects()->pluck('projects.id'))
            ->merge(Project::where('is_public', true)->pluck('projects.id'))
            ->unique()
            ->values();
        
        \Log::info("Syncing workspace for user: " . $user->email . " (Project IDs: " . $projectIds->count() . ")");

        foreach (Project::whereIn('id', $projectIds)->cursor() as $project) {
            $projectPath = $workspaceDir . '/' . $project->name;
            if (!is_dir($projectPath)) {
                mkdir($projectPath, 0777, true);
            }

            // Gebruik cursor om te voorkomen dat alle files van een project tegelijk in memory komen
            foreach ($project->files()->cursor() as $projectFile) {
                if ($projectFile->type === 'file') {
                    $fullPath = $projectPath . '/' . $projectFile->getPath();
                    
                    // Alleen syncen als de file echt veranderd is (op basis van timestamp)
                    $needsUpdate = !file_exists($fullPath) || 
                                  filemtime($fullPath) < $projectFile->updated_at->timestamp;

                    if ($needsUpdate) {
                        if (!is_dir(dirname($fullPath))) {
                            mkdir(dirname($fullPath), 0777, true);
                        }

                        $content = $projectFile->binary_content ?? $projectFile->content;
                        file_put_contents($fullPath, $content);
                        // Zet mtime gelijk aan updated_at voor toekomstige checks
                        touch($fullPath, $projectFile->updated_at->timestamp);
                    }
                }
            }
        }
    }
}
