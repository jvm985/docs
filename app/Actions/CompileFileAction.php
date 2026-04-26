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

        $compiler = CompilerFactory::make($file);
        return $compiler->compile($fullPathOnDisk, $workspaceDir);
    }

    private function syncUserWorkspace(User $user, string $workspaceDir): void
    {
        $t_start = microtime(true);

        // 1. Get project IDs (indexed query)
        $projectIds = Project::where('user_id', $user->id)
            ->orWhereIn('id', function($query) use ($user) {
                $query->select('project_id')->from('project_user')->where('user_id', $user->id);
            })
            ->orWhere('is_public', true)
            ->pluck('id')
            ->toArray();
        
        $t_projects = microtime(true);

        // 2. Check for ANY change in accessible files or project names
        $accessibleProjects = Project::whereIn('id', $projectIds)->get(['id', 'name']);
        $projectNames = $accessibleProjects->pluck('name', 'id')->toArray();

        $lastFileChange = DB::table('files')
            ->whereIn('project_id', $projectIds)
            ->max('updated_at');
        
        $t_max = microtime(true);
        
        $checksum = md5(implode(',', $projectIds) . implode('|', $projectNames) . $lastFileChange);

        if ($user->projects_checksum === $checksum) {
            $t_end = microtime(true);
            \Log::info(sprintf("Sync [SKIP]: %.4fs (Projects: %.4fs, Max: %.4fs)", 
                $t_end - $t_start, $t_projects - $t_start, $t_max - $t_projects));
            return;
        }

        // 3. Incremental sync logic...
        $query = File::whereIn('project_id', $projectIds);
        if ($user->last_synced_at) {
            $query->where('updated_at', '>', $user->last_synced_at);
        }

        foreach ($query->cursor() as $projectFile) {
            $projectName = $projectNames[$projectFile->project_id] ?? null;
            if (!$projectName) continue;
            $projectPath = $workspaceDir . '/' . $projectName;
            $fullPath = $projectPath . '/' . $projectFile->getPath();
            
            if ($projectFile->type === 'file') {
                if (!is_dir(dirname($fullPath))) mkdir(dirname($fullPath), 0777, true);
                $content = $projectFile->binary_content ?? $projectFile->content;
                file_put_contents($fullPath, $content);
                touch($fullPath, $projectFile->updated_at->timestamp);
            } elseif ($projectFile->type === 'folder') {
                if (!is_dir($fullPath)) mkdir($fullPath, 0777, true);
            }
        }

        // Ensure roots
        foreach ($projectNames as $name) {
            $path = $workspaceDir . '/' . $name;
            if (!is_dir($path)) mkdir($path, 0777, true);
        }

        $user->update([
            'projects_checksum' => $checksum,
            'last_synced_at' => now()
        ]);
        
        $t_end = microtime(true);
        \Log::info(sprintf("Sync [EXEC]: %.4fs", $t_end - $t_start));
    }
}
