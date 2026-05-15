<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SetUserRole extends Command
{
    protected $signature = 'user:role {email} {role : student, teacher or admin} {--create : create the user if it does not exist} {--name= : name to use when creating}';

    protected $description = 'Set a user role (student, teacher or admin)';

    public function handle(): int
    {
        $email = $this->argument('email');
        $role = $this->argument('role');

        if (! in_array($role, ['student', 'teacher', 'admin'], true)) {
            $this->error('Role must be "student", "teacher" or "admin".');

            return self::INVALID;
        }

        $user = User::firstWhere('email', $email);

        if (! $user) {
            if (! $this->option('create')) {
                $this->error("No user with email {$email}. Pass --create to create one.");

                return self::FAILURE;
            }

            $user = User::create([
                'email' => $email,
                'name' => $this->option('name') ?: explode('@', $email)[0],
                'role' => $role,
            ]);
            $this->info("Created {$email} as {$role}.");

            return self::SUCCESS;
        }

        $user->update(['role' => $role]);
        $this->info("Updated {$email} to {$role}.");

        return self::SUCCESS;
    }
}
