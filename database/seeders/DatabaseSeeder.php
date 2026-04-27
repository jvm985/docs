<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Project;
use App\Models\File;
use App\Services\WorkspaceManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $manager = new WorkspaceManager();

        $user = User::factory()->create([
            'name' => 'Joachim Van Meirvenne',
            'email' => 'joachim.vanmeirvenne@atheneumkapellen.be',
            'password' => Hash::make('password'),
        ]);

        $project = Project::create([
            'user_id' => $user->id,
            'name' => 'Demo Project',
            'description' => 'Een voorbeeld project met LaTeX en R',
        ]);

        $file = File::create([
            'project_id' => $project->id,
            'name' => 'main.tex',
            'type' => 'file',
            'extension' => 'tex',
        ]);
        $manager->putFile($file, "\\documentclass{article}\n\\begin{document}\nHello LaTeX from Filesystem!\n\\end{document}");

        $rFile = File::create([
            'project_id' => $project->id,
            'name' => 'analysis.r',
            'type' => 'file',
            'extension' => 'r',
        ]);
        $manager->putFile($rFile, "x <- 1:10; plot(x, x^2); print('R is working');");
        
        echo "✅ Database seeded and Filesystem populated!\n";
    }
}
