<?php

use App\Models\Project;
use App\Models\File;

$project = Project::where('name', '05 geschiedenis typst')->first();
if (!$project) die("Project not found\n");

foreach ($project->files()->where('extension', 'typ')->cursor() as $file) {
    if ($file->name === 'main.typ' || $file->name === 'template.typ') continue;
    $content = $file->content;

    // 1. Unescape everything
    $content = str_replace(['\#', '\"', '\_', '\(', '\)'], ['#', '"', '_', '(', ')'], $content);
    
    // 2. Fix Figure/Image wrapping and paths
    $content = preg_replace_callback('/(?:#figure\(|#box\(width: [^,]+, )?image\("(?:figuren\/)?([^"]+)"\)\)?\s*(<fig:[^>]+>)/', function($matches) use ($project) {
        $imgName = $matches[1];
        $label = $matches[2];
        $imgFile = $project->files()->where('name', 'LIKE', $imgName . '%')->where('type', 'file')->first();
        $path = $imgFile ? '../' . $imgFile->getPath() : '../figuren/' . $imgName;
        return "#figure(image(\"$path\")) $label";
    }, $content);

    // 3. Fix remaining image paths not in figures
    $content = preg_replace_callback('/image\("(?:figuren\/)?([^"]+)"\)/', function($matches) use ($project) {
        $imgName = $matches[1];
        if (str_starts_with($imgName, '../')) return $matches[0];
        $imgFile = $project->files()->where('name', 'LIKE', $imgName . '%')->where('type', 'file')->first();
        $path = $imgFile ? '../' . $imgFile->getPath() : '../figuren/' . $imgName;
        return "image(\"$path\")";
    }, $content);

    // 4. Fix [@] reference brackets
    $content = preg_replace('/(@[a-zA-Z0-9_-]+:[a-zA-Z0-9_-]+)\]/', '$1', $content);
    $content = preg_replace('/(@[a-zA-Z0-9_-]+)\]/', '$1', $content);

    if ($content !== $file->content) {
        $file->content = $content;
        $file->save();
        echo "Final Cleanup: " . $file->name . "\n";
    }
}

echo "DONE!\n";
