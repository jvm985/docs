<?php

use App\Models\Project;
use App\Models\File;

$project = Project::where('name', '05 geschiedenis typst')->first();
if (!$project) die("Project not found\n");

foreach ($project->files()->where('extension', 'typ')->cursor() as $file) {
    if ($file->name === 'main.typ' || $file->name === 'template.typ') continue;
    
    $content = $file->content;
    
    // Add import if not present
    if (!str_contains($content, 'import "../template.typ"')) {
        $content = '#import "../template.typ": *' . "\n" . $content;
        $file->content = $content;
        $file->save();
        echo "Added Import: " . $file->name . "\n";
    }
}

echo "Import Cleanup DONE!\n";
