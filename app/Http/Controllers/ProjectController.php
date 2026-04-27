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

        // Laad files maar ZONDER de zware binaire data (die breekt JSON en is traag)
        $project->load(['sharedUsers', 'files' => function($query) {
            $query->select('id', 'project_id', 'parent_id', 'name', 'type', 'extension', 'content', 'created_at', 'updated_at', 'preferred_compiler');
        }]);

        return Inertia::render('Projects/Show', [
            'project' => $project,
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

    public function update(Request $request, Project $project)
    {
        $this->authorize('update', $project);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $project->update($request->only('name', 'description'));

        return back()->with('success', 'Project updated');
    }

    public function destroy(Project $project)
    {
        $this->authorize('delete', $project);
        $project->delete();
        return redirect()->route('projects.index')->with('success', 'Project deleted');
    }

    public function duplicate(Project $project)
    {
        $this->authorize('view', $project); // Iedereen die mag kijken mag kopiëren

        $newProject = auth()->user()->projects()->create([
            'name' => $project->name . ' (Kopie)',
            'description' => $project->description,
        ]);

        // Deep copy files
        foreach ($project->files as $file) {
            $newFile = $file->replicate();
            $newFile->project_id = $newProject->id;
            // Parent mapping is lastig zonder recursie, maar we doen hier een simpele versie
            // Voor echte folders/structuur is meer logica nodig, maar voor nu doen we root files
            if (!$file->parent_id) {
                $newFile->save();
            }
        }

        return redirect()->route('projects.index')->with('success', 'Project duplicated');
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
