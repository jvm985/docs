<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'google_id', 'avatar', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function sharedProjects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)
            ->withPivot('permission')
            ->withTimestamps();
    }

    public function ownedSharedDrives(): HasMany
    {
        return $this->hasMany(SharedDrive::class, 'owner_id');
    }

    public function sharedDrives(): BelongsToMany
    {
        return $this->belongsToMany(SharedDrive::class, 'shared_drive_user')
            ->withPivot('permission')
            ->withTimestamps();
    }

    public function isTeacher(): bool
    {
        return in_array($this->role, ['teacher', 'admin'], true);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
