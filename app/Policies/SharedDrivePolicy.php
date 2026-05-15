<?php

namespace App\Policies;

use App\Models\SharedDrive;
use App\Models\User;

class SharedDrivePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SharedDrive $drive): bool
    {
        return $drive->canRead($user);
    }

    public function create(User $user): bool
    {
        return $user->isTeacher();
    }

    public function update(User $user, SharedDrive $drive): bool
    {
        return $drive->isOwnedBy($user);
    }

    public function manageMembers(User $user, SharedDrive $drive): bool
    {
        return $drive->isOwnedBy($user);
    }

    public function delete(User $user, SharedDrive $drive): bool
    {
        return $drive->isOwnedBy($user);
    }

    public function restore(User $user, SharedDrive $drive): bool
    {
        return $drive->isOwnedBy($user);
    }

    public function forceDelete(User $user, SharedDrive $drive): bool
    {
        return $drive->isOwnedBy($user);
    }

    public function createProjectIn(User $user, SharedDrive $drive): bool
    {
        return $drive->canWrite($user);
    }
}
