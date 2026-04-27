<?php

namespace App\Http\Controllers;

use App\Actions\CompileFileAction;
use App\Models\File;
use App\Models\Project;
use App\Services\WorkspaceManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    public function __construct(protected WorkspaceManager $workspaceManager) {}

    public function show(File $file): JsonResponse
    {
        $this->authorize('view', $file->project);
        
        $content = $this->workspaceManager->getFile($file);
        
        return response()->json([
            'id' => $file->id,
            'name' => $file->name,
            'content' => $content,
            'extension' => $file->extension,
        ]);
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
        ]);

        if ($request->type === 'file') {
            $this->workspaceManager->putFile($file, '');
        }

        return response()->json($file);
    }

    public function update(Request $request, File $file): JsonResponse
    {
        $this->authorize('update', $file->project);
        
        if ($request->has('content')) {
            $this->workspaceManager->putFile($file, $request->input('content'));
        }

        if ($request->has('name')) {
            $file->update(['name' => $request->name]);
        }

        return response()->json($file);
    }

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
            }

            $extension = $uploadedFile->getClientOriginalExtension();
            $rawContent = file_get_contents($uploadedFile->getRealPath());
            
            $file = File::create([
                'project_id' => $request->project_id,
                'parent_id' => $currentParentId,
                'name' => $fileName,
                'type' => 'file',
                'extension' => $extension,
            ]);

            $this->workspaceManager->putFile($file, $rawContent);
            $createdFiles[] = $file;
        }

        return response()->json($createdFiles);
    }

    public function destroy(File $file): JsonResponse
    {
        $this->authorize('update', $file->project);
        $file->delete();
        return response()->json(['message' => 'Deleted']);
    }

    public function compile(Request $request, File $file, CompileFileAction $action)
    {
        return $action->execute($file);
    }
}
