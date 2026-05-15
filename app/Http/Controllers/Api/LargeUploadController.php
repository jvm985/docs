<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SharedDrive;
use App\Services\FileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class LargeUploadController extends Controller
{
    private const CHUNK_SIZE = 5 * 1024 * 1024; // 5 MB

    private const MAX_FILE_SIZE = 10 * 1024 * 1024 * 1024; // 10 GB

    public function init(Request $request, SharedDrive $drive)
    {
        Gate::authorize('createProjectIn', $drive);

        $data = $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1', 'max:'.self::MAX_FILE_SIZE],
        ]);

        $filename = basename($data['filename']);
        if ($filename === '' || str_contains($filename, '/') || str_contains($filename, "\0")) {
            abort(422, 'Invalid filename.');
        }

        $uploadId = bin2hex(random_bytes(16));
        $userId = $request->user()->id;

        $manifest = [
            'upload_id' => $uploadId,
            'drive_id' => $drive->id,
            'user_id' => $userId,
            'filename' => $filename,
            'size' => $data['size'],
            'chunk_size' => self::CHUNK_SIZE,
            'total_chunks' => (int) ceil($data['size'] / self::CHUNK_SIZE),
            'received_chunks' => [],
            'created_at' => now()->toIso8601String(),
        ];

        $dir = $this->uploadDir($userId, $uploadId);
        Storage::makeDirectory($dir);
        Storage::put($dir.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));

        return response()->json($manifest);
    }

    public function status(Request $request, SharedDrive $drive, string $uploadId)
    {
        Gate::authorize('createProjectIn', $drive);

        $manifest = $this->loadManifest($request->user()->id, $uploadId);
        if ($manifest['drive_id'] !== $drive->id) {
            abort(404);
        }

        return response()->json($manifest);
    }

    public function chunk(Request $request, SharedDrive $drive, string $uploadId, int $index)
    {
        Gate::authorize('createProjectIn', $drive);

        $userId = $request->user()->id;
        $manifest = $this->loadManifest($userId, $uploadId);

        if ($manifest['drive_id'] !== $drive->id) {
            abort(404);
        }
        if ($index < 0 || $index >= $manifest['total_chunks']) {
            abort(422, 'Invalid chunk index.');
        }

        $body = $request->getContent();
        if ($body === '' || $body === false) {
            abort(422, 'Empty chunk body.');
        }

        $dir = $this->uploadDir($userId, $uploadId);
        Storage::put($dir.'/chunk_'.$index, $body);

        if (! in_array($index, $manifest['received_chunks'], true)) {
            $manifest['received_chunks'][] = $index;
            sort($manifest['received_chunks']);
            Storage::put($dir.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
        }

        return response()->json([
            'received' => count($manifest['received_chunks']),
            'total' => $manifest['total_chunks'],
        ]);
    }

    public function finish(Request $request, SharedDrive $drive, string $uploadId, FileService $files)
    {
        Gate::authorize('createProjectIn', $drive);

        $userId = $request->user()->id;
        $manifest = $this->loadManifest($userId, $uploadId);

        if ($manifest['drive_id'] !== $drive->id) {
            abort(404);
        }
        if (count($manifest['received_chunks']) !== $manifest['total_chunks']) {
            return response()->json([
                'error' => 'incomplete',
                'received' => count($manifest['received_chunks']),
                'total' => $manifest['total_chunks'],
            ], 409);
        }

        // Create the project that hosts the uploaded file.
        $projectName = pathinfo($manifest['filename'], PATHINFO_FILENAME) ?: $manifest['filename'];
        $project = $request->user()->projects()->create([
            'name' => $projectName,
            'shared_drive_id' => $drive->id,
        ]);
        $files->basePath($project);

        // Stream-assemble chunks into the project's files dir.
        $finalRelative = $project->filesPath($manifest['filename']);
        $finalAbsolute = Storage::disk('local')->path($finalRelative);
        $finalDir = dirname($finalAbsolute);
        if (! is_dir($finalDir)) {
            @mkdir($finalDir, 0775, true);
        }

        $out = fopen($finalAbsolute, 'wb');
        if ($out === false) {
            abort(500, 'Could not open destination file.');
        }
        try {
            for ($i = 0; $i < $manifest['total_chunks']; $i++) {
                $chunkPath = Storage::disk('local')->path($this->uploadDir($userId, $uploadId).'/chunk_'.$i);
                $in = fopen($chunkPath, 'rb');
                if ($in === false) {
                    abort(500, 'Missing chunk '.$i);
                }
                stream_copy_to_stream($in, $out);
                fclose($in);
            }
        } finally {
            fclose($out);
        }

        // Cleanup upload scratch space.
        Storage::deleteDirectory($this->uploadDir($userId, $uploadId));

        return response()->json([
            'project_id' => $project->id,
            'project_url' => route('editor', $project),
            'filename' => $manifest['filename'],
            'size' => filesize($finalAbsolute),
        ]);
    }

    public function cancel(Request $request, SharedDrive $drive, string $uploadId)
    {
        Gate::authorize('createProjectIn', $drive);

        $dir = $this->uploadDir($request->user()->id, $uploadId);
        if (Storage::exists($dir.'/manifest.json')) {
            $manifest = json_decode(Storage::get($dir.'/manifest.json'), true);
            if (($manifest['drive_id'] ?? null) !== $drive->id) {
                abort(404);
            }
        }
        Storage::deleteDirectory($dir);

        return response()->json(['cancelled' => true]);
    }

    private function uploadDir(int $userId, string $uploadId): string
    {
        if (! preg_match('/^[a-f0-9]{32}$/', $uploadId)) {
            abort(422, 'Invalid upload id.');
        }

        return 'uploads/'.$userId.'/'.$uploadId;
    }

    private function loadManifest(int $userId, string $uploadId): array
    {
        $path = $this->uploadDir($userId, $uploadId).'/manifest.json';
        if (! Storage::exists($path)) {
            abort(404);
        }

        return json_decode(Storage::get($path), true);
    }
}
