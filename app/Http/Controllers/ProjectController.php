<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index()
    {
        $projects = auth()->user()->projects()
            ->withMax('nodes', 'updated_at')
            ->with('shares')
            ->latest()
            ->get();

        // Projecten gedeeld met mij
        $sharedProjects = Project::whereHas('shares', function ($q) {
            $q->where('user_id', auth()->id())->orWhere('is_public', true);
        })->where('user_id', '!=', auth()->id())
            ->with('user', 'shares')
            ->get();

        return view('projects.index', compact('projects', 'sharedProjects'));
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);

        $project = auth()->user()->projects()->create($request->only('name'));

        return redirect()->route('editor', $project);
    }

    public function duplicate(Project $project)
    {
        $this->authorize('view', $project);

        $copy = auth()->user()->projects()->create([
            'name' => $project->name.' (kopie)',
        ]);

        // Kopieer alle nodes met mapstructuur
        $idMap = [];
        foreach ($project->nodes()->orderBy('parent_id')->get() as $node) {
            $newNode = $copy->nodes()->create([
                'parent_id' => $node->parent_id ? ($idMap[$node->parent_id] ?? null) : null,
                'type' => $node->type,
                'name' => $node->name,
                'content' => $node->content,
            ]);
            $idMap[$node->id] = $newNode->id;
        }

        return redirect()->route('projects.index')->with('success', "Project '{$project->name}' gekopieerd.");
    }

    public function share(Request $request, Project $project)
    {
        $this->authorize('update', $project);

        $request->validate([
            'is_public' => 'boolean',
            'permission' => 'required_if:is_public,true|in:read,write',
            'emails' => 'nullable|string',
        ]);

        // Verwijder bestaande shares
        $project->shares()->delete();

        if ($request->boolean('is_public')) {
            $project->shares()->create([
                'is_public' => true,
                'permission' => $request->input('permission', 'read'),
            ]);
        } else {
            $emails = array_filter(array_map('trim', explode("\n", $request->input('emails', ''))));
            foreach ($emails as $email) {
                $user = User::where('email', $email)->first();
                if ($user && $user->id !== auth()->id()) {
                    $project->shares()->create([
                        'user_id' => $user->id,
                        'permission' => $request->input('permission', 'read'),
                    ]);
                }
            }
        }

        return redirect()->route('projects.index')->with('success', 'Deelinstellingen opgeslagen.');
    }

    public function destroy(Project $project)
    {
        $this->authorize('delete', $project);
        $project->delete();

        return redirect()->route('projects.index');
    }
}
