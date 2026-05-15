<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SetUserRole extends Command
{
    protected $signature = 'user:role {email} {role : student or teacher}';

    protected $description = 'Set a user role (student or teacher)';

    public function handle(): int
    {
        $email = $this->argument('email');
        $role = $this->argument('role');

        if (! in_array($role, ['student', 'teacher'], true)) {
            $this->error('Role must be "student" or "teacher".');

            return self::INVALID;
        }

        $user = User::firstWhere('email', $email);
        if (! $user) {
            $this->error("No user with email {$email}.");

            return self::FAILURE;
        }

        $user->update(['role' => $role]);
        $this->info("Updated {$email} to {$role}.");

        return self::SUCCESS;
    }
}
