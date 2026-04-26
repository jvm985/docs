<?php

use App\Models\Project;
use App\Models\File;

$project = Project::where('name', '05 geschiedenis typst')->first();
if (!$project) die("Project not found\n");

foreach ($project->files()->where('extension', 'typ')->cursor() as $file) {
    $content = $file->content;

    // 1. Fix figure labels
    $content = preg_replace_callback('/image\("([^"]+)"\)\s+(<fig:[^>]+>)/', function($matches) {
        $img = $matches[1];
        $label = $matches[2];
        return "#figure(image(\"$img\")) $label";
    }, $content);
    
    // 2. Fix #link(<label>)[...] -> @label
    $content = preg_replace('/#link\(<([^>]+)>\)\[[^\]]+\]/', '@$1', $content);

    // 3. Fix escaped parents
    $content = str_replace(['\(', '\)'], ['(', ')'], $content);

    // 4. Fix specific Pandoc artifacts for lists in our custom blocks
    $content = str_replace('\item', '-', $content);

    if ($content !== $file->content) {
        $file->content = $content;
        $file->save();
        echo "Final Cleanup: " . $file->name . "\n";
    }
}

echo "Final Cleanup DONE!\n";
