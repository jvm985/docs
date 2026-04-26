<?php

use App\Models\Project;
use App\Models\File;

$project = Project::find(261);
if (!$project) die("Project 261 not found\n");

foreach ($project->files()->where('extension', 'typ')->cursor() as $file) {
    $content = $file->content;
    
    // 1. Fix link artifacts
    $content = preg_replace('/#link\(<([^>]+)>\)\[\[[^\]]+\]\]/', '@$1', $content);
    
    // 2. Fix figure wrapping
    $content = preg_replace_callback('/#box\(width: [^,]+, image\("([^"]+)"\)\)\s+(<fig:[^>]+>)/', function($m) {
        return "#figure(image(\"$m[1]\")) $m[2]";
    }, $content);

    if ($content !== $file->content) {
        $file->content = $content;
        $file->save();
        echo "Refined: " . $file->name . "\n";
    }
}

echo "DONE V261!\n";
