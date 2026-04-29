<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function view(User $user, Project $project): bool
    {
        return $user->id === $project->user_id || $project->isSharedWith($user);
    }

    public function update(User $user, Project $project): bool
    {
        if ($user->id === $project->user_id) {
            return true;
        }

        return $project->shares()
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)->orWhere('is_public', true);
            })
            ->where('permission', 'write')
            ->exists();
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->id === $project->user_id;
    }
}
