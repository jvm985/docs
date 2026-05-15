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
    public function index(Request $request)
    {
        $user = $request->user();
        $projects = $user->projects()
            ->whereNull('shared_drive_id')
            ->with('users')
            ->orderByDesc('updated_at')
            ->get();

        return view('projects.index', [
            'projects' => $projects,
            'scope' => 'my-drive',
            'heading' => 'Mijn Drive',
        ]);
    }

    public function sharedWithMe(Request $request)
    {
        $user = $request->user();
        $projects = $user->sharedProjects()
            ->with('owner')
            ->orderByDesc('projects.updated_at')
            ->get();

        return view('projects.shared', [
            'projects' => $projects,
            'scope' => 'shared',
            'heading' => 'Met mij gedeeld',
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
