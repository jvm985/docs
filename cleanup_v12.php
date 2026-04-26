<?php

use App\Models\Project;
use App\Models\File;

$project = Project::where('name', '05 geschiedenis typst v12')->first();
if (!$project) die("Project not found\n");

foreach ($project->files()->where('extension', 'typ')->cursor() as $file) {
    if ($file->name === 'main.typ' || $file->name === 'template.typ') continue;
    $content = $file->content;

    // 1. Unescape Typst hashtags and quotes and underscores
    $content = str_replace(['\#', '\"', '\_', '\(', '\)'], ['#', '"', '_', '(', ')'], $content);
    $content = str_replace(['\[', '\]'], ['[', ']'], $content);

    // 2. Fix Figure/Image wrapping and paths
    $content = preg_replace_callback('/(?:#figure\(|#box\(width: [^,]+, )?image\("(?:figuren\/)?([^\\\"\\)]+)"\)\)?\s*(<fig:[^>]+>)?/', function($matches) use ($project) {
        $imgName = $matches[1];
        $label = isset($matches[2]) ? $matches[2] : '';
        $imgFile = $project->files()->where('name', 'LIKE', $imgName . '%')->where('type', 'file')->first();
        $path = $imgFile ? '../' . $imgFile->getPath() : '../figuren/' . $imgName;
        if ($label) return "#figure(image(\"$path\")) $label";
        return "image(\"$path\")";
    }, $content);

    // 3. Fix [@] reference brackets and function syntax
    $content = preg_replace('/(@[a-zA-Z0-9_-]+:[a-zA-Z0-9_-]+)\]/', '$1', $content);
    $content = preg_replace('/(@[a-zA-Z0-9_-]+)\]/', '$1', $content);
    $content = str_replace('width: 00%', 'width: 100%', $content);
    $content = preg_replace('/#answer\\[(.*?)\\]/', '#answer($1)[]', $content);
    $content = preg_replace('/^\\\\item/m', '- ', $content);

    if ($content !== $file->content) {
        $file->content = $content;
        $file->save();
        echo "Cleaned up: " . $file->name . "\n";
    }
}

echo "Cleanup DONE!\n";
