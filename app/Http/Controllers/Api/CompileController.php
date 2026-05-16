<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\CompileService;
use App\Services\FileService;
use App\Services\PdfLocator;
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

    public function cancel(Request $request, Project $project)
    {
        Gate::authorize('view', $project);
        $killed = $this->compile->cancel($project, $request->user());

        return response()->json(['cancelled' => $killed]);
    }

    public function locate(Request $request, Project $project, FileService $files, PdfLocator $locator)
    {
        Gate::authorize('view', $project);
        $data = $request->validate([
            'path' => ['required', 'string'],
            'line' => ['required', 'integer', 'min:1'],
            'context' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);
        $sourceRel = $files->validateRelativePath($data['path']);
        $sourceAbs = $files->absolutePath($project, $sourceRel);
        if (! is_file($sourceAbs)) {
            return response()->json(['page' => null]);
        }

        // Find the PDF for this source (uses CompileService's per-user output convention).
        $outDir = storage_path('app/private/'.$project->userOutputPath($request->user()->id, 'compile/'.sha1($sourceRel)));
        $pdf = $outDir.'/output.pdf';
        if (! is_file($pdf)) {
            return response()->json(['page' => null]);
        }

        // Extract a stable text needle around the requested line.
        $needle = $this->extractNeedle($sourceAbs, (int) $data['line'], (int) ($data['context'] ?? 2));
        if ($needle === '') {
            return response()->json(['page' => null]);
        }

        $page = $locator->locate($pdf, $needle);

        return response()->json(['page' => $page, 'needle' => $needle]);
    }

    private function extractNeedle(string $sourceAbs, int $line, int $context): string
    {
        $lines = @file($sourceAbs, FILE_IGNORE_NEW_LINES);
        if (! is_array($lines) || $lines === []) {
            return '';
        }
        $idx = max(0, min(count($lines) - 1, $line - 1));

        // Try to use a chunk of pure-prose text (more unique). Walk back/forward
        // a few lines until we have enough non-trivial content.
        $collected = [];
        $minChars = 25;
        $totalChars = 0;
        for ($offset = 0; $offset <= $context; $offset++) {
            foreach ([$idx + $offset, $idx - $offset] as $candidate) {
                if ($candidate < 0 || $candidate >= count($lines)) {
                    continue;
                }
                $ln = trim($lines[$candidate]);
                // Skip code-fence markers, headings, blank, very short.
                if ($ln === '' || str_starts_with($ln, '```') || strlen($ln) < 8) {
                    continue;
                }
                // Strip leading bullet/heading markup so the needle is just prose.
                $ln = preg_replace('/^[#\-*]+\s+/', '', $ln) ?? $ln;
                $ln = preg_replace('/^\d+(\.\d+)*\s+/', '', $ln) ?? $ln;
                $collected[] = $ln;
                $totalChars += strlen($ln);
                if ($totalChars >= $minChars * 2) {
                    break 2;
                }
            }
        }

        return implode(' ', $collected);
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
