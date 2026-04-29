<?php

namespace Database\Factories;

use App\Models\CompileLog;
use App\Models\Node;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompileLog>
 */
class CompileLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'node_id' => Node::factory()->latex(),
            'user_id' => User::factory(),
            'compiler' => 'pdflatex',
            'status' => 'success',
            'output' => $this->faker->text(200),
            'pdf_path' => null,
        ];
    }

    public function failed(): static
    {
        return $this->state(['status' => 'failed', 'pdf_path' => null]);
    }

    public function pending(): static
    {
        return $this->state(['status' => 'pending']);
    }
}
