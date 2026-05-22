<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use RuntimeException;
use Symfony\Component\Process\Process;

class CompileService
{
    public const SUPPORTED_LATEX_COMPILERS = ['pdflatex', 'xelatex', 'lualatex'];

    private ?string $pidFile = null;

    public function __construct(private FileService $files) {}

    /**
     * Path where the currently-running compile process writes its PID.
     * Used by CompileController::cancel to kill the process.
     */
    public function pidPath(Project $project, User $user): string
    {
        $dir = storage_path('app/private/projects/'.$project->id.'/users/'.$user->id.'/output');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir.'/compile.pid';
    }

    public function cancel(Project $project, User $user): bool
    {
        $path = $this->pidPath($project, $user);
        if (! is_file($path)) {
            return false;
        }
        $pid = (int) trim((string) @file_get_contents($path));
        if ($pid <= 0) {
            @unlink($path);

            return false;
        }
        // SIGTERM first, then SIGKILL after a short grace period.
        @posix_kill($pid, defined('SIGTERM') ? SIGTERM : 15);
        usleep(200_000);
        if (@posix_kill($pid, 0)) {
            @posix_kill($pid, defined('SIGKILL') ? SIGKILL : 9);
        }
        @unlink($path);

        return true;
    }

    public function canCompile(string $extension): bool
    {
        return in_array($extension, ['tex', 'md', 'rmd', 'rnw', 'rtex', 'typ'], true);
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
        $this->pidFile = $this->pidPath($project, $user);

        try {
            return match ($ext) {
                'tex' => $this->compileLatex($sourceDir, $sourceRel, $outputDir, $compiler ?? 'pdflatex'),
                'md' => $this->compileMarkdown($sourceDir, $sourceRel, $outputDir),
                'rmd' => $this->compileRmd($sourceDir, $sourceRel, $outputDir),
                'rnw', 'rtex' => $this->compileRnw($sourceDir, $sourceRel, $outputDir, $compiler ?? 'pdflatex'),
                'typ' => $this->compileTypst($sourceDir, $sourceRel, $outputDir),
                default => throw new RuntimeException('Unsupported.'),
            };
        } finally {
            if ($this->pidFile && is_file($this->pidFile)) {
                @unlink($this->pidFile);
            }
            $this->pidFile = null;
        }
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
        // Big documents with xelatex first-run can take long (font cache build).
        $process = $this->run(['Rscript', '-e', $script], dirname($sourceAbs), 600);
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

    private function compileRnw(string $sourceDir, string $sourceRel, string $outputDir, string $compiler): array
    {
        if (! in_array($compiler, self::SUPPORTED_LATEX_COMPILERS, true)) {
            throw new RuntimeException('Unsupported compiler.');
        }
        $sourceAbs = $sourceDir.'/'.$sourceRel;
        $pdf = $outputDir.'/output.pdf';
        @unlink($pdf);

        // knit_hooks per knit2pdf signature:
        //  knit2pdf(input, output, compiler, envir, quiet, clean) — kept defaults.
        // We chdir() inside R so relative file refs in the .Rnw resolve, then
        // copy the produced PDF to outputDir/output.pdf (knit2pdf names it
        // after the .Rnw basename).
        $base = pathinfo($sourceAbs, PATHINFO_FILENAME);
        $script = sprintf(
            'setwd(%s); knitr::knit2pdf(%s, compiler=%s, quiet=TRUE); '
            .'file.rename(paste0(%s, ".pdf"), file.path(%s, "output.pdf"))',
            $this->rString(dirname($sourceAbs)),
            $this->rString($sourceAbs),
            $this->rString($compiler),
            $this->rString($base),
            $this->rString($outputDir),
        );
        $process = $this->run(['Rscript', '-e', $script], dirname($sourceAbs), 600);
        $log = $process->getOutput()."\n".$process->getErrorOutput();

        // knit2pdf leaves the intermediate .tex next to the source — copy it
        // to outputDir so the user can inspect it via the existing log/PDF UI.
        $tex = dirname($sourceAbs).'/'.$base.'.tex';
        if (is_file($tex)) {
            @copy($tex, $outputDir.'/'.$base.'.tex');
        }

        $status = is_file($pdf) ? 'success' : 'failed';
        $this->writeLog($outputDir, $log);

        return [
            'status' => $status,
            'log' => $log,
            'has_pdf' => is_file($pdf),
            'compiler' => 'knitr+'.$compiler,
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
        $sharedLib = env('R_LIB_DIR') ?: storage_path('app/r-site-library');
        @mkdir($sharedLib, 0775, true);
        $env = [
            'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
            'HOME' => $home,
            'XDG_CACHE_HOME' => $cacheDir,
            'XDG_DATA_HOME' => '/usr/local/share',
            'R_LIBS_USER' => $sharedLib,
        ];
        $process = new Process($cmd, $cwd, $env, null, $timeout);
        try {
            // Start async so we can record the pid for /cancel; then wait().
            $process->start();
            $pid = $process->getPid();
            if ($pid !== null && $this->pidFile !== null) {
                @file_put_contents($this->pidFile, (string) $pid);
            }
            $process->wait();
        } catch (\Throwable $e) {
            // surface a usable message in the log
        }
        if ($this->pidFile !== null) {
            @unlink($this->pidFile);
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
