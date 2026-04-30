<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\FileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FileController extends Controller
{
    public function __construct(private FileService $files) {}

    public function tree(Request $request, Project $project)
    {
        Gate::authorize('view', $project);

        return response()->json([
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'can_write' => $project->canWrite($request->user()),
                'is_owner' => $project->isOwnedBy($request->user()),
            ],
            'tree' => $this->files->tree($project),
        ]);
    }

    public function read(Request $request, Project $project)
    {
        Gate::authorize('view', $project);
        $path = (string) $request->query('path', '');
        $data = $this->files->readFile($project, $path);

        if ($data['kind'] === 'viewable') {
            $data['url'] = route('editor.asset', ['project' => $project->id, 'path' => $path]);
        }

        return response()->json($data);
    }

    public function save(Request $request, Project $project)
    {
        Gate::authorize('update', $project);
        $data = $request->validate([
            'path' => ['required', 'string'],
            'content' => ['present', 'string'],
        ]);
        $this->files->writeFile($project, $data['path'], $data['content']);

        return response()->json(['ok' => true]);
    }

    public function create(Request $request, Project $project)
    {
        Gate::authorize('update', $project);
        $data = $request->validate([
            'path' => ['required', 'string'],
            'type' => ['required', 'in:file,folder'],
        ]);
        $entry = $this->files->create($project, $data['path'], $data['type']);

        return response()->json($entry);
    }

    public function upload(Request $request, Project $project)
    {
        Gate::authorize('update', $project);
        $data = $request->validate([
            'folder' => ['nullable', 'string'],
            'files' => ['required', 'array'],
            'files.*' => ['file'],
            'paths' => ['nullable', 'array'],
            'paths.*' => ['nullable', 'string'],
        ]);
        $folder = $data['folder'] ?? '';
        $created = [];
        foreach ($data['files'] as $i => $upload) {
            $rel = $data['paths'][$i] ?? $upload->getClientOriginalName();
            $relClean = $this->files->validateRelativePath($rel);
            $name = basename($relClean);
            $sub = trim(dirname($relClean) === '.' ? '' : dirname($relClean), '/');
            $targetFolder = trim($folder.'/'.$sub, '/');
            $created[] = $this->files->upload($project, $targetFolder, $upload, $name);
        }

        return response()->json(['files' => $created]);
    }

    public function delete(Request $request, Project $project)
    {
        Gate::authorize('update', $project);
        $data = $request->validate([
            'path' => ['required', 'string'],
        ]);
        $this->files->delete($project, $data['path']);

        return response()->json(['ok' => true]);
    }

    public function rename(Request $request, Project $project)
    {
        Gate::authorize('update', $project);
        $data = $request->validate([
            'path' => ['required', 'string'],
            'name' => ['required', 'string'],
        ]);
        $newPath = $this->files->rename($project, $data['path'], $data['name']);

        return response()->json(['path' => $newPath]);
    }

    public function move(Request $request, Project $project)
    {
        Gate::authorize('update', $project);
        $data = $request->validate([
            'path' => ['required', 'string'],
            'parent' => ['nullable', 'string'],
        ]);
        $newPath = $this->files->move($project, $data['path'], $data['parent'] ?? '');

        return response()->json(['path' => $newPath]);
    }

    public function copyFromOther(Request $request, Project $project)
    {
        Gate::authorize('update', $project);
        $data = $request->validate([
            'source_project_id' => ['required', 'integer', 'exists:projects,id'],
            'source_path' => ['required', 'string'],
            'target_parent' => ['nullable', 'string'],
        ]);
        $source = Project::findOrFail($data['source_project_id']);
        Gate::authorize('view', $source);
        $newPath = $this->files->copyEntry($source, $data['source_path'], $project, $data['target_parent'] ?? '');

        return response()->json(['path' => $newPath]);
    }
}
