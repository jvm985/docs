<?php

use App\Models\Project;
use App\Models\File;

$project = Project::where('name', '05 geschiedenis typst')->first();
if (!$project) die("Project not found\n");

foreach ($project->files()->where('extension', 'typ')->cursor() as $file) {
    $content = $file->content;

    // 1. Unescape Typst hashtags
    $content = str_replace('\#', '#', $content);
    
    // 2. Fix image paths again (Pandoc might have stripped them)
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

    // 3. Fix 00% width
    $content = str_replace('width: 00%', 'width: 100%', $content);

    // 4. Fix reference trailing brackets e.g. @label]
    $content = preg_replace('/(@fig:[a-zA-Z0-9_-]+)\]/', '$1', $content);

    if ($content !== $file->content) {
        $file->content = $content;
        $file->save();
        echo "Cleaned up Pandoc Artifacts: " . $file->name . "\n";
    }
}

echo "Cleanup DONE!\n";
