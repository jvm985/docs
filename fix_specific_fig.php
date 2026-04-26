<?php

use App\Models\Project;
use App\Models\File;

$project = Project::where('name', '05 geschiedenis typst')->first();
$file = $project->files()->where('name', '04_eenmaking_splitsing.typ')->first();

$old = '#figure([#box(width: 100%, image("../figuren/europe_1815_2.png"))
  #box(width: 100%, image("../figuren/europa_1914.png"));],
  caption: [
    Europa in 1914
  ]
)
<fig:europa_1914>';

$new = '#figure(image("../figuren/europe_1815_2.png"), caption: [Europa in 1815]) <fig:europa_1815_2>

#figure(image("../figuren/europa_1914.png"), caption: [Europa in 1914]) <fig:europa_1914>';

$file->content = str_replace($old, $new, $file->content);
$file->save();
echo "Fixed specific figure in 04_eenmaking_splitsing.typ\n";
