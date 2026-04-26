<?php

use App\Models\Project;
use App\Models\File;

$project = Project::where('name', '05 geschiedenis typst')->first();
if (!$project) die("Project not found\n");

foreach ($project->files()->where('extension', 'typ')->cursor() as $file) {
    $content = $file->content;

    // Fix single words in $...$ that are not actually math
    // We match $alphanumeric$ but not if it contains math operators like + - = / ^ _
    $content = preg_replace_callback('/\$([a-zA-Z0-9]{2,})\$/', function($matches) {
        $word = $matches[1];
        // If it's a common math variable like 'x' or 'n', keep it? 
        // But the user said "van" triggered an error.
        return '_' . $word . '_';
    }, $content);

    if ($content !== $file->content) {
        $file->content = $content;
        $file->save();
        echo "Fixed Fake Math: " . $file->name . "\n";
    }
}

echo "Cleanup DONE!\n";
