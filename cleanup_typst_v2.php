<?php

use App\Models\Project;
use App\Models\File;

$project = Project::where('name', '05 geschiedenis typst')->first();
if (!$project) die("Project not found\n");

foreach ($project->files()->where('extension', 'typ')->cursor() as $file) {
    $content = $file->content;
    $changed = false;

    // 3. Fix Pandoc link/ref artifacts: #link(<label>)[$label$] -> @label
    $content = preg_replace('/#link\(<([^>]+)>\)\[\$[^$]+\$\]/', '@$1', $content);
    
    // 4. Fix plain math refs: $fig:something$ -> @fig:something
    // But be careful not to break real math. Usually fig: or sec: are refs.
    $content = preg_replace('/\$((?:fig|sec|tab):[^$]+)\$/', '@$1', $content);

    if ($content !== $file->content) {
        $file->content = $content;
        $file->save();
        echo "Fixed Refs: " . $file->name . "\n";
    }
}

echo "Cleanup DONE!\n";
