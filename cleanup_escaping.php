<?php

use App\Models\Project;
use App\Models\File;

$project = Project::where('name', '05 geschiedenis typst')->first();
if (!$project) die("Project not found\n");

foreach ($project->files()->where('extension', 'typ')->cursor() as $file) {
    $content = $file->content;

    // The issue is that Pandoc sees my #kunnen_en_kennen([...], [...], [...]) call 
    // and ESCAPES the brackets because it thinks they are LaTeX.
    
    // Convert back escaped brackets specifically for my function calls
    $content = str_replace('\], \[', '], [', $content);
    $content = str_replace('\])', '])', $content);
    $content = str_replace('#kunnen\_en\_kennen', '#kunnen_en_kennen', $content);
    
    // Also check for leading escaped brackets
    $content = str_replace("\n\], [", "\n], [", $content);
    $content = str_replace("\n\])", "\n])", $content);

    if ($content !== $file->content) {
        $file->content = $content;
        $file->save();
        echo "Fixed Escaping: " . $file->name . "\n";
    }
}

echo "Cleanup DONE!\n";
