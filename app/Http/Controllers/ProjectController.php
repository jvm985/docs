<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProjectController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $user = auth()->user();
        
        // Eigen projecten + Gedeelde projecten + Publieke projecten van anderen
        $myProjects = $user->projects()->latest()->get();
        $sharedWithMe = $user->sharedProjects()->latest()->get();
        $publicProjects = Project::where('is_public', true)
            ->where('user_id', '!=', $user->id)
            ->latest()->get();

        return Inertia::render('Projects/Index', [
            'projects' => $myProjects,
            'sharedProjects' => $sharedWithMe,
            'publicProjects' => $publicProjects
        ]);
    }

    public function show(Project $project)
    {
        $this->authorize('view', $project);

        return Inertia::render('Projects/Show', [
            'project' => $project->load(['files', 'sharedUsers']),
            'auth_user_role' => $this->getUserRole($project)
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $project = auth()->user()->projects()->create($request->only('name', 'description'));

        return redirect()->route('projects.show', $project);
    }

    public function updateSharing(Request $request, Project $project)
    {
        $this->authorize('share', $project);

        $request->validate([
            'is_public' => 'boolean',
            'public_role' => 'string|in:viewer,editor',
            'shares' => 'array',
            'shares.*.email' => 'required|email',
            'shares.*.role' => 'required|string|in:viewer,editor'
        ]);

        $project->update([
            'is_public' => $request->is_public,
            'public_role' => $request->public_role,
        ]);

        // Private shares verwerken
        $syncData = [];
        if ($request->has('shares')) {
            foreach ($request->shares as $share) {
                $user = User::where('email', $share['email'])->first();
                if ($user && $user->id !== auth()->id()) {
                    $syncData[$user->id] = ['role' => $share['role']];
                }
            }
        }
        $project->sharedUsers()->sync($syncData);

        return back()->with('success', 'Sharing updated');
    }

    protected function getUserRole(Project $project)
    {
        if ($project->user_id === auth()->id()) return 'owner';
        
        $shared = $project->sharedUsers()->where('user_id', auth()->id())->first();
        if ($shared) return $shared->pivot->role;

        if ($project->is_public) return $project->public_role;

        return 'none';
    }
}
