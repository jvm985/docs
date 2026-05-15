<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\SharedDrive;
use App\Models\User;
use App\Services\FileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller
{
    private const SORTABLE = [
        'name' => 'name',
        'public' => 'public_permission',
        'updated' => 'updated_at',
    ];

    public function index(Request $request)
    {
        $user = $request->user();
        $q = trim((string) $request->query('q', ''));

        if ($q !== '') {
            return $this->search($user, $q);
        }

        [$sortKey, $sortColumn, $dir] = $this->resolveSort($request);

        $projects = $user->projects()
            ->whereNull('shared_drive_id')
            ->with('users')
            ->orderBy($sortColumn, $dir)
            ->get();

        return view('projects.index', [
            'projects' => $projects,
            'scope' => 'my-drive',
            'heading' => 'Mijn Drive',
            'sortKey' => $sortKey,
            'sortDir' => $dir,
        ]);
    }

    private function resolveSort(Request $request, string $defaultKey = 'updated', string $defaultDir = 'desc'): array
    {
        $sortKey = $request->query('sort', $defaultKey);
        if (! array_key_exists($sortKey, self::SORTABLE)) {
            $sortKey = $defaultKey;
        }
        $dir = strtolower((string) $request->query('dir', $defaultDir));
        if (! in_array($dir, ['asc', 'desc'], true)) {
            $dir = $defaultDir;
        }

        return [$sortKey, self::SORTABLE[$sortKey], $dir];
    }

    private function search(User $user, string $q)
    {
        $like = '%'.addcslashes($q, '%_\\').'%';

        $myDrive = $user->projects()
            ->whereNull('shared_drive_id')
            ->where('name', 'like', $like)
            ->orderBy('name')
            ->get();

        $sharedWithMe = $user->sharedProjects()
            ->where('projects.name', 'like', $like)
            ->with('owner')
            ->orderBy('projects.name')
            ->get();

        $driveIds = $user->ownedSharedDrives()->pluck('id')
            ->concat($user->sharedDrives()->pluck('shared_drives.id'))
            ->unique();

        $inSharedDrives = \App\Models\Project::query()
            ->whereIn('shared_drive_id', $driveIds)
            ->where('name', 'like', $like)
            ->with(['owner', 'sharedDrive'])
            ->orderBy('name')
            ->get();

        return view('projects.search', [
            'q' => $q,
            'myDrive' => $myDrive,
            'sharedWithMe' => $sharedWithMe,
            'inSharedDrives' => $inSharedDrives,
            'scope' => 'search',
            'heading' => 'Zoeken naar "'.$q.'"',
        ]);
    }

    public function sharedWithMe(Request $request)
    {
        $user = $request->user();
        [$sortKey, $sortColumn, $dir] = $this->resolveSort($request);

        $projects = $user->sharedProjects()
            ->with('owner')
            ->orderBy('projects.'.$sortColumn, $dir)
            ->get();

        return view('projects.shared', [
            'projects' => $projects,
            'scope' => 'shared',
            'heading' => 'Met mij gedeeld',
            'sortKey' => $sortKey,
            'sortDir' => $dir,
        ]);
    }

    public function store(Request $request, FileService $files)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'shared_drive_id' => ['nullable', 'integer', 'exists:shared_drives,id'],
        ]);

        $driveId = $data['shared_drive_id'] ?? null;
        if ($driveId) {
            $drive = SharedDrive::findOrFail($driveId);
            Gate::authorize('createProjectIn', $drive);
        }

        $project = $request->user()->projects()->create([
            'name' => $data['name'],
            'shared_drive_id' => $driveId,
        ]);
        $files->basePath($project);

        return redirect()->route('editor', $project);
    }

    public function rename(Request $request, Project $project)
    {
        Gate::authorize('update', $project);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);
        $project->update(['name' => $data['name']]);

        return back();
    }

    public function destroy(Request $request, Project $project)
    {
        Gate::authorize('delete', $project);
        $project->delete();

        return back();
    }

    public function duplicate(Request $request, Project $project, FileService $files)
    {
        Gate::authorize('duplicate', $project);
        $copy = $request->user()->projects()->create([
            'name' => $project->name.' (kopie)',
        ]);
        $files->copyAll($project, $copy);

        return redirect()->route('editor', $copy);
    }

    public function share(Request $request, Project $project)
    {
        Gate::authorize('share', $project);
        $data = $request->validate([
            'public_permission' => ['nullable', 'in:read,write'],
            'users' => ['array'],
            'users.*.email' => ['required', 'email'],
            'users.*.permission' => ['required', 'in:read,write'],
        ]);

        DB::transaction(function () use ($data, $project) {
            $project->update(['public_permission' => $data['public_permission'] ?? null]);
            $sync = [];
            foreach ($data['users'] ?? [] as $entry) {
                $user = User::firstWhere('email', $entry['email']);
                if (! $user || $user->id === $project->user_id) {
                    continue;
                }
                $sync[$user->id] = ['permission' => $entry['permission']];
            }
            $project->users()->sync($sync);
        });

        return back();
    }
}
