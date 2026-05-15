<?php

namespace Database\Factories;

use App\Models\SharedDrive;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SharedDrive>
 */
class SharedDriveFactory extends Factory
{
    protected $model = SharedDrive::class;

    public function definition(): array
    {
        return [
            'owner_id' => User::factory()->teacher(),
            'name' => fake()->words(2, true),
        ];
    }
}
