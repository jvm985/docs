<?php

use App\Models\Project;
use App\Models\File;
use Illuminate\Support\Str;

// Find source project
$sourceProject = Project::where('name', '05_geschiedenis')->first();
if (!$sourceProject) die("Source project not found\n");

$user = $sourceProject->user;

// Create new Typst project (delete old one first)
Project::where('name', '05 geschiedenis typst')->delete();

$targetProject = Project::create([
    'name' => '05 geschiedenis typst',
    'user_id' => $user->id,
    'description' => 'Final Typst version - Corrected Template - V7',
]);

echo "Created project: " . $targetProject->id . "\n";

// 1. Create Template File
$templateContent = '
#let leerstof(title: "", body) = block(
  fill: red.lighten(95%),
  stroke: (left: 3pt + red),
  inset: 12pt,
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
  stroke: (left: 3pt + blue),
  inset: 12pt,
  width: 100%,
  breakable: true,
  [
    #text(weight: "bold", fill: blue.darken(20%), size: 9pt)[#smallcaps("Bron: ") #title]
    #v(4pt)
    #set text(style: "italic")
    #body
  ]
)

#let source(title: "", body) = block(
  fill: luma(245),
  stroke: (left: 3pt + luma(50)),
  inset: 12pt,
  width: 100%,
  breakable: true,
  [
    #text(weight: "bold", fill: luma(50), size: 9pt)[#smallcaps("Bron: ") #title]
    #v(4pt)
    #body
  ]
)

#let application(title: "", body) = block(
  fill: red.lighten(95%),
  stroke: (left: 3pt + red),
  inset: 12pt,
  width: 100%,
  breakable: true,
  [
    #text(weight: "bold", fill: red.darken(20%), size: 9pt)[#smallcaps("Toepassing ") #title]
    #v(4pt)
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

#let answer(height, body) = block(
  width: 100%,
  height: height,
  clip: true,
  [
    #set line(stroke: (dash: "dotted", thickness: 0.5pt, paint: gray.lighten(30%)))
    #place(repeat[#v(0.8cm) #line(length: 100%)])
    #body
  ]
)

#let kunnen_en_kennen(je_kan, jargon, begrippen) = block(
  width: 100%,
  inset: (top: 1em),
  [
    == Kunnen en kennen:
    #grid(
      columns: (1fr, 1fr),
      gutter: 2em,
      [
        *Je kan:*
        #je_kan
      ],
      [
        *Jargon:*
        #jargon
        #v(1em)
        *Historische begrippen:*
        #begrippen
      ]
    )
  ]
)

#let tblr(..args) = table(
  stroke: 0.5pt + gray,
  inset: 8pt,
  align: left + top,
  ..args
)

#let project(title: "", authors: (), body) = {
  set document(author: authors, title: title)
  set page(
    paper: "a4",
    margin: (x: 2.5cm, y: 2.5cm),
    header: context {
      if counter(page).get().first() > 1 [
        #set text(8pt, style: "italic")
        #title 
        #h(1fr) 
        Pagina #counter(page).display()
      ]
    },
    numbering: "1",
  )
  
  set text(font: "Libertinus Serif", size: 11pt, lang: "nl")
  set heading(numbering: "1.1")
  set par(justify: true, leading: 0.65em)
  
  show heading: it => block(below: 1em, above: 1.5em)[
    #if it.level == 1 {
      set text(20pt, weight: "bold", fill: navy)
      it
    } else {
      it
    }
  ]

  show figure: set block(spacing: 1.5em)

  body
}
';

$targetProject->files()->create([
    'name' => 'template.typ',
    'type' => 'file',
    'extension' => 'typ',
    'content' => $templateContent,
]);

// Helper to convert LaTeX content to Typst
function convertToTypst($content) {
    // 1. Handle answer/solution correctly (wrap content)
    $content = preg_replace_callback('/\\\\begin\{answer\}(?:\[(.*?)\])?(.*?)\\\\end\{answer\}/s', function($matches) {
        $height = !empty($matches[1]) ? $matches[1] : "2cm";
        $body = trim($matches[2]);
        if ($body === $height) $body = "";
        return "\n#answer($height)[\n$body\n]\n";
    }, $content);
    
    $content = preg_replace_callback('/\\\\begin\{solution\}(?:\{(.*?)\})?(.*?)\\\\end\{solution\}/s', function($matches) {
        $height = !empty($matches[1]) ? $matches[1] : "2cm";
        $body = trim($matches[2]);
        if ($body === $height) $body = "";
        return "\n#answer($height)[\n$body\n]\n";
    }, $content);

    // 2. Handle tblr / longtblr / tabularx
    $content = preg_replace('/\\\\begin\{(?:long)?tblr\}(?:\[.*?\])?\{(.*?)\}/s', '\begin{tabular}', $content);
    $content = preg_replace('/\\\\end\{(?:long)?tblr\}/s', '\end{tabular}', $content);
    $content = preg_replace('/\\\\begin\{tabularx\}\{.*?\}(?:\[.*?\])?\{(.*?)\}/s', '\begin{tabular}', $content);
    $content = preg_replace('/\\\\end\{tabularx\}/s', '\end{tabular}', $content);

    // 3. Handle kunnen_en_kennen
    $content = preg_replace_callback('/\\\\begin\{kunnen_en_kennen\}(.*?)\\\\end\{kunnen_en_kennen\}/s', function($matches) {
        $inner = $matches[1];
        $jekan = ""; $jargon = ""; $begrippen = "";
        $parts = preg_split('/(\\\\jargon|\\\\begrippen)/', $inner, -1, PREG_SPLIT_DELIM_CAPTURE);
        $currentMode = 'jekan';
        foreach ($parts as $part) {
            if ($part === '\jargon') $currentMode = 'jargon';
            elseif ($part === '\begrippen') $currentMode = 'begrippen';
            else {
                if ($currentMode === 'jekan') $jekan .= $part;
                if ($currentMode === 'jargon') $jargon .= $part;
                if ($currentMode === 'begrippen') $begrippen .= $part;
            }
        }
        return "\n#kunnen_en_kennen([\n$jekan\n], [\n$jargon\n], [\n$begrippen\n])\n";
    }, $content);

    // 4. Handle source and application
    $content = preg_replace_callback('/\\\\begin\{source\}(?:\[.*?\])?\{(.*?)\}(.*?)\\\\end\{source\}/s', function($matches) {
        return "\n#source(title: \"$matches[1]\")[\n$matches[2]\n]\n";
    }, $content);
    
    $content = preg_replace_callback('/\\\\begin\{application\}(.*?)\\\\end\{application\}/s', function($matches) {
        return "\n#application()[\n$matches[1]\n]\n";
    }, $content);

    // 5. Basic blocks
    $content = preg_replace('/\\\\begin\{leerstof\}\{(.*?)\}/', "\n#leerstof(title: \"$1\")[\n", $content);
    $content = preg_replace('/\\\\end\{leerstof\}/', "\n]\n", $content);
    $content = preg_replace('/\\\\begin\{bron\}\{(.*?)\}/', "\n#bron(title: \"$1\")[\n", $content);
    $content = preg_replace('/\\\\end\{bron\}/', "\n]\n", $content);
    $content = preg_replace('/\\\\begin\{opdracht\}/', "\n#opdracht()[\n", $content);
    $content = preg_replace('/\\\\end\{opdracht\}/', "\n]\n", $content);

    // 6. Call Pandoc
    $tmpInput = tempnam(sys_get_temp_dir(), 'tex');
    file_put_contents($tmpInput, $content);
    $tmpOutput = tempnam(sys_get_temp_dir(), 'typ');
    exec("pandoc \"$tmpInput\" -f latex -t typst -o \"$tmpOutput\"");
    $typstContent = file_get_contents($tmpOutput);
    unlink($tmpInput); unlink($tmpOutput);
    
    // 7. Final Typst Cleanups
    $typstContent = str_replace(['\(', '\)'], ['(', ')'], $typstContent);
    $typstContent = str_replace('\#', '#', $typstContent);
    $typstContent = str_replace('\"', '"', $typstContent);
    $typstContent = str_replace('\_', '_', $typstContent);
    $typstContent = preg_replace('/([0-9.]*)\\\\linewidth/', '$1' . '100%', $typstContent);
    $typstContent = preg_replace('/([0-9.]*)\\\\textwidth/', '$1' . '100%', $typstContent);
    $typstContent = str_replace(['\[', '\]'], ['[', ']'], $typstContent);
    
    $typstContent = preg_replace('/^\\\\item/m', '- ', $typstContent);
    $typstContent = preg_replace('/(@[a-zA-Z0-9_-]+:[a-zA-Z0-9_-]+)\]/', '$1', $typstContent);
    $typstContent = preg_replace('/(@[a-zA-Z0-9_-]+)\]/', '$1', $typstContent);
    
    return $typstContent;
}

// Folders
$folders = [];
foreach ($sourceProject->files()->where('type', 'folder')->orderBy('id')->get() as $file) {
    $newFolder = $targetProject->files()->create([
        'name' => $file->name, 'type' => 'folder',
        'parent_id' => isset($folders[$file->parent_id]) ? $folders[$file->parent_id] : null,
    ]);
    $folders[$file->id] = $newFolder->id;
}

// Files
foreach ($sourceProject->files()->where('type', 'file')->get() as $file) {
    $parent_id = isset($folders[$file->parent_id]) ? $folders[$file->parent_id] : null;
    if ($file->extension === 'tex') {
        if ($file->name === '5_geschiedenis.tex' || Str::contains($file->name, 'config')) continue;
        echo "Converting: " . $file->name . "\n";
        $converted = convertToTypst($file->content);
        $newName = str_replace('.tex', '.typ', $file->name);
        $targetProject->files()->create([
            'name' => $newName, 'type' => 'file', 'extension' => 'typ',
            'content' => "#import \"../template.typ\": *\n" . $converted, 'parent_id' => $parent_id,
        ]);
    } else {
        $targetProject->files()->create([
            'name' => $file->name, 'type' => 'file', 'extension' => $file->extension,
            'content' => $file->content, 'binary_content' => $file->binary_content, 'parent_id' => $parent_id,
        ]);
    }
}

// Main Typst
$mainTypContent = '#import "template.typ": *
#show: project.with(title: "05 Geschiedenis", authors: ("' . $user->name . '",))
#align(center)[#text(24pt, weight: "bold")[05 Geschiedenis]\ #v(1cm) #text(14pt)[Volledige Cursus]]
#v(2cm)
= Inhoudstafel
#outline(depth: 3, indent: 1em)
#pagebreak()
';

foreach ($targetProject->files()->where('extension', 'typ')->get() as $f) {
    if ($f->name !== 'main.typ' && $f->name !== 'template.typ') {
        $path = $f->getPath();
        if (str_starts_with($path, 'hoofdstukken/')) {
            $mainTypContent .= "#include \"$path\"\n";
        }
    }
}

$targetProject->files()->create(['name' => 'main.typ', 'type' => 'file', 'extension' => 'typ', 'content' => $mainTypContent]);

echo "DONE V7!\n";
