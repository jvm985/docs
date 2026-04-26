<?php

namespace App\Actions;

use App\Models\File;
use App\Models\Project;
use App\Models\User;
use App\Services\Compilers\CompilerFactory;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class CompileFileAction
{
    public function execute(File $file)
    {
        $user = auth()->user();
        if (!$user) {
             throw new \Exception("User not authenticated");
        }

        $workspaceDir = storage_path('app/workspaces/user_' . $user->id);
        
        if (!is_dir($workspaceDir)) {
            mkdir($workspaceDir, 0777, true);
        }

        // Turbo Sync: Alleen gewijzigde bestanden ophalen
        $this->syncUserWorkspace($user, $workspaceDir);

        $project = $file->project;
        $relativeFilePath = $project->name . '/' . $file->getPath();
        $fullPathOnDisk = $workspaceDir . '/' . $relativeFilePath;

        if (!file_exists($fullPathOnDisk)) {
            throw new \Exception("File not found in workspace after sync: " . $relativeFilePath);
        }

        $compiler = CompilerFactory::make($file);
        $result = $compiler->compile($fullPathOnDisk, $workspaceDir);

        return $result;
    }

    private function syncUserWorkspace(User $user, string $workspaceDir): void
    {
        // 1. Haal project IDs op
        $projectIds = $user->projects()->pluck('projects.id')
            ->merge($user->sharedProjects()->pluck('projects.id'))
            ->merge(Project::where('is_public', true)->pluck('projects.id'))
            ->unique()
            ->values();

        // 2. Definieer de start-tijd voor de sync (of 1970 als we nog nooit gesynct hebben)
        $lastSync = $user->last_synced_at;

        // 3. Haal ALLEEN de bestanden op die veranderd zijn sinds de laatste sync
        // OF haal alles op als de map nog niet bestaat (of leeg is)
        $query = File::whereIn('project_id', $projectIds);
        
        if ($lastSync) {
            $query->where('updated_at', '>', $lastSync);
        }

        // 4. Update alleen de 'dirty' files
        foreach ($query->cursor() as $projectFile) {
            $project = $projectFile->project;
            $projectPath = $workspaceDir . '/' . $project->name;
            
            if ($projectFile->type === 'file') {
                $fullPath = $projectPath . '/' . $projectFile->getPath();
                if (!is_dir(dirname($fullPath))) {
                    mkdir(dirname($fullPath), 0777, true);
                }
                $content = $projectFile->binary_content ?? $projectFile->content;
                file_put_contents($fullPath, $content);
                touch($fullPath, $projectFile->updated_at->timestamp);
            } elseif ($projectFile->type === 'folder') {
                $folderPath = $projectPath . '/' . $projectFile->getPath();
                if (!is_dir($folderPath)) {
                    mkdir($folderPath, 0777, true);
                }
            }
        }

        // 5. Als we nog nooit gesynct hebben, moeten we ook zorgen dat lege mappen van projecten bestaan
        if (!$lastSync) {
            foreach (Project::whereIn('id', $projectIds)->cursor() as $project) {
                $projectPath = $workspaceDir . '/' . $project->name;
                if (!is_dir($projectPath)) {
                    mkdir($projectPath, 0777, true);
                }
            }
        }

        // 6. Update de sync timestamp naar NU
        $user->update(['last_synced_at' => now()]);
    }
}
