<?php

namespace App\Services;

use App\Models\File;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WorkspaceManager
{
    protected string $projectsRoot;
    protected string $workspacesRoot;

    public function __construct()
    {
        $this->projectsRoot = storage_path('app/private/projects');
        $this->workspacesRoot = storage_path('app/private/workspaces');

        if (!is_dir($this->projectsRoot)) mkdir($this->projectsRoot, 0777, true);
        if (!is_dir($this->workspacesRoot)) mkdir($this->workspacesRoot, 0777, true);
    }

    /**
     * Zorg dat een gebruiker een complete virtuele workspace heeft
     * met alle projecten waar hij/zij bij mag.
     */
    public function syncUserWorkspace(User $user): void
    {
        $userDir = $this->workspacesRoot . '/u_' . $user->id;
        if (!is_dir($userDir)) mkdir($userDir, 0777, true);

        // Haal alle toegankelijke projecten op
        $projectIds = Project::where('user_id', $user->id)
            ->orWhereIn('id', function($query) use ($user) {
                $query->select('project_id')->from('project_user')->where('user_id', $user->id);
            })
            ->orWhere('is_public', true)
            ->pluck('id');

        $projects = Project::whereIn('id', $projectIds)->get();

        foreach ($projects as $project) {
            $this->linkProjectToWorkspace($project, $user);
        }
    }

    /**
     * Maak een 'Symlink Forest' voor een specifiek project in de user workspace.
     */
    public function linkProjectToWorkspace(Project $project, User $user): void
    {
        $projectSandbox = $this->workspacesRoot . '/u_' . $user->id . '/' . $project->name;
        $masterSource = $this->projectsRoot . '/p_' . $project->id;

        if (!is_dir($projectSandbox)) mkdir($projectSandbox, 0777, true);
        if (!is_dir($masterSource)) mkdir($masterSource, 0777, true);

        // Link alle files recursief
        $this->recursiveSymlink($masterSource, $projectSandbox);
    }

    /**
     * De kern-magie: mappen worden echt aangemaakt, bestanden worden gesymlinkt.
     */
    protected function recursiveSymlink(string $source, string $target): void
    {
        $items = scandir($source);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;

            $sourcePath = $source . '/' . $item;
            $targetPath = $target . '/' . $item;

            if (is_dir($sourcePath)) {
                if (!is_dir($targetPath)) mkdir($targetPath, 0777, true);
                $this->recursiveSymlink($sourcePath, $targetPath);
            } else {
                if (file_exists($targetPath) || is_link($targetPath)) {
                    // Als het een link is, check of hij nog klopt, anders verwijderen
                    if (is_link($targetPath) && readlink($targetPath) === $sourcePath) {
                        continue;
                    }
                    unlink($targetPath);
                }
                symlink($sourcePath, $targetPath);
            }
        }
    }

    /**
     * Schrijf een bestand direct naar de Master Storage.
     */
    public function putFile(File $file, $content): void
    {
        $path = 'p_' . $file->project_id . '/' . $file->getPath();
        Storage::disk('projects')->put($path, $content);
    }

    /**
     * Haal bestand op uit Master Storage.
     */
    public function getFile(File $file): ?string
    {
        $path = 'p_' . $file->project_id . '/' . $file->getPath();
        return Storage::disk('projects')->get($path);
    }
    
    public function deleteFile(File $file): void
    {
        $path = 'p_' . $file->project_id . '/' . $file->getPath();
        Storage::disk('projects')->delete($path);
    }
}
