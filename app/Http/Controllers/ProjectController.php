<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProjectController extends Controller
{
    public function index()
    {
        return Inertia::render('Projects/Index', [
            'projects' => auth()->user()->projects()->latest()->get()
        ]);
    }

    public function show(Project $project)
    {
        $this->authorizeProject($project);

        return Inertia::render('Projects/Show', [
            'project' => $project->load('files'),
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

    protected function authorizeProject(Project $project)
    {
        if ($project->user_id !== auth()->id()) {
            abort(403);
        }
    }
}
