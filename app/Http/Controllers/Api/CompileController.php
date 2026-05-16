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

        // Walk first downward (then upward) for prose-only lines: skip
        // headings, fences, bullets, HR rules, bold-lead labels, page-break
        // comments, very short lines. Collect until ~80 chars of needle.
        $collected = $this->pickProse($lines, $idx, 15)
                  ?: $this->pickProse($lines, $idx, -15);

        return implode(' ', $collected);
    }

    /**
     * @param  string[]  $lines
     * @return string[]
     */
    private function pickProse(array $lines, int $start, int $step): array
    {
        $collected = [];
        $totalChars = 0;
        $count = count($lines);
        $end = $step > 0 ? min($count - 1, $start + abs($step)) : max(0, $start + $step);
        $iter = $step > 0 ? range($start, $end) : range($start, $end, -1);

        foreach ($iter as $i) {
            $ln = trim($lines[$i] ?? '');
            if ($ln === '') {
                continue;
            }
            if (str_starts_with($ln, '```')) {
                continue;
            }
            if (str_starts_with($ln, '#')) {
                continue;     // heading
            }
            if (str_starts_with($ln, '-')) {
                continue;     // bullet
            }
            if (preg_match('/^[-*_]{3,}\s*$/', $ln)) {
                continue;     // HR
            }
            if (preg_match('/^\*\*[^*]+\*\*\s*$/', $ln)) {
                continue;     // bold-only lead line
            }
            if (str_starts_with($ln, '<!--')) {
                continue;     // html comment / page marker
            }
            if (strlen($ln) < 30) {
                continue;     // too short to be unique
            }
            $collected[] = $ln;
            $totalChars += strlen($ln);
            if ($totalChars >= 80) {
                break;
            }
        }

        return $collected;
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
