<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index()
    {
        $projects = auth()->user()->projects()->withMax('nodes', 'updated_at')->latest()->get();

        return view('projects.index', compact('projects'));
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);

        $project = auth()->user()->projects()->create($request->only('name'));

        return redirect()->route('editor', $project);
    }

    public function destroy(Project $project)
    {
        $this->authorize('delete', $project);
        $project->delete();

        return redirect()->route('projects.index');
    }
}
