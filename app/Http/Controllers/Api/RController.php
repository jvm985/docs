<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\RExecutionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RController extends Controller
{
    public function __construct(private RExecutionService $r) {}

    public function execute(Request $request, Project $project)
    {
        Gate::authorize('view', $project);
        $data = $request->validate([
            'code' => ['required', 'string'],
            'path' => ['nullable', 'string'],
        ]);
        $result = $this->r->execute($project, $request->user(), $data['code'], $data['path'] ?? null);

        return response()->json($result);
    }

    public function reset(Request $request, Project $project)
    {
        Gate::authorize('view', $project);
        $this->r->reset($project, $request->user());

        return response()->json(['ok' => true]);
    }

    public function state(Request $request, Project $project)
    {
        Gate::authorize('view', $project);

        return response()->json($this->r->state($project, $request->user()));
    }
}
