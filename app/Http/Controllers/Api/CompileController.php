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

    public function updateSettings(Request $request, Project $project)
    {
        if (! $project->canWrite($request->user())) {
            abort(403);
        }
        $data = $request->validate([
            'primary_file' => ['nullable', 'string'],
            'compiler' => ['nullable', 'in:pdflatex,xelatex,lualatex'],
        ]);
        $attrs = [];
        if ($request->has('primary_file')) {
            $attrs['primary_file'] = $data['primary_file'] ?: null;
        }
        if (! empty($data['compiler'])) {
            $attrs['compiler'] = $data['compiler'];
        }
        if ($attrs) {
            $project->update($attrs);
        }

        return response()->json([
            'primary_file' => $project->primary_file,
            'compiler' => $project->compiler,
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
