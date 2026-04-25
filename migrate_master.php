<?php

use App\Models\File;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Str;

// 1. DATA LADEN
$projectsRaw = file("/var/www/projects_all.json");
$docsRaw = file("/var/www/docs_all.json");
$usersRaw = file("/var/www/users_all.json");

$userMap = [];
foreach ($usersRaw as $line) {
    $u = json_decode($line, true);
    if ($u) {
        $id = $u['_id']['$oid'] ?? (string)$u['_id'];
        $userMap[$id] = $u['email'];
    }
}

$docsLookup = [];
foreach ($docsRaw as $line) {
    $d = json_decode($line, true);
    if ($d) {
        $id = $d['_id']['$oid'] ?? (string)$d['_id'];
        $docsLookup[$id] = implode("\n", $d['lines']);
    }
}

$userFilesPath = "/var/www/old_user_files";

// 2. GLOBAL INDEX BOUWEN (Voor linked files)
$globalIndex = []; 

foreach ($projectsRaw as $line) {
    $p = json_decode($line, true);
    if (!$p) continue;
    $pid = $p['_id']['$oid'] ?? (string)$p['_id'];
    
    $indexFiles = function($folder, $currentPath = "") use (&$indexFiles, &$globalIndex, $pid, $docsLookup, $userFilesPath) {
        if (isset($folder['docs'])) {
            foreach ($folder['docs'] as $doc) {
                $id = $doc['_id']['$oid'] ?? (string)$doc['_id'];
                $path = ltrim($currentPath . "/" . $doc['name'], '/');
                $globalIndex["$pid:$path"] = ['type' => 'text', 'content' => $docsLookup[$id] ?? ""];
            }
        }
        if (isset($folder['fileRefs'])) {
            foreach ($folder['fileRefs'] as $f) {
                $id = $f['_id']['$oid'] ?? (string)$f['_id'];
                $path = ltrim($currentPath . "/" . $f['name'], '/');
                $filePath = $userFilesPath . "/" . $id;
                if (file_exists($filePath)) {
                    $globalIndex["$pid:$path"] = ['type' => 'binary', 'content' => file_get_contents($filePath)];
                }
            }
        }
        if (isset($folder['folders'])) {
            foreach ($folder['folders'] as $sub) {
                $indexFiles($sub, $currentPath . "/" . $sub['name']);
            }
        }
    };
    
    if (isset($p['rootFolder'][0])) {
        $indexFiles($p['rootFolder'][0]);
    }
}

// 3. RECURSIEVE IMPORT FUNCTIE (Met link resolutie)
function importFolderRecursive($mongoFolder, $projectId, $docsLookup, $userFilesPath, $globalIndex, $parentId = null) {
    if (isset($mongoFolder['docs'])) {
        foreach ($mongoFolder['docs'] as $doc) {
            $id = $doc['_id']['$oid'] ?? (string)$doc['_id'];
            File::create([
                'project_id' => $projectId, 'parent_id' => $parentId, 'name' => $doc['name'],
                'type' => 'file', 'extension' => pathinfo($doc['name'], PATHINFO_EXTENSION) ?: 'tex',
                'content' => $docsLookup[$id] ?? ""
            ]);
        }
    }

    if (isset($mongoFolder['fileRefs'])) {
        foreach ($mongoFolder['fileRefs'] as $f) {
            $binary = null; $text = null;
            
            if (isset($f['linkedFileData']['source_project_id'])) {
                $sourcePid = $f['linkedFileData']['source_project_id']['$oid'] ?? (string)$f['linkedFileData']['source_project_id'];
                $sourcePath = ltrim($f['linkedFileData']['source_entity_path'], '/');
                if (isset($globalIndex["$sourcePid:$sourcePath"])) {
                    $data = $globalIndex["$sourcePid:$sourcePath"];
                    if ($data['type'] === 'text') $text = $data['content']; else $binary = $data['content'];
                }
            } else {
                $id = $f['_id']['$oid'] ?? (string)$f['_id'];
                $filePath = $userFilesPath . "/" . $id;
                if (file_exists($filePath)) $binary = file_get_contents($filePath);
            }

            File::create([
                'project_id' => $projectId, 'parent_id' => $parentId, 'name' => $f['name'],
                'type' => 'file', 'extension' => pathinfo($f['name'], PATHINFO_EXTENSION),
                'content' => $text, 'binary_content' => $binary,
                'preferred_compiler' => 'xelatex' // Standaard XeLaTeX voor alles
            ]);
        }
    }

    if (isset($mongoFolder['folders'])) {
        foreach ($mongoFolder['folders'] as $sub) {
            $folder = File::create(['project_id' => $projectId, 'parent_id' => $parentId, 'name' => $sub['name'], 'type' => 'folder']);
            importFolderRecursive($sub, $projectId, $docsLookup, $userFilesPath, $globalIndex, $folder->id);
        }
    }
}

// 4. UITVOERING
Project::query()->delete();

foreach ($projectsRaw as $line) {
    $p = json_decode($line, true);
    if (!$p) continue;
    $ownerId = $p['owner_ref']['$oid'] ?? (string)$p['owner_ref'];
    $email = $userMap[$ownerId] ?? "joachim.vanmeirvenne@atheneumkapellen.be";
    
    $user = User::where('email', $email)->first();
    if (!$user) continue;

    $project = Project::create(['user_id' => $user->id, 'name' => $p['name'], 'description' => "Migrated from Overleaf"]);
    if (isset($p['rootFolder'][0])) importFolderRecursive($p['rootFolder'][0], $project->id, $docsLookup, $userFilesPath, $globalIndex);
}

echo "✅ MASTER MIGRATION V8 COMPLETE!\n";
