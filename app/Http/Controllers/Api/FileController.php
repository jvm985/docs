<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\FileService;
use App\Services\LinkRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FileController extends Controller
{
    public function __construct(private FileService $files, private LinkRegistry $links) {}

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

        $link = $this->links->get($project, $this->files->validateRelativePath($path));
        $data['is_linked'] = $link !== null;
        if ($link) {
            $data['link'] = $link;
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
        if ($this->links->isLinked($project, $this->files->validateRelativePath($data['path']))) {
            return response()->json(['error' => 'Linked files are read-only. Use refresh to update from the source.'], 423);
        }
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
        $clean = $this->files->validateRelativePath($data['path']);
        $this->files->delete($project, $data['path']);
        $this->links->removePrefix($project, $clean);

        return response()->json(['ok' => true]);
    }

    public function rename(Request $request, Project $project)
    {
        Gate::authorize('update', $project);
        $data = $request->validate([
            'path' => ['required', 'string'],
            'name' => ['required', 'string'],
        ]);
        $oldClean = $this->files->validateRelativePath($data['path']);
        $newPath = $this->files->rename($project, $data['path'], $data['name']);
        $this->links->rename($project, $oldClean, $newPath);

        return response()->json(['path' => $newPath]);
    }

    public function move(Request $request, Project $project)
    {
        Gate::authorize('update', $project);
        $data = $request->validate([
            'path' => ['required', 'string'],
            'parent' => ['nullable', 'string'],
        ]);
        $oldClean = $this->files->validateRelativePath($data['path']);
        $newPath = $this->files->move($project, $data['path'], $data['parent'] ?? '');
        $this->links->rename($project, $oldClean, $newPath);

        return response()->json(['path' => $newPath]);
    }

    public function importFromProject(Request $request, Project $project)
    {
        Gate::authorize('update', $project);
        $data = $request->validate([
            'source_project_id' => ['required', 'integer', 'exists:projects,id'],
            'source_path' => ['required', 'string'],
            'target_parent' => ['nullable', 'string'],
            'mode' => ['nullable', 'in:link,copy'],
        ]);
        $source = Project::findOrFail($data['source_project_id']);
        Gate::authorize('view', $source);

        $newPath = $this->files->copyEntry($source, $data['source_path'], $project, $data['target_parent'] ?? '');
        $mode = $data['mode'] ?? 'link';

        if ($mode === 'link') {
            $this->registerLinks($project, $source, $newPath, $this->files->validateRelativePath($data['source_path']));
        }

        return response()->json(['path' => $newPath, 'mode' => $mode]);
    }

    public function refreshLink(Request $request, Project $project)
    {
        Gate::authorize('update', $project);
        $data = $request->validate([
            'path' => ['required', 'string'],
        ]);
        $clean = $this->files->validateRelativePath($data['path']);
        $link = $this->links->get($project, $clean);
        if (! $link) {
            return response()->json(['error' => 'This path is not linked to another project.'], 422);
        }
        $source = Project::find($link['source_project_id']);
        if (! $source) {
            return response()->json(['error' => 'Source project no longer exists.'], 410);
        }
        Gate::authorize('view', $source);

        $sourceAbs = $this->files->absolutePath($source, $link['source_path']);
        if (! is_file($sourceAbs)) {
            return response()->json(['error' => 'Source file no longer exists.'], 410);
        }
        $targetAbs = $this->files->absolutePath($project, $clean);
        @copy($sourceAbs, $targetAbs);
        $this->links->set($project, $clean, $source->id, $link['source_path']);

        return response()->json(['ok' => true, 'copied_at' => time()]);
    }

    public function accessibleProjects(Request $request)
    {
        $user = $request->user();
        $own = $user->projects()->select('id', 'name')->get()
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'access' => 'owner']);
        $shared = $user->sharedProjects()->select('projects.id', 'projects.name')->get()
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'access' => 'shared']);
        $public = Project::query()
            ->whereNotNull('public_permission')
            ->where('user_id', '!=', $user->id)
            ->whereDoesntHave('users', fn ($q) => $q->where('users.id', $user->id))
            ->select('id', 'name')
            ->get()
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'access' => 'public']);

        return response()->json([
            'projects' => $own->concat($shared)->concat($public)->sortBy('name')->values(),
        ]);
    }

    public function browseProject(Request $request, Project $other)
    {
        Gate::authorize('view', $other);

        return response()->json([
            'project' => ['id' => $other->id, 'name' => $other->name],
            'tree' => $this->files->tree($other),
        ]);
    }

    private function registerLinks(Project $target, Project $source, string $targetPath, string $sourcePath): void
    {
        $sourceAbs = $this->files->absolutePath($source, $sourcePath);
        $targetAbs = $this->files->absolutePath($target, $targetPath);
        if (is_file($sourceAbs)) {
            $this->links->set($target, $targetPath, $source->id, $sourcePath);

            return;
        }
        if (! is_dir($sourceAbs) || ! is_dir($targetAbs)) {
            return;
        }
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceAbs, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($iter as $entry) {
            if (! $entry->isFile()) {
                continue;
            }
            $rel = ltrim(substr($entry->getPathname(), strlen($sourceAbs)), '/');
            $this->links->set($target, $targetPath.'/'.$rel, $source->id, $sourcePath.'/'.$rel);
        }
    }
}
