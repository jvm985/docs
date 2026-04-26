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

        $this->syncUserWorkspace($user, $workspaceDir);

        $project = $file->project;
        $relativeFilePath = $project->name . '/' . $file->getPath();
        $fullPathOnDisk = $workspaceDir . '/' . $relativeFilePath;

        if (!file_exists($fullPathOnDisk)) {
            throw new \Exception("File not found in workspace after sync: " . $relativeFilePath);
        }

        $compiler = CompilerFactory::make($file);
        return $compiler->compile($fullPathOnDisk, $workspaceDir);
    }

    private function syncUserWorkspace(User $user, string $workspaceDir): void
    {
        // 1. Haal ALLE toegankelijke projecten op (geindexeerd op ID)
        $projects = Project::where('user_id', $user->id)
            ->orWhereIn('id', function($query) use ($user) {
                $query->select('project_id')->from('project_user')->where('user_id', $user->id);
            })
            ->orWhere('is_public', true)
            ->get(['id', 'name']);

        $projectNames = $projects->pluck('name', 'id')->toArray();
        $projectIds = $projects->pluck('id')->toArray();

        $lastSync = $user->last_synced_at;

        // 2. Check de allerlaatste wijziging in de DB (Nu razendsnel door index op updated_at)
        $lastDatabaseChangeStr = DB::table('files')
            ->whereIn('project_id', $projectIds)
            ->max('updated_at');
        
        if ($lastSync && $lastDatabaseChangeStr) {
            $lastDatabaseChange = new \Illuminate\Support\Carbon($lastDatabaseChangeStr);
            // Als de DB niet nieuwer is dan onze laatste sync, stop onmiddellijk!
            if ($lastSync->greaterThanOrEqualTo($lastDatabaseChange)) {
                return;
            }
        }

        // 3. Haal alleen de gewijzigde bestanden op sinds de laatste sync
        $query = File::whereIn('project_id', $projectIds);
        if ($lastSync) {
            $query->where('updated_at', '>', $lastSync);
        }

        // 4. Update de 'dirty' files
        foreach ($query->cursor() as $projectFile) {
            $projectName = $projectNames[$projectFile->project_id] ?? null;
            if (!$projectName) continue;

            $projectPath = $workspaceDir . '/' . $projectName;
            $fullPath = $projectPath . '/' . $projectFile->getPath();
            
            if ($projectFile->type === 'file') {
                if (!is_dir(dirname($fullPath))) {
                    mkdir(dirname($fullPath), 0777, true);
                }
                $content = $projectFile->binary_content ?? $projectFile->content;
                file_put_contents($fullPath, $content);
                touch($fullPath, $projectFile->updated_at->timestamp);
            } elseif ($projectFile->type === 'folder') {
                if (!is_dir($fullPath)) {
                    mkdir($fullPath, 0777, true);
                }
            }
        }

        // 5. Zorg bij de allereerste sync dat alle project-root mappen bestaan
        if (!$lastSync) {
            foreach ($projectNames as $name) {
                $path = $workspaceDir . '/' . $name;
                if (!is_dir($path)) {
                    mkdir($path, 0777, true);
                }
            }
        }

        $user->update(['last_synced_at' => now()]);
    }
}
