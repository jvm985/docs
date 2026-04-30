<?php

namespace App\Http\Controllers;

use App\Models\Project;
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
        $own = $user->projects()->with('users')->orderBy('name')->get();
        $sharedWithMe = $user->sharedProjects()->with('owner')->orderBy('name')->get();
        $publicProjects = Project::query()
            ->whereNotNull('public_permission')
            ->where('user_id', '!=', $user->id)
            ->whereDoesntHave('users', fn ($q) => $q->where('users.id', $user->id))
            ->with('owner')
            ->orderBy('name')
            ->get();

        return view('projects.index', [
            'ownProjects' => $own,
            'sharedProjects' => $sharedWithMe,
            'publicProjects' => $publicProjects,
        ]);
    }

    public function store(Request $request, FileService $files)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);
        $project = $request->user()->projects()->create([
            'name' => $data['name'],
        ]);
        $files->basePath($project);

        return redirect()->route('editor', $project);
    }

    public function destroy(Request $request, Project $project)
    {
        Gate::authorize('delete', $project);
        $base = storage_path('app/private/projects/'.$project->id);
        $project->delete();
        if (is_dir($base)) {
            $this->rrmdir($base);
        }

        return redirect()->route('projects.index');
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

        return redirect()->route('projects.index');
    }

    private function rrmdir(string $dir): void
    {
        $items = scandir($dir) ?: [];
        foreach ($items as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $full = $dir.'/'.$name;
            is_dir($full) ? $this->rrmdir($full) : @unlink($full);
        }
        @rmdir($dir);
    }
}
