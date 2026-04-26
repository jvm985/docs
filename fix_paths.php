<?php

use App\Models\Project;
use App\Models\File;

$project = Project::where('name', '05 geschiedenis typst')->first();
if (!$project) die("Project not found\n");

foreach ($project->files()->where('extension', 'typ')->cursor() as $file) {
    if ($file->name === 'main.typ' || $file->name === 'template.typ') continue;

    $content = $file->content;

    // Hard fix: image("figuren/name") -> image("../figuren/name.ext")
    $content = preg_replace_callback('/image\("figuren\/([^"]+)"\)/', function($matches) use ($project) {
        $imgName = $matches[1];
        
        $imgFile = $project->files()->where('name', 'LIKE', $imgName . '%')->where('type', 'file')->first();
        if ($imgFile) {
            return 'image("../' . $imgFile->getPath() . '")';
        }
        return 'image("../figuren/' . $imgName . '")';
    }, $content);

    if ($content !== $file->content) {
        $file->content = $content;
        $file->save();
        echo "Fixed Folder Paths: " . $file->name . "\n";
    }
}

echo "Path Cleanup DONE!\n";
