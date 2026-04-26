<?php

use App\Models\Project;
use App\Models\File;

$project = Project::where('name', '05 geschiedenis typst')->first();
$file = $project->files()->where('name', '06_belgische_revolutie.typ')->first();

$old = '#box(width: 100%, image("../figuren/jan_van_speijk.jpg")) <fig:speijk>';
$new = '#figure(image("../figuren/jan_van_speijk.jpg"), caption: [Jan van Speijk]) <fig:speijk>';

$file->content = str_replace($old, $new, $file->content);
$file->save();
echo "Fixed specific figure in 06_belgische_revolutie.typ\n";
