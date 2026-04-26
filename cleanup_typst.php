<?php

use App\Models\Project;
use App\Models\File;

$project = Project::where('name', '05 geschiedenis typst')->first();
if (!$project) die("Project not found\n");

foreach ($project->files()->where('extension', 'typ')->cursor() as $file) {
    $content = $file->content;
    $changed = false;

    // 1. Fix \textwidth
    if (str_contains($content, '\textwidth')) {
        $content = str_replace('\textwidth', '100%', $content);
        $changed = true;
    }

    // 2. Fix image calls that don't have paths or extensions
    // Pandoc often outputs image("name")
    // We need to check if the image exists in figuren/
    $content = preg_replace_callback('/image\("([^"]+)"\)/', function($matches) use ($project, $file) {
        $imgName = $matches[1];
        
        // Find the actual file in the project
        $imgFile = $project->files()->where('name', 'LIKE', $imgName . '%')->where('type', 'file')->first();
        
        if ($imgFile) {
            $path = $imgFile->getPath();
            // If the current file is in a folder (like hoofdstukken/), we need ../
            if ($file->parent_id) {
                return 'image("../' . $path . '")';
            }
            return 'image("' . $path . '")';
        }
        
        return $matches[0]; // Keep as is if not found
    }, $content);

    if ($content !== $file->content) {
        $file->content = $content;
        $file->save();
        echo "Fixed: " . $file->name . "\n";
    }
}

echo "Cleanup DONE!\n";
