<?php

use App\Models\Project;
use App\Models\File;
use Illuminate\Support\Str;

$sourceProject = Project::where('name', '05_geschiedenis')->first();
if (!$sourceProject) die("Source project not found\n");
$user = $sourceProject->user;

Project::where('name', '05 geschiedenis typst v12')->delete();
$targetProject = Project::create([
    'name' => '05 geschiedenis typst v12',
    'user_id' => $user->id,
    'description' => 'Perfected Typst Version - V12',
]);

$templateContent = '
#let leerstof(title: "", body) = block(
  fill: red.lighten(95%), stroke: (left: 3pt + red), inset: 12pt, width: 100%, breakable: true,
  [ #text(weight: "bold", fill: red.darken(20%), size: 9pt)[#smallcaps("Leerstof: ") #title] #v(4pt) #body ]
)

#let bron(title: "", body) = block(
  fill: blue.lighten(95%), stroke: (left: 3pt + blue), inset: 12pt, width: 100%, breakable: true,
  [ #text(weight: "bold", fill: blue.darken(20%), size: 9pt)[#smallcaps("Bron: ") #title] #v(4pt) #set text(style: "italic") #body ]
)

#let source(title: "", body) = block(
  fill: luma(245), stroke: (left: 3pt + luma(50)), inset: 12pt, width: 100%, breakable: true,
  [ #text(weight: "bold", fill: luma(50), size: 9pt)[#smallcaps("Bron: ") #title] #v(4pt) #body ]
)

#let application(title: "", body) = block(
  fill: red.lighten(95%), stroke: (left: 3pt + red), inset: 12pt, width: 100%, breakable: true,
  [ #text(weight: "bold", fill: red.darken(20%), size: 9pt)[#smallcaps("Toepassing ") #title] #v(4pt) #body ]
)

#let opdracht(body) = block(
  fill: gray.lighten(95%), stroke: 0.5pt + gray, inset: 12pt, radius: 2pt, width: 100%, breakable: true,
  [ #text(weight: "bold", fill: gray.darken(20%), size: 9pt)[#smallcaps("Opdracht")] #v(4pt) #body ]
)

#let answer(height, body) = block(
  width: 100%, height: height, clip: true,
  fill: pattern(size: (100%, 0.25in))[ #place(bottom)[#line(length: 100%, stroke: (dash: "dotted", thickness: 0.5pt, paint: gray.lighten(30%)))] ],
  [ #place(top + left)[#body] ]
)

#let kunnen_en_kennen(je_kan, jargon, begrippen) = block(
  width: 100%, inset: (top: 1em),
  [ == Kunnen en kennen: #grid(columns: (1fr, 1fr), gutter: 2em, [ *Je kan:* #je_kan ], [ *Jargon:* #jargon #v(1em) *Historische begrippen:* #begrippen ]) ]
)

#let tblr(columns: 1, ..args) = table(
  columns: (1fr,) * columns, stroke: 0.5pt + gray, inset: 8pt, align: left + top, ..args
)

#let project(title: "", authors: (), body) = {
  set document(author: authors, title: title)
  set page(paper: "a4", margin: (x: 2.5cm, y: 2.5cm), header: context { if counter(page).get().first() > 1 [ #set text(8pt, style: "italic") #title #h(1fr) Pagina #counter(page).display() ] }, numbering: "1")
  set text(font: "Libertinus Serif", size: 11pt, lang: "nl")
  set heading(numbering: "1.1")
  set par(justify: true, leading: 0.65em)
  show heading: it => block(below: 1em, above: 1.5em)[ #if it.level == 1 { set text(20pt, weight: "bold", fill: navy); it } else { it } ]
  show figure: set block(spacing: 1.5em)
  body
}
';

$targetProject->files()->create(['name' => 'template.typ', 'type' => 'file', 'extension' => 'typ', 'content' => $templateContent]);

function convertToTypst($content) {
    // 1. Answer & Solution mapping (with height)
    $content = preg_replace_callback('/\\\\begin\{(?:answer|solution)\}(?:\[(.*?)\])?(?:\{(.*?)\})?(.*?)\\\\end\{(?:answer|solution)\}/s', function($m) {
        $h = !empty($m[1]) ? $m[1] : (!empty($m[2]) ? $m[2] : "2cm");
        $b = trim($m[3]);
        return "\n#answer($h)[\n$b\n]\n";
    }, $content);

    // 2. TBLR Mapping
    $content = preg_replace_callback('/\\\\begin\{(?:long)?tblr\}(?:\[.*?\])?\{(.*?)\}(.*?)\\\\end\{(?:long)?tblr\}/s', function($m) {
        $spec = $m[1]; $body = $m[2];
        $colCount = preg_match_all('/[XlrcQ]/', $spec, $matches); $cols = $colCount ? $colCount : 2;
        $body = str_replace(['&', '\\\\'], ['], [', '], ['], $body);
        return "\n#tblr(columns: $cols, [\n$body\n])\n";
    }, $content);

    // 3. Custom blocks
    $envs = ['source', 'definition', 'story', 'application', 'leerstof', 'bron', 'opdracht', 'infobox', 'concepts', 'aims'];
    foreach ($envs as $env) {
        $content = preg_replace('/\\\\begin\{' . $env . '\}(?:\[.*?\])?(?:\{(.*?)\})?/', "\n#$env(title: \"$1\")[\n", $content);
        $content = str_replace('\end{' . $env . '}', "\n]\n", $content);
    }

    // 4. Goals
    $content = preg_replace_callback('/\\\\begin\{kunnen_en_kennen\}(.*?)\\\\end\{kunnen_en_kennen\}/s', function($m) {
        $parts = preg_split('/(\\\\jargon|\\\\begrippen)/', $m[1], -1, PREG_SPLIT_DELIM_CAPTURE);
        $jekan = ""; $jargon = ""; $begrippen = ""; $mode = 'jekan';
        foreach ($parts as $p) { if ($p === '\jargon') $mode = 'jargon'; elseif ($p === '\begrippen') $mode = 'begrippen'; else { $$mode .= $p; } }
        return "\n#kunnen_en_kennen([\n$jekan\n], [\n$jargon\n], [\n$begrippen\n])\n";
    }, $content);

    // 5. Pandoc
    $tmpInput = tempnam(sys_get_temp_dir(), 'tex'); file_put_contents($tmpInput, $content);
    $tmpOutput = tempnam(sys_get_temp_dir(), 'typ'); exec("pandoc \"$tmpInput\" -f latex -t typst -o \"$tmpOutput\"");
    $res = file_get_contents($tmpOutput); unlink($tmpInput); unlink($tmpOutput);
    
    // 6. Cleanup
    $res = str_replace(['\#', '\"', '\_', '\(', '\)'], ['#', '"', '_', '(', ')'], $res);
    $res = str_replace(['\[', '\]'], ['[', ']'], $res);
    $res = preg_replace('/([0-9.]*)\\\\linewidth/', '$1' . '100%', $res);
    $res = preg_replace('/([0-9.]*)\\\\textwidth/', '$1' . '100%', $res);
    $res = preg_replace('/(@[a-zA-Z0-9_-]+:[a-zA-Z0-9_-]+)\]/', '$1', $res);
    $res = preg_replace('/(@[a-zA-Z0-9_-]+)\]/', '$1', $res);
    $res = preg_replace('/^\\\\item/m', '- ', $res);
    
    return $res;
}

// Run Migration
$folders = [];
foreach ($sourceProject->files()->where('type', 'folder')->orderBy('id')->get() as $f) {
    $nf = $targetProject->files()->create(['name' => $f->name, 'type' => 'folder', 'parent_id' => $folders[$f->parent_id] ?? null]);
    $folders[$f->id] = $nf->id;
}

foreach ($sourceProject->files()->where('type', 'file')->get() as $f) {
    if ($f->extension === 'tex') {
        if ($f->name === '5_geschiedenis.tex' || Str::contains($f->name, 'config')) continue;
        $nf = $targetProject->files()->create([
            'name' => str_replace('.tex', '.typ', $f->name), 'type' => 'file', 'extension' => 'typ',
            'content' => "#import \"../template.typ\": *\n" . convertToTypst($f->content),
            'parent_id' => $folders[$f->parent_id] ?? null,
        ]);
    } else {
        $targetProject->files()->create([
            'name' => $f->name, 'type' => 'file', 'extension' => $f->extension,
            'content' => $f->content, 'binary_content' => $f->binary_content, 'parent_id' => $folders[$f->parent_id] ?? null,
        ]);
    }
}

$main = "#import \"template.typ\": *\n#show: project.with(title: \"05 Geschiedenis\", authors: (\"" . $user->name . "\",))\n"
      . "#align(center)[#text(24pt, weight: \"bold\")[05 Geschiedenis]\\ #v(1cm) #text(14pt)[Full Course V12]]\n"
      . "#v(2cm)\n= Inhoudstafel\n#outline(depth: 3, indent: 1em)\n#pagebreak()\n";
foreach ($targetProject->files()->where('extension', 'typ')->orderBy('name')->get() as $f) {
    $p = $f->getPath();
    if (str_starts_with($p, 'hoofdstukken/')) $main .= "#include \"$p\"\n";
}
$targetProject->files()->create(['name' => 'main.typ', 'type' => 'file', 'extension' => 'typ', 'content' => $main]);

echo "DONE V12!\n";
