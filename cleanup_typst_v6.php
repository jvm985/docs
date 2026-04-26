<?php

use App\Models\Project;
use App\Models\File;

$project = Project::where('name', '05 geschiedenis typst')->first();
if (!$project) die("Project not found\n");

foreach ($project->files()->where('extension', 'typ')->cursor() as $file) {
    $content = $file->content;

    // Fix $...$ blocks that contain spaces or multiple words (clearly not math)
    $content = preg_replace_callback('/\$([^$]+)\$/', function($matches) {
        $inner = $matches[1];
        // If it contains spaces, it's likely text
        if (str_contains($inner, ' ') || strlen($inner) > 10) {
            return '_' . $inner . '_';
        }
        return $matches[0];
    }, $content);

    if ($content !== $file->content) {
        $file->content = $content;
        $file->save();
        echo "Fixed Multi-word Math: " . $file->name . "\n";
    }
}

echo "Cleanup DONE!\n";
