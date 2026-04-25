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

        return response()->json($file);
    }

    public function update(Request $request, File $file): JsonResponse
    {
        $this->authorize('update', $file->project);

        $file->update($request->only('content', 'name', 'parent_id'));

        return response()->json($file);
    }

    public function move(Request $request, File $file): JsonResponse
    {
        $this->authorize('update', $file->project);

        $request->validate(['parent_id' => 'nullable|exists:files,id']);

        $file->update(['parent_id' => $request->parent_id]);

        return response()->json(['success' => true]);
    }

    public function duplicate(File $file): JsonResponse
    {
        $this->authorize('update', $file->project);

        $newFile = $file->replicate();
        $newFile->name = 'Copy of ' . $file->name;
        $newFile->save();
        
        return response()->json($newFile);
    }

    public function destroy(File $file): JsonResponse
    {
        $this->authorize('update', $file->project);

        $file->delete();

        return response()->json(['success' => true]);
    }

    public function compile(CompileFileRequest $request, File $file, CompileFileAction $action): JsonResponse
    {
        $this->authorize('view', $file->project);
        
        $result = $action->execute($file, $request->validated());

        return response()->json($result);
    }
}
