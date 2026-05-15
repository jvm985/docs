<?php

namespace App\Models;

use Database\Factories\SharedDriveFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['owner_id', 'name'])]
class SharedDrive extends Model
{
    /** @use HasFactory<SharedDriveFactory> */
    use HasFactory, SoftDeletes;

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'shared_drive_user')
            ->withPivot('permission')
            ->withTimestamps();
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function permissionFor(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        if ($this->owner_id === $user->id) {
            return 'write';
        }

        $membership = $this->members->firstWhere('id', $user->id)
            ?? $this->members()->where('users.id', $user->id)->first();

        return $membership?->pivot->permission;
    }

    public function canRead(?User $user): bool
    {
        return $this->permissionFor($user) !== null;
    }

    public function canWrite(?User $user): bool
    {
        return $this->permissionFor($user) === 'write';
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && $this->owner_id === $user->id;
    }
}
