<?php

namespace App\Jobs;

use App\Models\CompileLog;
use App\Models\Node;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CompileDocumentJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 2;

    /** @var array<int, int> */
    public array $backoff = [5, 30];

    public function __construct(
        public readonly Node $node,
        public readonly User $user,
        public readonly string $compiler = 'pdflatex',
    ) {}

    public function handle(): void
    {
        $log = CompileLog::create([
            'node_id' => $this->node->id,
            'user_id' => $this->user->id,
            'compiler' => $this->compiler,
            'status' => 'running',
        ]);

        $workDir = $this->prepareWorkDirectory();

        try {
            [$output, $pdfPath] = $this->compile($workDir);

            $log->update([
                'status' => $pdfPath ? 'success' : 'failed',
                'output' => $output,
                'pdf_path' => $pdfPath,
            ]);

            if ($pdfPath) {
                $this->notifyPdfReady($pdfPath);
            }
        } finally {
            $this->cleanupWorkDirectory($workDir);
        }
    }

    public function failed(\Throwable $exception): void
    {
        CompileLog::where('node_id', $this->node->id)
            ->where('status', 'running')
            ->update(['status' => 'failed', 'output' => $exception->getMessage()]);
    }

    private function prepareWorkDirectory(): string
    {
        $workDir = storage_path('app/compile/'.Str::uuid());
        mkdir($workDir, 0755, true);

        // Write all project files to work dir
        $projectNodes = $this->node->project->nodes()->where('type', 'file')->get();
        foreach ($projectNodes as $node) {
            $filePath = $workDir.'/'.$node->name;
            $dir = dirname($filePath);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($filePath, $node->content ?? '');
        }

        // Write cross-project files (../projectname/ prefix)
        $userProjects = $this->user->projects()->with(['nodes' => fn ($q) => $q->where('type', 'file')])->get();
        foreach ($userProjects as $project) {
            if ($project->id === $this->node->project_id) {
                continue;
            }
            $projectDir = dirname($workDir).'/'.$project->name;
            if (! is_dir($projectDir)) {
                mkdir($projectDir, 0755, true);
            }
            foreach ($project->nodes as $node) {
                file_put_contents($projectDir.'/'.$node->name, $node->content ?? '');
            }
        }

        return $workDir;
    }

    private function compile(string $workDir): array
    {
        $ext = $this->node->extension();
        $fileName = $this->node->name;

        return match ($ext) {
            'tex' => $this->compileLaTeX($workDir, $fileName),
            'md' => $this->compileMarkdown($workDir, $fileName),
            'typ' => $this->compileTypst($workDir, $fileName),
            'rmd' => $this->compileRMarkdown($workDir, $fileName),
            default => ['Unsupported file type: '.$ext, null],
        };
    }

    private function compileLaTeX(string $workDir, string $fileName): array
    {
        $cmd = sprintf(
            'cd %s && %s -interaction=nonstopmode -output-directory=%s %s 2>&1',
            escapeshellarg($workDir),
            escapeshellarg($this->compiler),
            escapeshellarg($workDir),
            escapeshellarg($fileName)
        );

        // Run twice for references/TOC
        exec($cmd, $out1);
        exec($cmd, $out2);
        $output = implode("\n", array_merge($out1, $out2));

        $pdfFile = $workDir.'/'.pathinfo($fileName, PATHINFO_FILENAME).'.pdf';

        return [$output, $this->storePdf($pdfFile)];
    }

    private function compileMarkdown(string $workDir, string $fileName): array
    {
        $outFile = $workDir.'/'.pathinfo($fileName, PATHINFO_FILENAME).'.pdf';
        $cmd = sprintf(
            'cd %s && pandoc %s -o %s 2>&1',
            escapeshellarg($workDir),
            escapeshellarg($fileName),
            escapeshellarg($outFile)
        );
        exec($cmd, $out, $code);

        return [implode("\n", $out), $code === 0 ? $this->storePdf($outFile) : null];
    }

    private function compileTypst(string $workDir, string $fileName): array
    {
        $outFile = $workDir.'/'.pathinfo($fileName, PATHINFO_FILENAME).'.pdf';
        $cmd = sprintf(
            'cd %s && typst compile %s %s 2>&1',
            escapeshellarg($workDir),
            escapeshellarg($fileName),
            escapeshellarg($outFile)
        );
        exec($cmd, $out, $code);

        return [implode("\n", $out), $code === 0 ? $this->storePdf($outFile) : null];
    }

    private function compileRMarkdown(string $workDir, string $fileName): array
    {
        $cmd = sprintf(
            'cd %s && Rscript -e "rmarkdown::render(\'%s\')" 2>&1',
            escapeshellarg($workDir),
            addslashes($fileName)
        );
        exec($cmd, $out, $code);

        $pdfFile = $workDir.'/'.pathinfo($fileName, PATHINFO_FILENAME).'.pdf';

        return [implode("\n", $out), $code === 0 ? $this->storePdf($pdfFile) : null];
    }

    private function storePdf(string $pdfFile): ?string
    {
        if (! file_exists($pdfFile)) {
            return null;
        }

        $storagePath = 'pdfs/'.$this->user->id.'/'.$this->node->id.'/'.basename($pdfFile);
        Storage::disk('public')->put($storagePath, file_get_contents($pdfFile));

        return $storagePath;
    }

    private function notifyPdfReady(string $storagePath): void
    {
        $url = Storage::url($storagePath);
        // Broadcast to user's channel via Livewire event
        // This will be picked up by the editor page
    }

    private function cleanupWorkDirectory(string $workDir): void
    {
        if (is_dir($workDir)) {
            exec('rm -rf '.escapeshellarg($workDir));
        }
    }
}
