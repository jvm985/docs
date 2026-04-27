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
            'files' => 'required|array',
            'files.*' => 'file',
            'paths' => 'nullable|array',
        ]);

        $project = Project::findOrFail($request->project_id);
        $this->authorize('update', $project);

        $uploadedFiles = $request->file('files');
        $paths = $request->input('paths', []);
        $createdFiles = [];

        foreach ($uploadedFiles as $index => $uploadedFile) {
            $fullPath = $paths[$index] ?? $uploadedFile->getClientOriginalName();
            $pathParts = explode('/', $fullPath);
            $fileName = array_pop($pathParts);
            
            $currentParentId = $request->parent_id;

            // Recreate folder structure
            foreach ($pathParts as $folderName) {
                $folder = File::firstOrCreate([
                    'project_id' => $request->project_id,
                    'parent_id' => $currentParentId,
                    'name' => $folderName,
                    'type' => 'folder',
                ]);
                $currentParentId = $folder->id;
                $this->syncToDisk($folder);
            }

            $extension = $uploadedFile->getClientOriginalExtension();
            $rawContent = file_get_contents($uploadedFile->getRealPath());
            
            // Check if it is valid UTF-8
            $isUtf8 = mb_check_encoding($rawContent, 'UTF-8');
            $isBinary = !in_array(strtolower($extension), ['txt', 'tex', 'typ', 'md', 'rmd', 'r', 'json', 'xml', 'csv']);

            if (!$isUtf8 && !$isBinary) {
                // Try to convert from ISO-8859-1 to UTF-8 for common text files
                $converted = @mb_convert_encoding($rawContent, 'UTF-8', 'ISO-8859-1');
                if (mb_check_encoding($converted, 'UTF-8')) {
                    $rawContent = $converted;
                } else {
                    // If conversion fails, treat as binary even if it has a text extension
                    $isBinary = true;
                }
            }

            $file = File::create([
                'project_id' => $request->project_id,
                'parent_id' => $currentParentId,
                'name' => $fileName,
                'type' => 'file',
                'extension' => $extension,
                'content' => $isBinary ? null : $rawContent,
                'binary_content' => $isBinary ? $rawContent : null,
            ]);

            $this->syncToDisk($file);
            $createdFiles[] = $file;
        }

        return response()->json($createdFiles);
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

        $file->update($request->only('content', 'name', 'parent_id', 'preferred_compiler'));

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
