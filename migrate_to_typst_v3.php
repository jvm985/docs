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
    'description' => 'Final Typst version with tblr, answer and goals support',
]);

echo "Created project: " . $targetProject->id . "\n";

// Helper to convert LaTeX content to Typst
function convertToTypst($content) {
    // 1. Handle answer environment (usually has a height like [2cm])
    $content = preg_replace_callback('/\\\\begin\{answer\}(?:\[(.*?)\])?/', function($matches) {
        $height = !empty($matches[1]) ? $matches[1] : "2cm";
        // Convert LaTeX height to Typst (e.g. 2cm remains 2cm)
        return "\n#answer($height)\n#block[\n";
    }, $content);
    $content = str_replace('\end{answer}', "\n]\n", $content);

    // 2. Handle tblr / longtblr
    // We do a very basic conversion: \begin{tblr}{colspec={...}} ... \end{tblr} -> #tblr(columns: ..., [...])
    $content = preg_replace_callback('/\\\\begin\{(?:long)?tblr\}\{(.*?)\}(.*?)\\\\end\{(?:long)?tblr\}/s', function($matches) {
        $spec = $matches[1];
        $body = $matches[2];
        
        // Count 'X' or 'l' or 'r' in colspec to guess number of columns
        $colCount = preg_match_all('/[XlrcQ]/', $spec, $m);
        $cols = $colCount ? $colCount : 2;
        
        // Clean body: replace & with , and \\ with ], [
        $body = str_replace('&', '], [', $body);
        $body = preg_replace('/\\\\\\\\/', '], [', $body);
        
        return "\n#tblr(columns: $cols, [\n$body\n])\n";
    }, $content);

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

    // 5. Handle basic blocks
    $content = preg_replace('/\\\\begin\{leerstof\}\{(.*?)\}/', "\n#leerstof(title: \"$1\")[\n", $content);
    $content = preg_replace('/\\\\end\{leerstof\}/', "\n]\n", $content);
    $content = preg_replace('/\\\\begin\{bron\}\{(.*?)\}/', "\n#bron(title: \"$1\")[\n", $content);
    $content = preg_replace('/\\\\end\{bron\}/', "\n]\n", $content);
    $content = preg_replace('/\\\\begin\{opdracht\}/', "\n#opdracht()[\n", $content);
    $content = preg_replace('/\\\\end\{opdracht\}/', "\n]\n", $content);

    // 6. Call Pandoc for the rest
    $tmpInput = tempnam(sys_get_temp_dir(), 'tex');
    file_put_contents($tmpInput, $content);
    $tmpOutput = tempnam(sys_get_temp_dir(), 'typ');
    exec("pandoc \"$tmpInput\" -f latex -t typst -o \"$tmpOutput\"");
    $typstContent = file_get_contents($tmpOutput);
    unlink($tmpInput); unlink($tmpOutput);
    
    // 7. Post-process Cleanup
    $typstContent = str_replace(['\(', '\)'], ['(', ')'], $typstContent);
    $typstContent = str_replace('\#', '#', $typstContent);
    $typstContent = str_replace('\"', '"', $typstContent);
    $typstContent = str_replace('\_', '_', $typstContent);
    $typstContent = preg_replace('/([0-9.]*)\\\\linewidth/', '$1' . '100%', $typstContent);
    $typstContent = preg_replace('/([0-9.]*)\\\\textwidth/', '$1' . '100%', $typstContent);
    
    // Fix bracket escaping inside my functions
    $typstContent = str_replace('\], \[', '], [', $typstContent);
    $typstContent = str_replace('\])', '])', $typstContent);
    
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

// Main Typst initialization
$mainTypContent = '#import "template.typ": *
#show: project.with(title: "05 Geschiedenis", authors: ("' . $user->name . '",))
#align(center)[#text(24pt, weight: "bold")[05 Geschiedenis]\ #v(1cm) #text(14pt)[Volledige Cursus]]
#v(2cm)
= Inhoudstafel
#outline(depth: 3, indent: 1em)
#pagebreak()
';

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
        if ($parent_id && Project::find($targetProject->id)->files()->find($parent_id)->name === 'hoofdstukken') {
             $mainTypContent .= "#include \"hoofdstukken/$newName\"\n";
        }
    } else {
        $targetProject->files()->create([
            'name' => $file->name, 'type' => 'file', 'extension' => $file->extension,
            'content' => $file->content, 'binary_content' => $file->binary_content, 'parent_id' => $parent_id,
        ]);
    }
}

// Final template and main save
// Template already saved by previous script, but let's ensure main.typ
$targetProject->files()->create(['name' => 'main.typ', 'type' => 'file', 'extension' => 'typ', 'content' => $mainTypContent]);

echo "DONE V3!\n";
