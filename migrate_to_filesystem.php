<?php

use App\Models\File;
use App\Models\Project;
use App\Services\WorkspaceManager;
use Illuminate\Support\Facades\Storage;

$manager = new WorkspaceManager();
$count = 0;
$total = File::where('type', 'file')->count();

echo "🎬 Starting migration of $total files to filesystem...\n";

foreach (File::where('type', 'file')->cursor() as $file) {
    $content = $file->binary_content ?? $file->content;
    if ($content !== null) {
        $manager->putFile($file, $content);
        $count++;
        if ($count % 100 === 0) echo "⏳ $count / $total migrated...\n";
    }
}

echo "✅ DONE! $count files migrated to /storage/app/private/projects\n";
