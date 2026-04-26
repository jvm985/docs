<?php

use App\Models\Project;
use App\Models\File;

$project = Project::where('name', '05 geschiedenis typst')->first();
if (!$project) die("Project not found\n");

foreach ($project->files()->where('extension', 'typ')->cursor() as $file) {
    $content = $file->content;
    
    // 1. Fix image calls with "figuren/" prefix and missing extensions
    $content = preg_replace_callback('/image\("(?:figuren\/)?([^"]+)"\)/', function($matches) use ($project, $file) {
        $imgName = $matches[1];
        
        // Find actual file
        $imgFile = $project->files()
            ->where('name', 'LIKE', $imgName . '%')
            ->where('type', 'file')
            ->first();
            
        if ($imgFile) {
            $realPath = $imgFile->getPath();
            // If in hoofdstukken/, we need ../
            if ($file->parent_id) {
                return 'image("../' . $realPath . '")';
            }
            return 'image("' . $realPath . '")';
        }
        
        return $matches[0];
    }, $content);

    // 2. Fix #link(<label>)[...] -> @label
    $content = preg_replace('/#link\(<([^>]+)>\)\[[^\]]+\]/', '@$1', $content);

    // 3. Fix escaped parentheses \( \) -> ( )
    $content = str_replace(['\(', '\)'], ['(', ')'], $content);

    if ($content !== $file->content) {
        $file->content = $content;
        $file->save();
        echo "Fixed Advanced: " . $file->name . "\n";
    }
}

echo "Cleanup DONE!\n";
