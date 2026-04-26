<?php

use App\Models\Project;
use App\Models\File;

$project = Project::where('name', '05 geschiedenis typst')->first();
if (!$project) die("Project not found\n");

foreach ($project->files()->where('extension', 'typ')->cursor() as $file) {
    $content = $file->content;

    // 1. Fix lengths: \linewidth, \textwidth
    // Handle cases like 0.9\linewidth -> 90%
    $content = preg_replace_callback('/([0-9.]*)\\\\linewidth/', function($matches) {
        $multiplier = $matches[1] === '' ? 1 : floatval($matches[1]);
        return ($multiplier * 100) . '%';
    }, $content);
    
    $content = preg_replace_callback('/([0-9.]*)\\\\textwidth/', function($matches) {
        $multiplier = $matches[1] === '' ? 1 : floatval($matches[1]);
        return ($multiplier * 100) . '%';
    }, $content);

    // 2. Remove any remaining single \ that Pandoc might have left in code blocks
    // But be careful not to break legitimate escaping. 
    // Usually these are at the start of a word in a #...[...] block.
    
    if ($content !== $file->content) {
        $file->content = $content;
        $file->save();
        echo "Fixed Lengths: " . $file->name . "\n";
    }
}

echo "Cleanup DONE!\n";
