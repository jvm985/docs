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
        return $project->isOwnedBy($user);
    }

    public function share(User $user, Project $project): bool
    {
        return $project->isOwnedBy($user);
    }

    public function duplicate(User $user, Project $project): bool
    {
        return $project->canRead($user);
    }
}
