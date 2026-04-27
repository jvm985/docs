<?php

namespace App\Actions;

use App\Models\File;
use App\Models\User;
use App\Services\Compilers\CompilerFactory;
use App\Services\WorkspaceManager;

class CompileFileAction
{
    public function __construct(protected WorkspaceManager $workspaceManager) {}

    public function execute(File $file)
    {
        $user = auth()->user();
        if (!$user) {
             throw new \Exception("User not authenticated");
        }

        // 1. Zorg dat de Symlink Forest up-to-date is
        $this->workspaceManager->syncUserWorkspace($user);

        // 2. Bepaal de Sandbox map voor DIT specifieke project van DEZE gebruiker
        $workspaceDir = storage_path('app/private/workspaces/u_' . $user->id);
        $projectDir = $workspaceDir . '/' . $file->project->name;
        $fullPathOnDisk = $projectDir . '/' . $file->getPath();

        // 3. Compileer in de Sandbox
        $compiler = CompilerFactory::make($file);
        return $compiler->compile($fullPathOnDisk, $workspaceDir);
    }
}
