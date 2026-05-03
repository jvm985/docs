<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use RuntimeException;
use Symfony\Component\Process\Process;

class CompileService
{
    public const SUPPORTED_LATEX_COMPILERS = ['pdflatex', 'xelatex', 'lualatex'];

    public function __construct(private FileService $files) {}

    public function canCompile(string $extension): bool
    {
        return in_array($extension, ['tex', 'md', 'rmd', 'typ'], true);
    }

    public function compile(Project $project, User $user, string $relPath, ?string $compiler = null, bool $clean = false): array
    {
        $abs = $this->files->absolutePath($project, $relPath);
        if (! is_file($abs)) {
            throw new RuntimeException('File not found.');
        }
        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        if (! $this->canCompile($ext)) {
            throw new RuntimeException('File is not compilable.');
        }

        $sourceDir = $this->files->basePath($project);
        $sourceRel = $this->files->validateRelativePath($relPath);
        if ($clean) {
            $this->wipeOutputDir($project, $user, $sourceRel);
        }
        $outputDir = $this->ensureOutputDir($project, $user, $sourceRel);

        return match ($ext) {
            'tex' => $this->compileLatex($sourceDir, $sourceRel, $outputDir, $compiler ?? 'pdflatex'),
            'md' => $this->compileMarkdown($sourceDir, $sourceRel, $outputDir),
            'rmd' => $this->compileRmd($sourceDir, $sourceRel, $outputDir),
            'typ' => $this->compileTypst($sourceDir, $sourceRel, $outputDir),
            default => throw new RuntimeException('Unsupported.'),
        };
    }

    public function lastLog(Project $project, User $user, string $relPath): ?array
    {
        $rel = $this->files->validateRelativePath($relPath);
        $outputDir = $this->outputDir($project, $user, $rel);
        $logFile = $outputDir.'/compile.log';
        $pdfFile = $outputDir.'/output.pdf';
        if (! is_file($logFile) && ! is_file($pdfFile)) {
            return null;
        }

        return [
            'log' => is_file($logFile) ? (string) file_get_contents($logFile) : '',
            'has_pdf' => is_file($pdfFile),
            'status' => is_file($pdfFile) ? 'success' : 'failed',
        ];
    }

    private function compileLatex(string $sourceDir, string $sourceRel, string $outputDir, string $compiler): array
    {
        if (! in_array($compiler, self::SUPPORTED_LATEX_COMPILERS, true)) {
            throw new RuntimeException('Unsupported compiler.');
        }
        $sourceAbs = $sourceDir.'/'.$sourceRel;

        // Mirror the project's subdirectory structure inside the output dir so
        // \include{sub/file} can write its .aux next to the source file.
        $this->mirrorTreeDirs($sourceDir, $outputDir);

        $jobname = 'output';
        $args = [
            $compiler,
            '-interaction=nonstopmode',
            '-halt-on-error',
            '-output-directory='.$outputDir,
            '-jobname='.$jobname,
            $sourceAbs,
        ];
        // Run from the source's own directory so relative \input/\include resolve.
        $cwd = dirname($sourceAbs);
        $process = $this->run($args, $cwd, 90);
        $log = $process->getOutput()."\n".$process->getErrorOutput();
        if ($process->isSuccessful()) {
            $process2 = $this->run($args, $cwd, 90);
            $log .= "\n".$process2->getOutput()."\n".$process2->getErrorOutput();
        }
        $pdf = $outputDir.'/'.$jobname.'.pdf';
        $finalPdf = $outputDir.'/output.pdf';
        $status = is_file($finalPdf) ? 'success' : 'failed';
        $this->writeLog($outputDir, $log);

        return [
            'status' => $status,
            'log' => $log,
            'has_pdf' => is_file($finalPdf),
            'compiler' => $compiler,
        ];
    }

    private function mirrorTreeDirs(string $sourceDir, string $outputDir): void
    {
        if (! is_dir($sourceDir)) {
            return;
        }
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($iter as $entry) {
            if (! $entry->isDir()) {
                continue;
            }
            $rel = ltrim(substr($entry->getPathname(), strlen($sourceDir)), '/');
            if ($rel === '') {
                continue;
            }
            $target = $outputDir.'/'.$rel;
            if (! is_dir($target)) {
                @mkdir($target, 0775, true);
            }
        }
    }

    private function compileMarkdown(string $sourceDir, string $sourceRel, string $outputDir): array
    {
        $sourceAbs = $sourceDir.'/'.$sourceRel;
        $pdf = $outputDir.'/output.pdf';
        @unlink($pdf);
        $process = $this->run([
            'pandoc', $sourceAbs, '-o', $pdf,
            '--pdf-engine=xelatex',
        ], dirname($sourceAbs), 90);
        $log = $process->getOutput()."\n".$process->getErrorOutput();
        $status = is_file($pdf) ? 'success' : 'failed';
        $this->writeLog($outputDir, $log);

        return [
            'status' => $status,
            'log' => $log,
            'has_pdf' => is_file($pdf),
            'compiler' => 'pandoc',
        ];
    }

    private function compileRmd(string $sourceDir, string $sourceRel, string $outputDir): array
    {
        $sourceAbs = $sourceDir.'/'.$sourceRel;
        $pdf = $outputDir.'/output.pdf';
        @unlink($pdf);
        $script = sprintf(
            'rmarkdown::render(%s, output_format = "pdf_document", output_file = "output.pdf", output_dir = %s, intermediates_dir = %s, clean = TRUE)',
            $this->rString($sourceAbs),
            $this->rString($outputDir),
            $this->rString($outputDir),
        );
        $process = $this->run(['Rscript', '-e', $script], dirname($sourceAbs), 180);
        $log = $process->getOutput()."\n".$process->getErrorOutput();
        $status = is_file($pdf) ? 'success' : 'failed';
        $this->writeLog($outputDir, $log);

        return [
            'status' => $status,
            'log' => $log,
            'has_pdf' => is_file($pdf),
            'compiler' => 'rmarkdown',
        ];
    }

    private function compileTypst(string $sourceDir, string $sourceRel, string $outputDir): array
    {
        $sourceAbs = $sourceDir.'/'.$sourceRel;
        $pdf = $outputDir.'/output.pdf';
        @unlink($pdf);
        $process = $this->run([
            'typst', 'compile', '--root', $sourceDir, $sourceAbs, $pdf,
        ], dirname($sourceAbs), 90);
        $log = $process->getOutput()."\n".$process->getErrorOutput();
        $status = is_file($pdf) ? 'success' : 'failed';
        $this->writeLog($outputDir, $log);

        return [
            'status' => $status,
            'log' => $log,
            'has_pdf' => is_file($pdf),
            'compiler' => 'typst',
        ];
    }

    public function pdfPath(Project $project, User $user, string $relPath): ?string
    {
        $rel = $this->files->validateRelativePath($relPath);
        $pdf = $this->outputDir($project, $user, $rel).'/output.pdf';

        return is_file($pdf) ? $pdf : null;
    }

    private function ensureOutputDir(Project $project, User $user, string $relPath): string
    {
        $dir = $this->outputDir($project, $user, $relPath);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    private function wipeOutputDir(Project $project, User $user, string $relPath): void
    {
        $dir = $this->outputDir($project, $user, $relPath);
        if (! is_dir($dir)) {
            return;
        }
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iter as $entry) {
            $entry->isDir() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
        }
    }

    private function outputDir(Project $project, User $user, string $relPath): string
    {
        $hash = sha1($relPath);

        return storage_path('app/private/'.$project->userOutputPath($user->id, 'compile/'.$hash));
    }

    private function writeLog(string $outputDir, string $log): void
    {
        @file_put_contents($outputDir.'/compile.log', $log);
    }

    private function rString(string $s): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $s).'"';
    }

    private function run(array $cmd, string $cwd, int $timeout): Process
    {
        if (! empty($cmd) && ! str_contains($cmd[0], '/')) {
            $resolved = $this->locate($cmd[0]);
            if ($resolved !== null) {
                $cmd[0] = $resolved;
            }
        }
        $home = getenv('HOME') ?: '';
        if ($home === '' || ! is_writable($home)) {
            $home = sys_get_temp_dir().'/docs-compile';
        }
        $cacheDir = $home.'/.cache';
        @mkdir($cacheDir, 0775, true);
        $env = [
            'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
            'HOME' => $home,
            'XDG_CACHE_HOME' => $cacheDir,
        ];
        $process = new Process($cmd, $cwd, $env, null, $timeout);
        try {
            $process->run();
        } catch (\Throwable $e) {
            // surface a usable message in the log
        }

        return $process;
    }

    private function locate(string $bin): ?string
    {
        static $cache = [];
        if (array_key_exists($bin, $cache)) {
            return $cache[$bin];
        }
        $path = getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin';
        foreach (explode(PATH_SEPARATOR, $path) as $dir) {
            $candidate = rtrim($dir, '/').'/'.$bin;
            if (is_file($candidate) && is_executable($candidate)) {
                return $cache[$bin] = $candidate;
            }
        }

        return $cache[$bin] = null;
    }
}
