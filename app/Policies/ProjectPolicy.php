<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function view(User $user, Project $project): bool
    {
        if ($project->user_id === $user->id) return true;
        if ($project->is_public) return true;
        
        return $project->sharedUsers()->where('user_id', $user->id)->exists();
    }

    public function update(User $user, Project $project): bool
    {
        if ($project->user_id === $user->id) return true;
        
        if ($project->is_public && $project->public_role === 'editor') return true;

        return $project->sharedUsers()
            ->where('user_id', $user->id)
            ->where('role', 'editor')
            ->exists();
    }

    public function delete(User $user, Project $project): bool
    {
        return $project->user_id === $user->id;
    }

    public function share(User $user, Project $project): bool
    {
        return $project->user_id === $user->id;
    }
}
