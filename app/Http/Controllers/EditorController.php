<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\CompileService;
use App\Services\FileService;
use App\Services\RExecutionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EditorController extends Controller
{
    public function show(Request $request, Project $project)
    {
        Gate::authorize('view', $project);
        $project->load('users');
        $canWrite = $project->canWrite($request->user());

        return view('editor', [
            'project' => $project,
            'canWrite' => $canWrite,
        ]);
    }

    public function pdf(Request $request, Project $project, CompileService $compile): BinaryFileResponse
    {
        Gate::authorize('view', $project);
        $path = (string) $request->query('path', '');
        $abs = $compile->pdfPath($project, $request->user(), $path);
        abort_unless($abs, 404);

        return response()->file($abs, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function plot(Request $request, Project $project, RExecutionService $r): BinaryFileResponse
    {
        Gate::authorize('view', $project);
        $name = (string) $request->query('name', '');
        $abs = $r->plotPath($project, $request->user(), $name);
        abort_unless($abs, 404);

        return response()->file($abs, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function asset(Request $request, Project $project, FileService $files): BinaryFileResponse
    {
        Gate::authorize('view', $project);
        $path = (string) $request->query('path', '');
        $abs = $files->absolutePath($project, $path);
        abort_unless(is_file($abs), 404);

        return response()->file($abs, [
            'Cache-Control' => 'no-store',
        ]);
    }
}
