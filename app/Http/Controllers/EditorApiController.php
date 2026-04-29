<?php

namespace App\Http\Controllers;

use App\Jobs\CompileDocumentJob;
use App\Jobs\ExecuteRCodeJob;
use App\Models\Node;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class EditorApiController extends Controller
{
    public function project(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return response()->json([
            'project' => $project->only('id', 'name'),
            'nodes' => $project->nodes()->select('id', 'parent_id', 'type', 'name')->get(),
        ]);
    }

    public function openNode(Project $project, Node $node): JsonResponse
    {
        $this->authorize('view', $project);
        abort_unless($node->project_id === $project->id, 404);

        return response()->json([
            'id' => $node->id,
            'name' => $node->name,
            'type' => $node->type,
            'content' => $node->content,
        ]);
    }

    public function saveNode(Request $request, Project $project, Node $node): JsonResponse
    {
        $this->authorize('update', $project);
        abort_unless($node->project_id === $project->id, 404);

        $request->validate(['content' => 'required|string']);
        $node->update(['content' => $request->input('content')]);

        return response()->json(['saved' => true]);
    }

    public function createNode(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:file,folder',
            'parent_id' => 'nullable|integer|exists:nodes,id',
        ]);

        $node = $project->nodes()->create($data);

        return response()->json($node->only('id', 'parent_id', 'type', 'name'), 201);
    }

    public function deleteNode(Project $project, Node $node): JsonResponse
    {
        $this->authorize('update', $project);
        abort_unless($node->project_id === $project->id, 404);

        $node->delete();

        return response()->json(['deleted' => true]);
    }

    public function renameNode(Request $request, Project $project, Node $node): JsonResponse
    {
        $this->authorize('update', $project);
        abort_unless($node->project_id === $project->id, 404);

        $request->validate(['name' => 'required|string|max:255']);
        $node->update(['name' => $request->input('name')]);

        return response()->json(['renamed' => true]);
    }

    public function moveNode(Request $request, Project $project, Node $node): JsonResponse
    {
        $this->authorize('update', $project);
        abort_unless($node->project_id === $project->id, 404);

        $request->validate(['parent_id' => 'nullable|integer|exists:nodes,id']);
        $node->update(['parent_id' => $request->input('parent_id')]);

        return response()->json(['moved' => true]);
    }

    public function compile(Request $request, Project $project, Node $node): JsonResponse
    {
        $this->authorize('update', $project);
        abort_unless($node->project_id === $project->id, 404);
        abort_unless($node->isCompilable(), 422);

        CompileDocumentJob::dispatchSync($node, auth()->user(), $request->input('compiler', 'pdflatex'));

        $log = $node->compileLogs()->latest()->first();

        return response()->json([
            'status' => $log?->status,
            'output' => $log?->output,
            'pdf_url' => $log?->pdf_path ? Storage::disk('public')->url($log->pdf_path) : null,
        ]);
    }

    public function executeR(Request $request, Project $project, Node $node): JsonResponse
    {
        $this->authorize('update', $project);
        abort_unless($node->project_id === $project->id, 404);
        abort_unless($node->isExecutable(), 422);

        $request->validate(['code' => 'required|string']);
        ExecuteRCodeJob::dispatchSync($node, auth()->user(), $request->input('code'));

        $userId = auth()->id();
        $rOutput = Cache::pull("r_output_{$userId}", []);
        $rVars = Cache::get("r_vars_{$userId}");
        $rPlots = Cache::pull("r_plots_{$userId}", []);

        return response()->json([
            'output' => $rOutput,
            'variables' => $rVars,
            'plots' => $rPlots,
        ]);
    }

    public function compileLog(Project $project, Node $node): JsonResponse
    {
        $this->authorize('view', $project);
        abort_unless($node->project_id === $project->id, 404);

        $log = $node->compileLogs()->latest()->first();

        return response()->json([
            'status' => $log?->status,
            'output' => $log?->output,
            'pdf_url' => $log?->pdf_path ? Storage::disk('public')->url($log->pdf_path) : null,
        ]);
    }
}
