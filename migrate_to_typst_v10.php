<?php

use App\Models\Project;
use App\Models\File;
use Illuminate\Support\Str;

$sourceProject = Project::where('name', '05_geschiedenis')->first();
if (!$sourceProject) die("Source project not found\n");
$user = $sourceProject->user;

Project::where('name', '05 geschiedenis typst')->delete();
$targetProject = Project::create([
    'name' => '05 geschiedenis typst',
    'user_id' => $user->id,
    'description' => 'Perfected Typst Version - V10 (All Environments)',
]);

$templateContent = '
// --- COLORS ---
#let col-source = gray.darken(50%)
#let col-definition = blue.darken(20%)
#let col-application = red.darken(20%)
#let col-goal = olive.darken(20%)

// --- BOXES ---
#let base-box(title: "", color: black, body) = block(
  fill: color.lighten(95%),
  stroke: (left: 3pt + color),
  inset: 12pt,
  width: 100%,
  breakable: true,
  [
    #if title != "" {
       text(weight: "bold", fill: color, size: 9pt)[#smallcaps(title)]
       v(4pt)
    }
    #body
  ]
)

#let leerstof(title: "", body) = base-box(title: "Leerstof: " + title, color: red, body)
#let bron(title: "", body) = base-box(title: "Bron: " + title, color: blue, body)
#let source(title: "", body) = base-box(title: "Bron: " + title, color: gray, body)
#let application(title: "", body) = base-box(title: "Toepassing: " + title, color: red, body)
#let definition(title: "", body) = base-box(title: "Definitie: " + title, color: blue, body)
#let story(title: "", body) = base-box(title: title, color: blue, body)
#let opdracht(body) = base-box(title: "Opdracht", color: gray, body)
#let infobox(title: "", body) = block(fill: gray.lighten(90%), stroke: 0.5pt + gray, inset: 10pt, radius: 4pt, width: 100%)[*#title* \ #body]

// --- SPECIAL ENVIRONMENTS ---
#let answer(height, body) = block(
  width: 100%, height: height, clip: true,
  [ 
    #set line(stroke: (dash: "dotted", thickness: 0.5pt, paint: gray.lighten(30%))) 
    #repeat[#line(length: 100%) #v(0.7cm)] 
    #place(top + left)[#body] 
  ]
)

#let kunnen_en_kennen(je_kan, jargon, begrippen) = block(
  width: 100%, inset: (top: 1em),
  [ 
    == Kunnen en kennen: 
    #grid(columns: (1fr, 1fr), gutter: 2em, [ *Je kan:* #je_kan ], [ *Jargon:* #jargon #v(1em) *Historische begrippen:* #begrippen ]) 
  ]
)

#let concepts(body) = table(
  columns: (4cm, 1fr), stroke: 0.5pt + gray, fill: (x, y) => if y == 0 { olive.lighten(80%) },
  [*Historisch Begrip*], [*Omschrijving*],
  ..body
)

#let aims(body) = table(
  columns: (1fr), stroke: 0.5pt + gray, fill: olive.lighten(80%),
  [*Na het lezen van dit hoofdstuk kun je:*],
  body
)

#let tblr(columns: 1, ..args) = table(
  columns: (1fr,) * columns, stroke: 0.5pt + gray, inset: 8pt, align: left + top, ..args
)

// --- PROJECT SETUP ---
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
    // 1. Map all known environments
    $content = preg_replace_callback('/\\\\begin\{answer\}(?:\[(.*?)\])?(.*?)\\\\end\{answer\}/s', function($m) {
        $h = !empty($m[1]) ? $m[1] : "2cm"; $b = trim($m[2]); if ($b === $h) $b = "";
        return "\n#answer($h)[\n$b\n]\n";
    }, $content);

    $content = preg_replace_callback('/\\\\begin\{kunnen_en_kennen\}(.*?)\\\\end\{kunnen_en_kennen\}/s', function($m) {
        $parts = preg_split('/(\\\\jargon|\\\\begrippen)/', $m[1], -1, PREG_SPLIT_DELIM_CAPTURE);
        $jekan = ""; $jargon = ""; $begrippen = ""; $mode = 'jekan';
        foreach ($parts as $p) { if ($p === '\jargon') $mode = 'jargon'; elseif ($p === '\begrippen') $mode = 'begrippen'; else { $$mode .= $p; } }
        return "\n#kunnen_en_kennen([\n$jekan\n], [\n$jargon\n], [\n$begrippen\n])\n";
    }, $content);

    // Simple mappings for custom boxes
    $envs = ['source', 'definition', 'story', 'application', 'leerstof', 'bron', 'opdracht', 'infobox', 'concepts', 'aims'];
    foreach ($envs as $env) {
        $content = preg_replace('/\\\\begin\{' . $env . '\}(?:\[.*?\])?(?:\{(.*?)\})?/', "\n#$env(title: \"$1\")[\n", $content);
        $content = str_replace('\end{' . $env . '}', "\n]\n", $content);
    }

    // 2. Map custom commands
    $content = str_replace('\Bronanalyse', "\n#opdracht()[*Bronanalyse:* #answer(6cm)[] ]\n", $content);
    $content = preg_replace('/\\\\HistorischBegrip\{(.*?)\}/', "\n#leerstof(title: \"Historisch Begrip: $1\")[\n#answer(8cm)[]\n]\n", $content);

    // 3. Pandoc Pass
    $tmpInput = tempnam(sys_get_temp_dir(), 'tex'); file_put_contents($tmpInput, $content);
    $tmpOutput = tempnam(sys_get_temp_dir(), 'typ'); exec("pandoc \"$tmpInput\" -f latex -t typst -o \"$tmpOutput\"");
    $res = file_get_contents($tmpOutput); unlink($tmpInput); unlink($tmpOutput);
    
    // 4. Cleanup
    $res = str_replace(['\#', '\"', '\_', '\(', '\)'], ['#', '"', '_', '(', ')'], $res);
    $res = preg_replace('/([0-9.]*)\\\\linewidth/', '$1' . '100%', $res);
    $res = preg_replace('/([0-9.]*)\\\\textwidth/', '$1' . '100%', $res);
    $res = preg_replace('/(@[a-zA-Z0-9_-]+:[a-zA-Z0-9_-]+)\]/', '$1', $res);
    $res = preg_replace('/(@[a-zA-Z0-9_-]+)\]/', '$1', $res);
    $res = preg_replace('/^\\\\item/m', '- ', $res);
    
    return $res;
}

$folders = [];
foreach ($sourceProject->files()->where('type', 'folder')->orderBy('id')->get() as $f) {
    $nf = $targetProject->files()->create(['name' => $f->name, 'type' => 'folder', 'parent_id' => $folders[$f->parent_id] ?? null]);
    $folders[$f->id] = $nf->id;
}

foreach ($sourceProject->files()->where('type', 'file')->get() as $f) {
    if ($f->extension === 'tex') {
        if ($f->name === '5_geschiedenis.tex' || Str::contains($f->name, 'config')) continue;
        $targetProject->files()->create([
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
      . "#align(center)[#text(24pt, weight: \"bold\")[05 Geschiedenis]\\ #v(1cm) #text(14pt)[Full Course V10]]\n"
      . "#v(2cm)\n= Inhoudstafel\n#outline(depth: 3, indent: 1em)\n#pagebreak()\n";
foreach ($targetProject->files()->where('extension', 'typ')->orderBy('name')->get() as $f) {
    $p = $f->getPath();
    if (str_starts_with($p, 'hoofdstukken/')) $main .= "#include \"$p\"\n";
}
$targetProject->files()->create(['name' => 'main.typ', 'type' => 'file', 'extension' => 'typ', 'content' => $main]);

echo "DONE V10!\n";
