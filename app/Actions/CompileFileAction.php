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
        // 1. Haal alle projecten en hun namen vooraf op (Eager Loading / Lookup map)
        $projects = Project::whereIn('id', function($query) use ($user) {
            $query->select('id')->from('projects')->where('user_id', $user->id)
                ->union(DB::table('project_user')->where('user_id', $user->id)->select('project_id'))
                ->union(DB::table('projects')->where('is_public', true)->select('id'));
        })->get(['id', 'name']);

        $projectNames = $projects->pluck('name', 'id')->toArray();
        $projectIds = $projects->pluck('id')->toArray();

        $lastSync = $user->last_synced_at;

        // 2. Snelle check: is er iéts veranderd in de DB?
        $lastDatabaseChangeStr = DB::table('files')
            ->whereIn('project_id', $projectIds)
            ->max('updated_at');
        
        if ($lastSync && $lastDatabaseChangeStr) {
            $lastDatabaseChange = new \Illuminate\Support\Carbon($lastDatabaseChangeStr);
            if ($lastSync->greaterThanOrEqualTo($lastDatabaseChange)) {
                return;
            }
        }

        // 3. Haal alleen de gewijzigde bestanden op
        $query = File::whereIn('project_id', $projectIds);
        if ($lastSync) {
            $query->where('updated_at', '>', $lastSync);
        }

        // Gebruik chunking of cursor, maar ZONDER relaties te laden
        foreach ($query->cursor() as $projectFile) {
            $projectName = $projectNames[$projectFile->project_id] ?? null;
            if (!$projectName) continue;

            $projectPath = $workspaceDir . '/' . $projectName;
            
            if ($projectFile->type === 'file') {
                $fullPath = $projectPath . '/' . $projectFile->getPath();
                
                // Mappen maken indien nodig
                $dir = dirname($fullPath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true);
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

        // 4. Zorg dat alle project-root mappen bestaan (alleen bij eerste sync)
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
