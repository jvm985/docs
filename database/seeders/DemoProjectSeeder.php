<?php

namespace Database\Seeders;

use App\Models\File;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoProjectSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'demo@example.com'],
            ['name' => 'Demo User', 'password' => bcrypt('password')]
        );

        $project = Project::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Demo Project'],
            ['description' => 'A project containing various file types for testing.']
        );

        $files = [
            [
                'name' => 'document.tex',
                'type' => 'file',
                'extension' => 'tex',
                'content' => "\\documentclass{article}\n\\begin{document}\nHello LaTeX!\n\\end{document}",
            ],
            [
                'name' => 'document.typ',
                'type' => 'file',
                'extension' => 'typ',
                'content' => "#set page(width: 10cm, height: auto)\n\n= Hello Typst!\nThis is a typst document.",
            ],
            [
                'name' => 'script.R',
                'type' => 'file',
                'extension' => 'R',
                'content' => "print(\"Hello R!\")\nx <- rnorm(10)\nprint(summary(x))",
            ],
            [
                'name' => 'data.json',
                'type' => 'file',
                'extension' => 'json',
                'content' => "{\n  \"message\": \"Hello JSON\"\n}",
            ],
            [
                'name' => 'data.xml',
                'type' => 'file',
                'extension' => 'xml',
                'content' => "<?xml version=\"1.0\"?>\n<message>Hello XML</message>",
            ],
            [
                'name' => 'notes.txt',
                'type' => 'file',
                'extension' => 'txt',
                'content' => "Hello TXT",
            ],
            [
                'name' => 'readme.md',
                'type' => 'file',
                'extension' => 'md',
                'content' => "# Hello Markdown\nThis is a markdown file.",
            ],
            [
                'name' => 'report.rmd',
                'type' => 'file',
                'extension' => 'rmd',
                'content' => "---\ntitle: \"Hello RMarkdown\"\n---\n\n```{r}\nsummary(cars)\n```",
            ],
        ];

        foreach ($files as $fileData) {
            File::firstOrCreate(
                ['project_id' => $project->id, 'name' => $fileData['name']],
                $fileData
            );
        }
    }
}
