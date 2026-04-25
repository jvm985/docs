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
$globalIndex = []; // Key: "project_id:path", Value: content (string of binary)
$projectIdToName = [];

foreach ($projectsRaw as $line) {
    $p = json_decode($line, true);
    if (!$p) continue;
    $pid = $p['_id']['$oid'] ?? (string)$p['_id'];
    $projectIdToName[$pid] = $p['name'];
    
    $indexFiles = function($folder, $currentPath = "") use (&$indexFiles, &$globalIndex, $pid, $docsLookup, $userFilesPath) {
        if (isset($folder['docs'])) {
            foreach ($folder['docs'] as $doc) {
                $id = $doc['_id']['$oid'] ?? (string)$doc['_id'];
                $path = $currentPath . "/" . $doc['name'];
                $globalIndex["$pid:$path"] = $docsLookup[$id] ?? "";
            }
        }
        if (isset($folder['fileRefs'])) {
            foreach ($folder['fileRefs'] as $f) {
                $id = $f['_id']['$oid'] ?? (string)$f['_id'];
                $path = $currentPath . "/" . $f['name'];
                $filePath = $userFilesPath . "/" . $id;
                if (file_exists($filePath)) {
                    $globalIndex["$pid:$path"] = file_get_contents($filePath);
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

echo "🗺️ Global index built with " . count($globalIndex) . " entries.\n";

// 3. RECURSIEVE IMPORT FUNCTIE (Met link resolutie)
function importFolderRecursive($mongoFolder, $projectId, $docsLookup, $userFilesPath, $globalIndex, $parentId = null) {
    // Importeer Docs
    if (isset($mongoFolder['docs'])) {
        foreach ($mongoFolder['docs'] as $doc) {
            $id = $doc['_id']['$oid'] ?? (string)$doc['_id'];
            $content = $docsLookup[$id] ?? "";
            
            File::create([
                'project_id' => $projectId, 'parent_id' => $parentId, 'name' => $doc['name'],
                'type' => 'file', 'extension' => pathinfo($doc['name'], PATHINFO_EXTENSION) ?: 'tex',
                'content' => $content
            ]);
        }
    }

    // Importeer FileRefs (En resolve links!)
    if (isset($mongoFolder['fileRefs'])) {
        foreach ($mongoFolder['fileRefs'] as $f) {
            $binary = null;
            $text = null;
            
            // Is dit een LINK?
            if (isset($f['linkedFileData']['source_project_id'])) {
                $sourcePid = $f['linkedFileData']['source_project_id']['$oid'] ?? (string)$f['linkedFileData']['source_project_id'];
                $sourcePath = $f['linkedFileData']['source_entity_path'];
                
                if (isset($globalIndex["$sourcePid:$sourcePath"])) {
                    $data = $globalIndex["$sourcePid:$sourcePath"];
                    // Is het een tekstbestand?
                    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['tex', 'sty', 'cls', 'txt', 'bib', 'config'])) {
                        $text = $data;
                    } else {
                        $binary = $data;
                    }
                    echo "🔗 Resolved link: {$f['name']} from project {$sourcePid}\n";
                }
            } else {
                // Gewoon lokaal bestand
                $id = $f['_id']['$oid'] ?? (string)$f['_id'];
                $filePath = $userFilesPath . "/" . $id;
                if (file_exists($filePath)) {
                    $binary = file_get_contents($filePath);
                }
            }

            File::create([
                'project_id' => $projectId, 'parent_id' => $parentId, 'name' => $f['name'],
                'type' => 'file', 'extension' => pathinfo($f['name'], PATHINFO_EXTENSION),
                'content' => $text,
                'binary_content' => $binary
            ]);
        }
    }

    // Importeer Mappen
    if (isset($mongoFolder['folders'])) {
        foreach ($mongoFolder['folders'] as $sub) {
            $folder = File::create([
                'project_id' => $projectId, 'parent_id' => $parentId, 'name' => $sub['name'], 'type' => 'folder'
            ]);
            importFolderRecursive($sub, $projectId, $docsLookup, $userFilesPath, $globalIndex, $folder->id);
        }
    }
}

// 4. UITVOERING
echo "🧹 Wiping existing data for fresh start...\n";
Project::where('description', 'Migrated from Overleaf')->delete();

foreach ($projectsRaw as $line) {
    $p = json_decode($line, true);
    if (!$p) continue;
    $ownerId = $p['owner_ref']['$oid'] ?? (string)$p['owner_ref'];
    $email = $userMap[$ownerId] ?? "joachim.vanmeirvenne@atheneumkapellen.be";
    
    echo "🏗️ Migrating: {$p['name']} for {$email}\n";
    $user = User::where('email', $email)->first() ?: User::create([
        'email' => $email, 'name' => explode('@', $email)[0], 'password' => bcrypt('password'), 'email_verified_at' => now()
    ]);

    $project = Project::create([
        'user_id' => $user->id,
        'name' => $p['name'],
        'description' => "Migrated from Overleaf"
    ]);

    if (isset($p['rootFolder'][0])) {
        importFolderRecursive($p['rootFolder'][0], $project->id, $docsLookup, $userFilesPath, $globalIndex);
    }
}

echo "✅ MASTER MIGRATION COMPLETE!\n";
