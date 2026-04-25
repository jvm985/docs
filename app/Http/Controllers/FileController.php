<?php

namespace App\Http\Controllers;

use App\Actions\CompileFileAction;
use App\Http\Requests\CompileFileRequest;
use App\Models\File;
use App\Models\Project;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileController extends Controller
{
    use AuthorizesRequests;

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'project_id' => 'required|exists:projects,id',
            'parent_id' => 'nullable|exists:files,id',
            'file' => 'required|file',
        ]);

        $project = Project::findOrFail($request->project_id);
        $this->authorize('update', $project);

        $uploadedFile = $request->file('file');
        $name = $uploadedFile->getClientOriginalName();
        $extension = $uploadedFile->getClientOriginalExtension();
        $isBinary = !in_array($extension, ['txt', 'tex', 'typ', 'md', 'rmd', 'R', 'json', 'xml', 'csv']);

        $file = File::create([
            'project_id' => $request->project_id,
            'parent_id' => $request->parent_id,
            'name' => $name,
            'type' => 'file',
            'extension' => $extension,
            'content' => $isBinary ? null : file_get_contents($uploadedFile->getRealPath()),
            'binary_content' => $isBinary ? file_get_contents($uploadedFile->getRealPath()) : null,
        ]);

        $this->syncToDisk($file);

        return response()->json($file);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'project_id' => 'required|exists:projects,id',
            'parent_id' => 'nullable|exists:files,id',
            'name' => 'required|string',
            'type' => 'required|in:file,folder',
        ]);

        $project = Project::findOrFail($request->project_id);
        $this->authorize('update', $project);

        $file = File::create([
            'project_id' => $request->project_id,
            'parent_id' => $request->parent_id,
            'name' => $request->name,
            'type' => $request->type,
            'extension' => pathinfo($request->name, PATHINFO_EXTENSION),
            'content' => '',
        ]);

        $this->syncToDisk($file);

        return response()->json($file);
    }

    public function update(Request $request, File $file): JsonResponse
    {
        $this->authorize('update', $file->project);

        // Onthoud oude pad voor hernoemen/verplaatsen op schijf
        $oldPath = $this->getPhysicalPath($file);

        $file->update($request->only('content', 'name', 'parent_id'));

        // Update schijf
        if (isset($request->content)) {
            $this->syncToDisk($file);
        } elseif (isset($request->name) || isset($request->parent_id)) {
            $newPath = $this->getPhysicalPath($file);
            if ($oldPath !== $newPath && file_exists($oldPath)) {
                if (!is_dir(dirname($newPath))) mkdir(dirname($newPath), 0777, true);
                rename($oldPath, $newPath);
            }
        }

        return response()->json($file);
    }

    public function move(Request $request, File $file): JsonResponse
    {
        $this->authorize('update', $file->project);

        $oldPath = $this->getPhysicalPath($file);
        $request->validate(['parent_id' => 'nullable|exists:files,id']);
        $file->update(['parent_id' => $request->parent_id]);
        
        $newPath = $this->getPhysicalPath($file);
        if ($oldPath !== $newPath && file_exists($oldPath)) {
            if (!is_dir(dirname($newPath))) mkdir(dirname($newPath), 0777, true);
            rename($oldPath, $newPath);
        }

        return response()->json(['success' => true]);
    }

    public function destroy(File $file): JsonResponse
    {
        $this->authorize('update', $file->project);

        $path = $this->getPhysicalPath($file);
        if (file_exists($path)) {
            $file->type === 'folder' ? $this->recursiveRemoveDir($path) : unlink($path);
        }

        $file->delete();

        return response()->json(['success' => true]);
    }

    public function compile(CompileFileRequest $request, File $file, CompileFileAction $action): JsonResponse
    {
        $this->authorize('view', $file->project);
        $result = $action->execute($file, $request->validated());
        return response()->json($result);
    }

    /**
     * Schrijf een bestand direct naar de persistente workspace van de gebruiker.
     */
    private function syncToDisk(File $file)
    {
        if ($file->type !== 'file') {
            $path = $this->getPhysicalPath($file);
            if (!is_dir($path)) mkdir($path, 0777, true);
            return;
        }

        $fullPath = $this->getPhysicalPath($file);
        if (!is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0777, true);
        }

        $content = $file->binary_content ?? $file->content;
        file_put_contents($fullPath, $content);
    }

    private function getPhysicalPath(File $file): string
    {
        // Workspace is altijd gekoppeld aan de eigenaar van het project
        $ownerId = $file->project->user_id;
        return storage_path("app/workspaces/user_{$ownerId}/" . $file->project->name . "/" . $file->getPath());
    }

    private function recursiveRemoveDir($dir): void
    {
        if (!is_dir($dir)) return;
        foreach (array_diff(scandir($dir), array('.', '..')) as $file) {
            (is_dir("$dir/$file")) ? $this->recursiveRemoveDir("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }
}
