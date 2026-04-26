<?php

use App\Models\Project;
use App\Models\File;

$project = Project::where('name', '05 geschiedenis typst')->first();
if (!$project) die("Project not found\n");

foreach ($project->files()->where('extension', 'typ')->cursor() as $file) {
    $content = $file->content;

    // 1. Fix: #box(...) <label> -> #figure(image(...)) <label>
    // This is common for images.
    $content = preg_replace_callback('/#box\(width: [^,]+, (image\("[^"]+"\))\)\s+(<fig:[^>]+>)/', function($matches) {
        $imgCall = $matches[1];
        $label = $matches[2];
        return "#figure($imgCall) $label";
    }, $content);
    
    // 2. Also handle plain #image(...) <label> -> #figure(#image(...)) <label>
    $content = preg_replace_callback('/(image\("[^"]+"\))\s+(<fig:[^>]+>)/', function($matches) {
        $imgCall = $matches[1];
        $label = $matches[2];
        // Don't double-wrap
        return "#figure($imgCall) $label";
    }, $content);

    if ($content !== $file->content) {
        $file->content = $content;
        $file->save();
        echo "Fixed Figure Labels: " . $file->name . "\n";
    }
}

echo "Cleanup DONE!\n";
