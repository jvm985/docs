<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\CompileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CompileController extends Controller
{
    public function __construct(private CompileService $compile) {}

    public function compile(Request $request, Project $project)
    {
        Gate::authorize('view', $project);
        $data = $request->validate([
            'path' => ['required', 'string'],
            'compiler' => ['nullable', 'string'],
            'clean' => ['nullable', 'boolean'],
        ]);
        $result = $this->compile->compile($project, $request->user(), $data['path'], $data['compiler'] ?? null, (bool) ($data['clean'] ?? false));

        return response()->json([
            'status' => $result['status'],
            'log' => $result['log'],
            'compiler' => $result['compiler'],
            'pdf_url' => $result['has_pdf']
                ? route('editor.pdf', ['project' => $project->id, 'path' => $data['path'], 'v' => time()])
                : null,
        ]);
    }

    public function lastLog(Request $request, Project $project)
    {
        Gate::authorize('view', $project);
        $path = (string) $request->query('path', '');
        $result = $this->compile->lastLog($project, $request->user(), $path);
        if (! $result) {
            return response()->json(['log' => null, 'pdf_url' => null]);
        }

        return response()->json([
            'log' => $result['log'],
            'status' => $result['status'],
            'pdf_url' => $result['has_pdf']
                ? route('editor.pdf', ['project' => $project->id, 'path' => $path, 'v' => time()])
                : null,
        ]);
    }
}
