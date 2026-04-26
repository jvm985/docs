<?php

use App\Models\Project;
use App\Models\File;
use Illuminate\Support\Str;

// Find source project
$sourceProject = Project::where('name', '05_geschiedenis')->first();
if (!$sourceProject) {
    die("Source project not found\n");
}

$user = $sourceProject->user;

// Create new Typst project
$targetProject = Project::create([
    'name' => '05 geschiedenis typst',
    'user_id' => $user->id,
    'description' => 'Clean Typst version of history course 05',
]);

echo "Created project: " . $targetProject->id . "\n";

// Helper to convert LaTeX content to Typst
function convertToTypst($content) {
    // Basic pre-processing for custom envs
    $content = preg_replace('/\\\\begin\{leerstof\}\{(.*?)\}/', "\n#leerstof(title: \"$1\")[\n", $content);
    $content = preg_replace('/\\\\end\{leerstof\}/', "\n]\n", $content);
    
    $content = preg_replace('/\\\\begin\{bron\}\{(.*?)\}/', "\n#bron(title: \"$1\")[\n", $content);
    $content = preg_replace('/\\\\end\{bron\}/', "\n]\n", $content);
    
    $content = preg_replace('/\\\\begin\{opdracht\}/', "\n#opdracht()[\n", $content);
    $content = preg_replace('/\\\\end\{opdracht\}/', "\n]\n", $content);

    // Call Pandoc
    $tmpInput = tempnam(sys_get_temp_dir(), 'tex');
    file_put_contents($tmpInput, $content);
    
    $tmpOutput = tempnam(sys_get_temp_dir(), 'typ');
    exec("pandoc \"$tmpInput\" -f latex -t typst -o \"$tmpOutput\"");
    
    $typstContent = file_get_contents($tmpOutput);
    
    unlink($tmpInput);
    unlink($tmpOutput);
    
    // Cleanup: Remove some common artifacts
    $typstContent = str_replace('\[', '$', $typstContent);
    $typstContent = str_replace('\]', '$', $typstContent);
    
    return $typstContent;
}

// Corrected template with actual logic
$templateContent = '
#let leerstof(title: "", body) = block(
  fill: red.lighten(95%),
  stroke: 0.5pt + red,
  inset: 12pt,
  radius: 2pt,
  width: 100%,
  breakable: true,
  [
    #text(weight: "bold", fill: red.darken(20%), size: 9pt)[#smallcaps("Leerstof: ") #title]
    #v(4pt)
    #body
  ]
)

#let bron(title: "", body) = block(
  fill: blue.lighten(95%),
  stroke: 0.5pt + blue,
  inset: 12pt,
  radius: 2pt,
  width: 100%,
  breakable: true,
  [
    #text(weight: "bold", fill: blue.darken(20%), size: 9pt)[#smallcaps("Bron: ") #title]
    #v(4pt)
    #set text(style: "italic")
    #body
  ]
)

#let opdracht(body) = block(
  fill: gray.lighten(95%),
  stroke: 0.5pt + gray,
  inset: 12pt,
  radius: 2pt,
  width: 100%,
  breakable: true,
  [
    #text(weight: "bold", fill: gray.darken(20%), size: 9pt)[#smallcaps("Opdracht")]
    #v(4pt)
    #body
  ]
)

#let project(title: "", authors: (), body) = {
  set document(author: authors, title: title)
  set page(
    paper: "a4",
    margin: (x: 2.5cm, y: 2.5cm),
    numbering: "1",
  )
  set text(font: "Libertinus Serif", size: 11pt, lang: "nl")
  set heading(numbering: "1.1")
  
  show heading: it => block(below: 1em, above: 1.5em)[
    #if it.level == 1 {
      set text(20pt, weight: "bold", fill: navy)
      it
    } else {
      it
    }
  ]

  body
}
';

$targetProject->files()->create([
    'name' => 'template.typ',
    'type' => 'file',
    'extension' => 'typ',
    'content' => $templateContent,
]);

// Main Typst file
$mainTypContent = '#import "template.typ": project, leerstof, bron, opdracht
#show: project.with(
  title: "05 Geschiedenis",
  authors: ("' . $user->name . '",),
)

#align(center)[
  #text(24pt, weight: "bold")[05 Geschiedenis]
  #v(1cm)
  #text(14pt)[Cursusoverzicht]
]

#v(2cm)

= Inhoudstafel
#outline(depth: 3, indent: true)

#pagebreak()

';

// Folders
$folders = [];

foreach ($sourceProject->files()->where('type', 'folder')->orderBy('id')->get() as $file) {
    $newFolder = $targetProject->files()->create([
        'name' => $file->name,
        'type' => 'folder',
        'parent_id' => isset($folders[$file->parent_id]) ? $folders[$file->parent_id] : null,
    ]);
    $folders[$file->id] = $newFolder->id;
}

// Files
foreach ($sourceProject->files()->where('type', 'file')->get() as $file) {
    $parent_id = isset($folders[$file->parent_id]) ? $folders[$file->parent_id] : null;
    
    if ($file->extension === 'tex') {
        if ($file->name === '5_geschiedenis.tex' || Str::contains($file->name, 'config')) {
            continue;
        }
        
        echo "Converting: " . $file->name . "\n";
        $converted = convertToTypst($file->content);
        $newName = str_replace('.tex', '.typ', $file->name);
        
        $targetProject->files()->create([
            'name' => $newName,
            'type' => 'file',
            'extension' => 'typ',
            'content' => $converted,
            'parent_id' => $parent_id,
        ]);
        
        // Add include to main if it's in chapters
        if ($parent_id && $folders[$file->parent_id] && Project::find($targetProject->id)->files()->find($parent_id)->name === 'hoofdstukken') {
             $mainTypContent .= "#include \"hoofdstukken/$newName\"\n";
        }
        
    } else {
        // Copy binary
        $targetProject->files()->create([
            'name' => $file->name,
            'type' => 'file',
            'extension' => $file->extension,
            'content' => $file->content,
            'binary_content' => $file->binary_content,
            'parent_id' => $parent_id,
        ]);
    }
}

// Save main file
$targetProject->files()->create([
    'name' => 'main.typ',
    'type' => 'file',
    'extension' => 'typ',
    'content' => $mainTypContent,
]);

echo "DONE!\n";
