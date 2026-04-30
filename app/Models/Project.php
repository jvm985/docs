<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['user_id', 'name', 'public_permission'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    protected $casts = [
        'public_permission' => 'string',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('permission')
            ->withTimestamps();
    }

    public function permissionFor(?User $user): ?string
    {
        if ($user && $this->user_id === $user->id) {
            return 'write';
        }

        if ($user) {
            $share = $this->users->firstWhere('id', $user->id)
                ?? $this->users()->where('users.id', $user->id)->first();
            if ($share) {
                return $share->pivot->permission;
            }
        }

        return $this->public_permission;
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
        return $user !== null && $this->user_id === $user->id;
    }

    public function filesPath(string $sub = ''): string
    {
        return rtrim('projects/'.$this->id.'/files/'.ltrim($sub, '/'), '/');
    }

    public function userOutputPath(int $userId, string $sub = ''): string
    {
        return rtrim('projects/'.$this->id.'/users/'.$userId.'/output/'.ltrim($sub, '/'), '/');
    }

    public function userRSessionPath(int $userId, string $sub = ''): string
    {
        return rtrim('projects/'.$this->id.'/users/'.$userId.'/r/'.ltrim($sub, '/'), '/');
    }

    public function ensureDirectories(): void
    {
        $disk = Storage::disk('local');
        foreach (['projects/'.$this->id.'/files'] as $path) {
            if (! $disk->exists($path)) {
                $disk->makeDirectory($path);
            }
        }
    }
}
