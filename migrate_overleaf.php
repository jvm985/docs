<?php

use App\Models\File;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// 1. GEBRUIKERS LADEN
$usersRaw = file("/var/www/users.json");
$userMap = [];
foreach ($usersRaw as $line) {
    $u = json_decode($line, true);
    $id = $u['_id']['$oid'] ?? (string)$u['_id'];
    $userMap[$id] = $u['email'];
}

// 2. DOCS (INHOUD) LADEN
echo "📝 Loading document contents...\n";
$docsRaw = file("/var/www/docs.json");
$docsLookup = [];
foreach ($docsRaw as $line) {
    $d = json_decode($line, true);
    $id = $d['_id']['$oid'] ?? (string)$d['_id'];
    $docsLookup[$id] = $d['lines'];
}

// Functie om bestanden recursief te importeren
function importFolder($mongoFolder, $projectId, $docsLookup, $userFilesPath, $parentId = null) {
    // Importeer Docs (Tekstbestanden)
    if (isset($mongoFolder['docs'])) {
        foreach ($mongoFolder['docs'] as $mongoDoc) {
            $content = "";
            $docId = $mongoDoc['_id']['$oid'] ?? (string)$mongoDoc['_id'];
            if (isset($docsLookup[$docId])) {
                $content = implode("\n", $docsLookup[$docId]);
            }
            
            File::create([
                'project_id' => $projectId,
                'parent_id' => $parentId,
                'name' => $mongoDoc['name'],
                'type' => 'file',
                'extension' => pathinfo($mongoDoc['name'], PATHINFO_EXTENSION) ?: 'tex',
                'content' => $content
            ]);
        }
    }

    // Importeer Files (Binaire bestanden)
    if (isset($mongoFolder['fileRefs'])) {
        foreach ($mongoFolder['fileRefs'] as $mongoFile) {
            $fileId = $mongoFile['_id']['$oid'] ?? (string)$mongoFile['_id'];
            $filePath = $userFilesPath . '/' . $fileId;
            $binaryContent = null;
            if (file_exists($filePath)) {
                $binaryContent = file_get_contents($filePath);
            }

            File::create([
                'project_id' => $projectId,
                'parent_id' => $parentId,
                'name' => $mongoFile['name'],
                'type' => 'file',
                'extension' => pathinfo($mongoFile['name'], PATHINFO_EXTENSION),
                'binary_content' => $binaryContent
            ]);
        }
    }

    // Importeer Submappen
    if (isset($mongoFolder['folders'])) {
        foreach ($mongoFolder['folders'] as $mongoSubFolder) {
            $folder = File::create([
                'project_id' => $projectId,
                'parent_id' => $parentId,
                'name' => $mongoSubFolder['name'],
                'type' => 'folder'
            ]);
            importFolder($mongoSubFolder, $projectId, $docsLookup, $userFilesPath, $folder->id);
        }
    }
}

echo "📦 Starting migration of projects...\n";

$projectsRaw = file("/var/www/projects.json");
$userFilesPath = "/opt/irishof/1-latex/data/overleaf/data/user_files";

foreach ($projectsRaw as $line) {
    $p = json_decode($line, true);
    $ownerId = $p['owner_ref']['$oid'] ?? (string)$p['owner_ref'];
    $email = $userMap[$ownerId] ?? "joachim.vanmeirvenne@atheneumkapellen.be";
    
    echo "🏗️ Migrating: {$p['name']} -> {$email}\n";
    
    $user = User::firstOrCreate(
        ['email' => $email],
        ['name' => explode('@', $email)[0], 'password' => bcrypt(Str::random(16)), 'email_verified_at' => now()]
    );

    // Verwijder oud project als het al bestaat (voor schone import)
    Project::where('user_id', $user->id)->where('name', $p['name'])->delete();

    $project = Project::create([
        'user_id' => $user->id,
        'name' => $p['name'],
        'description' => "Migrated from Overleaf"
    ]);

    if (isset($p['rootFolder'][0])) {
        importFolder($p['rootFolder'][0], $project->id, $docsLookup, $userFilesPath);
    }
}

echo "✅ Migration completed successfully!\n";
