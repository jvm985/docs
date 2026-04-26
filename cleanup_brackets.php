<?php

use App\Models\Project;
use App\Models\File;

$project = Project::where('name', '05 geschiedenis typst')->first();
if (!$project) die("Project not found\n");

foreach ($project->files()->where('extension', 'typ')->cursor() as $file) {
    $content = $file->content;

    // Fix Pandoc escaping our deliberate Typst code
    // It likely escaped ] to \] inside the block
    $content = str_replace('\], \[', '], [', $content);
    $content = str_replace('\])', '])', $content);
    
    // Fix lists inside our blocks
    $content = preg_replace('/^\\\\item/m', '- ', $content);

    if ($content !== $file->content) {
        $file->content = $content;
        $file->save();
        echo "Fixed Mismatched Brackets: " . $file->name . "\n";
    }
}

echo "Cleanup DONE!\n";
