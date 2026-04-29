<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Share;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Share>
 */
class ShareFactory extends Factory
{
    public function definition(): array
    {
        return [
            'shareable_type' => Project::class,
            'shareable_id' => Project::factory(),
            'user_id' => User::factory(),
            'is_public' => false,
            'permission' => 'read',
        ];
    }

    public function public(): static
    {
        return $this->state(['user_id' => null, 'is_public' => true]);
    }

    public function writable(): static
    {
        return $this->state(['permission' => 'write']);
    }
}
