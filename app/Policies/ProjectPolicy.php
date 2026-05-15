<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function view(?User $user, Project $project): bool
    {
        return $project->canRead($user);
    }

    public function update(User $user, Project $project): bool
    {
        return $project->canWrite($user);
    }

    public function delete(User $user, Project $project): bool
    {
        if ($project->isOwnedBy($user)) {
            return true;
        }

        if ($project->shared_drive_id) {
            return $project->sharedDrive?->canWrite($user) ?? false;
        }

        return false;
    }

    public function restore(User $user, Project $project): bool
    {
        return $this->delete($user, $project);
    }

    public function forceDelete(User $user, Project $project): bool
    {
        if ($project->isOwnedBy($user)) {
            return true;
        }

        if ($project->shared_drive_id) {
            return $project->sharedDrive?->isOwnedBy($user) ?? false;
        }

        return false;
    }

    public function share(User $user, Project $project): bool
    {
        if ($project->isInSharedDrive()) {
            return false;
        }

        return $project->isOwnedBy($user);
    }

    public function duplicate(User $user, Project $project): bool
    {
        return $project->canRead($user);
    }
}
