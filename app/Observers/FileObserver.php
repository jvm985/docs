<?php

namespace App\Observers;

use App\Models\File;
use App\Services\WorkspaceManager;

class FileObserver
{
    public function __construct(protected WorkspaceManager $workspaceManager) {}

    /**
     * Zodra een file record wordt aangemaakt in de DB (bv metadata), 
     * hoeven we hier nog niets te doen, want de Controller zal putFile aanroepen
     * voor de eerste content.
     */
    public function deleted(File $file): void
    {
        if ($file->type === 'file') {
            $this->workspaceManager->deleteFile($file);
        }
    }
}
