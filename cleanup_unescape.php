<?php

use App\Models\Project;
use App\Models\File;

$project = Project::where('name', '05 geschiedenis typst')->first();
if (!$project) die("Project not found\n");

foreach ($project->files()->where('extension', 'typ')->cursor() as $file) {
    $content = $file->content;

    // 1. Unescape Typst hashtags and quotes and underscores
    $content = str_replace('\#', '#', $content);
    $content = str_replace('\"', '"', $content);
    $content = str_replace('\_', '_', $content);
    
    // 2. Double check image paths
    $content = preg_replace_callback('/image\("([^"]+)"\)/', function($matches) use ($project, $file) {
        $imgName = $matches[1];
        if (str_starts_with($imgName, '../figuren')) return $matches[0];
        
        $imgFile = $project->files()->where('name', 'LIKE', $imgName . '%')->where('type', 'file')->first();
        if ($imgFile) {
            $path = $imgFile->getPath();
            return $file->parent_id ? 'image("../' . $path . '")' : 'image("' . $path . '")';
        }
        return $matches[0];
    }, $content);

    if ($content !== $file->content) {
        $file->content = $content;
        $file->save();
        echo "Final Cleanup Unescape: " . $file->name . "\n";
    }
}

echo "Cleanup DONE!\n";
