<?php

namespace Database\Factories;

use App\Models\Node;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Node>
 */
class NodeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'parent_id' => null,
            'type' => 'file',
            'name' => $this->faker->word() . '.txt',
            'content' => $this->faker->paragraph(),
        ];
    }

    public function folder(): static
    {
        return $this->state(['type' => 'folder', 'name' => $this->faker->word(), 'content' => null]);
    }

    public function latex(): static
    {
        return $this->state([
            'type' => 'file',
            'name' => $this->faker->word() . '.tex',
            'content' => "\\documentclass{article}\n\\begin{document}\nHello World\n\\end{document}",
        ]);
    }

    public function markdown(): static
    {
        return $this->state([
            'type' => 'file',
            'name' => $this->faker->word() . '.md',
            'content' => "# Heading\n\nSome content.",
        ]);
    }

    public function rFile(): static
    {
        return $this->state([
            'type' => 'file',
            'name' => $this->faker->word() . '.R',
            'content' => "x <- 1 + 1\nprint(x)",
        ]);
    }
}
