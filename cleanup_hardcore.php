<?php

use App\Models\Project;
use App\Models\File;

$project = Project::where('name', '05 geschiedenis typst')->first();
if (!$project) die("Project not found\n");

foreach ($project->files()->where('extension', 'typ')->cursor() as $file) {
    $content = $file->content;

    // Hardcore fix for escaped brackets in my custom blocks
    $content = str_replace('\[', '[', $content);
    $content = str_replace('\]', ']', $content);
    
    // Fix the function name underscore
    $content = str_replace('#kunnen\_en\_kennen', '#kunnen_en_kennen', $content);

    if ($content !== $file->content) {
        $file->content = $content;
        $file->save();
        echo "Fixed Brackets Hardcore: " . $file->name . "\n";
    }
}

echo "Cleanup DONE!\n";
