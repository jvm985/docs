<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Symfony\Component\Process\Process;

class RExecutionService
{
    public function __construct(private FileService $files) {}

    public function execute(Project $project, User $user, string $code, ?string $scriptPath = null): array
    {
        $sessionDir = $this->sessionDir($project, $user);
        $plotDir = $sessionDir.'/plots';
        $this->ensureDir($sessionDir);
        $this->ensureDir($plotDir);

        $workspaceFile = $sessionDir.'/workspace.RData';
        $codeFile = $sessionDir.'/run.R';
        @file_put_contents($codeFile, $code);

        $base = $this->files->basePath($project);
        $cwd = $base;
        if ($scriptPath !== null && trim($scriptPath) !== '') {
            try {
                $rel = $this->files->validateRelativePath($scriptPath);
                $candidate = $rel === '' ? $base : dirname($base.'/'.$rel);
                if (is_dir($candidate)) {
                    $cwd = $candidate;
                }
            } catch (\Throwable $e) {
                // fall back to project root
            }
        }
        $script = $this->buildWrapper($workspaceFile, $codeFile, $plotDir, $cwd);
        $wrapperFile = $sessionDir.'/wrapper.R';
        @file_put_contents($wrapperFile, $script);

        $sharedLib = $this->sharedLibPath();
        $this->ensureDir($sharedLib);
        $env = [
            'HOME' => $sessionDir,
            'XDG_CACHE_HOME' => $sessionDir.'/.cache',
            'XDG_CONFIG_HOME' => $sessionDir.'/.config',
            'TMPDIR' => sys_get_temp_dir(),
            'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
            'R_LIBS_USER' => $sharedLib,
        ];
        $this->ensureDir($env['XDG_CACHE_HOME']);
        $this->ensureDir($env['XDG_CONFIG_HOME']);

        $timeout = preg_match('/\binstall\.packages\s*\(/', $code) ? 600 : 60;
        $process = new Process(['Rscript', '--vanilla', $wrapperFile], $cwd, $env, null, $timeout);
        try {
            $process->run();
        } catch (\Throwable $e) {
            return [
                'output' => [
                    ['type' => 'error', 'text' => 'R execution failed: '.$e->getMessage()],
                ],
                'variables' => [],
                'plots' => [],
            ];
        }

        $stdout = $process->getOutput();
        $stderr = $process->getErrorOutput();
        $entries = $this->parseEntries($code, $stdout, $stderr);
        $vars = $this->loadVars($sessionDir);
        $plots = $this->collectPlots($project, $user, $plotDir);

        return [
            'output' => $entries,
            'variables' => $vars,
            'plots' => $plots,
        ];
    }

    /**
     * @param  callable(array{type:string,text:string}):void  $emit  Callback called with each output entry as it arrives.
     * @return array{variables:array,plots:array}
     */
    public function executeStreaming(Project $project, User $user, string $code, ?string $scriptPath, callable $emit): array
    {
        $sessionDir = $this->sessionDir($project, $user);
        $plotDir = $sessionDir.'/plots';
        $this->ensureDir($sessionDir);
        $this->ensureDir($plotDir);

        $workspaceFile = $sessionDir.'/workspace.RData';
        $codeFile = $sessionDir.'/run.R';
        @file_put_contents($codeFile, $code);

        $base = $this->files->basePath($project);
        $cwd = $base;
        if ($scriptPath !== null && trim($scriptPath) !== '') {
            try {
                $rel = $this->files->validateRelativePath($scriptPath);
                $candidate = $rel === '' ? $base : dirname($base.'/'.$rel);
                if (is_dir($candidate)) {
                    $cwd = $candidate;
                }
            } catch (\Throwable $e) {
            }
        }
        $script = $this->buildWrapper($workspaceFile, $codeFile, $plotDir, $cwd);
        $wrapperFile = $sessionDir.'/wrapper.R';
        @file_put_contents($wrapperFile, $script);

        $sharedLib = $this->sharedLibPath();
        $this->ensureDir($sharedLib);
        $env = [
            'HOME' => $sessionDir,
            'XDG_CACHE_HOME' => $sessionDir.'/.cache',
            'XDG_CONFIG_HOME' => $sessionDir.'/.config',
            'TMPDIR' => sys_get_temp_dir(),
            'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
            'R_LIBS_USER' => $sharedLib,
        ];
        $this->ensureDir($env['XDG_CACHE_HOME']);
        $this->ensureDir($env['XDG_CONFIG_HOME']);

        $timeout = preg_match('/\binstall\.packages\s*\(/', $code) ? 600 : 60;
        $cmd = is_executable('/usr/bin/stdbuf')
            ? ['/usr/bin/stdbuf', '-oL', '-eL', 'Rscript', '--vanilla', $wrapperFile]
            : ['Rscript', '--vanilla', $wrapperFile];
        $process = new Process($cmd, $cwd, $env, null, $timeout);
        $process->start();

        $stdoutBuf = '';
        $stderrBuf = '';
        $state = ['inside' => false, 'started' => false];

        while ($process->isRunning()) {
            $stdoutBuf .= $process->getIncrementalOutput();
            $stderrBuf .= $process->getIncrementalErrorOutput();
            $stdoutBuf = $this->drainStdout($stdoutBuf, $state, $emit, false);
            $stderrBuf = $this->drainStderr($stderrBuf, $emit, false);
            usleep(100_000);
        }
        $stdoutBuf .= $process->getIncrementalOutput();
        $stderrBuf .= $process->getIncrementalErrorOutput();
        $this->drainStdout($stdoutBuf, $state, $emit, true);
        $this->drainStderr($stderrBuf, $emit, true);

        if (! $state['started']) {
            // Wrapper never reached __BEGIN_R__: surface a generic failure.
            $err = trim($process->getErrorOutput()) ?: 'R execution failed';
            $emit(['type' => 'error', 'text' => $err]);
        }

        return [
            'variables' => $this->loadVars($sessionDir),
            'plots' => $this->collectPlots($project, $user, $plotDir),
        ];
    }

    private function drainStdout(string $buf, array &$state, callable $emit, bool $flushFinal): string
    {
        $nl = strrpos($buf, "\n");
        if ($nl === false) {
            return $flushFinal ? $this->processStdoutLine($buf, $state, $emit) ?? '' : $buf;
        }
        $complete = substr($buf, 0, $nl);
        $rest = substr($buf, $nl + 1);
        foreach (explode("\n", $complete) as $line) {
            $this->processStdoutLine($line, $state, $emit);
        }
        if ($flushFinal && $rest !== '') {
            $this->processStdoutLine($rest, $state, $emit);
            $rest = '';
        }

        return $rest;
    }

    private function processStdoutLine(string $line, array &$state, callable $emit): ?string
    {
        if ($line === '') {
            return null;
        }
        if (str_starts_with($line, '__BEGIN_R__')) {
            $state['inside'] = true;
            $state['started'] = true;

            return null;
        }
        if (str_starts_with($line, '__END_R__')) {
            $state['inside'] = false;

            return null;
        }
        if (str_starts_with($line, '__LINE__')) {
            $emit(['type' => 'code', 'text' => substr($line, strlen('__LINE__'))]);

            return null;
        }
        if (str_starts_with($line, '__ERROR__')) {
            $emit(['type' => 'error', 'text' => substr($line, strlen('__ERROR__'))]);

            return null;
        }
        $emit(['type' => 'output', 'text' => $line]);

        return null;
    }

    private function drainStderr(string $buf, callable $emit, bool $flushFinal): string
    {
        $nl = strrpos($buf, "\n");
        if ($nl === false) {
            if ($flushFinal && $buf !== '') {
                $this->emitStderrLine($buf, $emit);
            }

            return $flushFinal ? '' : $buf;
        }
        $complete = substr($buf, 0, $nl);
        $rest = substr($buf, $nl + 1);
        foreach (explode("\n", $complete) as $line) {
            if (trim($line) !== '') {
                $this->emitStderrLine($line, $emit);
            }
        }
        if ($flushFinal && $rest !== '') {
            $this->emitStderrLine($rest, $emit);
            $rest = '';
        }

        return $rest;
    }

    private function emitStderrLine(string $line, callable $emit): void
    {
        $isError = (bool) preg_match('/^(Error|Fatal|fout):/i', ltrim($line));
        $emit(['type' => $isError ? 'error' : 'output', 'text' => $line]);
    }

    public function inspect(Project $project, User $user, string $name, int $limit = 1000): array
    {
        $sessionDir = $this->sessionDir($project, $user);
        $workspaceFile = $sessionDir.'/workspace.RData';
        if (! is_file($workspaceFile)) {
            throw new \RuntimeException('Geen R-sessie gevonden. Run eerst R-code.');
        }
        if (! preg_match('/^[A-Za-z._][A-Za-z0-9._]*$/', $name)) {
            throw new \RuntimeException('Ongeldige variabelenaam.');
        }
        $sharedLib = $this->sharedLibPath();
        $this->ensureDir($sharedLib);
        $script = <<<R
            local({
                load({$this->rString($workspaceFile)}, envir = environment())
                if (!exists("{$name}", inherits = FALSE)) {
                    cat('{"error":"variabele niet gevonden"}'); return(invisible())
                }
                v <- get("{$name}")
                if (!is.data.frame(v)) {
                    cat('{"error":"niet een data frame"}'); return(invisible())
                }
                n <- nrow(v); take <- min(n, {$limit}L)
                v2 <- v[seq_len(take), , drop = FALSE]
                payload <- list(
                    name = "{$name}",
                    n_rows = n,
                    n_cols = ncol(v2),
                    truncated = n > take,
                    columns = colnames(v2),
                    types = vapply(v2, function(x) class(x)[1], character(1)),
                    rows = lapply(seq_len(nrow(v2)), function(i) {
                        lapply(seq_len(ncol(v2)), function(j) {
                            x <- v2[i, j]
                            if (is.factor(x)) x <- as.character(x)
                            if (is.null(x) || (length(x) == 1 && is.na(x))) NA else x
                        })
                    })
                )
                if (requireNamespace("jsonlite", quietly = TRUE)) {
                    cat(jsonlite::toJSON(payload, na = "null", auto_unbox = TRUE))
                } else {
                    cat('{"error":"jsonlite ontbreekt"}')
                }
            })
            R;
        $env = [
            'HOME' => $sessionDir,
            'XDG_CACHE_HOME' => $sessionDir.'/.cache',
            'TMPDIR' => sys_get_temp_dir(),
            'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
            'R_LIBS_USER' => $sharedLib,
        ];
        $process = new Process(['Rscript', '--vanilla', '-e', $script], $sessionDir, $env, null, 30);
        $process->run();
        $out = trim($process->getOutput());
        $data = json_decode($out, true);
        if (! is_array($data)) {
            throw new \RuntimeException('Kon variabele niet inlezen: '.($process->getErrorOutput() ?: substr($out, 0, 200)));
        }
        if (isset($data['error'])) {
            throw new \RuntimeException($data['error']);
        }

        return $data;
    }

    public function reset(Project $project, User $user): void
    {
        $sessionDir = $this->sessionDir($project, $user);
        if (is_dir($sessionDir)) {
            $this->clearDir($sessionDir.'/plots');
            @unlink($sessionDir.'/workspace.RData');
            @unlink($sessionDir.'/vars.json');
            @unlink($sessionDir.'/attached.txt');
        }
    }

    public function plotPath(Project $project, User $user, string $name): ?string
    {
        $sessionDir = $this->sessionDir($project, $user);
        $name = basename($name);
        $path = $sessionDir.'/plots/'.$name;

        return is_file($path) ? $path : null;
    }

    public function state(Project $project, User $user): array
    {
        $sessionDir = $this->sessionDir($project, $user);
        $vars = $this->loadVars($sessionDir);
        $plots = $this->collectPlots($project, $user, $sessionDir.'/plots');

        return [
            'variables' => $vars,
            'plots' => $plots,
        ];
    }

    private function buildWrapper(string $workspaceFile, string $codeFile, string $plotDir, string $projectDir): string
    {
        $ws = $this->rString($workspaceFile);
        $code = $this->rString($codeFile);
        $plots = $this->rString($plotDir);
        $varsOut = $this->rString(dirname($workspaceFile).'/vars.json');
        $pkgFile = $this->rString(dirname($workspaceFile).'/attached.txt');
        $proj = $this->rString($projectDir);

        return <<<R
            local({
                tryCatch(setwd($proj), error = function(e) {})

                if (file.exists($ws)) {
                    tryCatch(load($ws, envir = .GlobalEnv), error = function(e) {})
                }

                if (file.exists($pkgFile)) {
                    pkgs <- tryCatch(readLines($pkgFile, warn = FALSE), error = function(e) character(0))
                    for (p in pkgs) {
                        if (nzchar(p)) tryCatch(suppressPackageStartupMessages(library(p, character.only = TRUE)), error = function(e) {})
                    }
                }

                options(device = function(...) png(
                    filename = file.path($plots, sprintf("plot-%03d.png",
                        length(list.files($plots, pattern = "\\\\.png\$")) + 1)),
                    width = 1600, height = 1200, res = 150))

                # Each plot in its own file: close current device before a new plot starts
                # so dev.off() flushes the previous bitmap, then the device factory opens
                # a fresh png with the next sequence number. Trace both base graphics'
                # plot.new and grid's grid.newpage (used by ggplot/lattice).
                .new_page <- quote({
                    if (length(dev.list()) > 0) try(dev.off(), silent = TRUE)
                })
                suppressMessages(tryCatch(
                    trace(graphics::plot.new, tracer = .new_page, print = FALSE),
                    error = function(e) {}
                ))
                suppressMessages(tryCatch(
                    trace(grid::grid.newpage, tracer = .new_page, print = FALSE),
                    error = function(e) {}
                ))

                cat("__BEGIN_R__\\n")
                tryCatch({
                    src <- readLines($code, warn = FALSE)
                    expr <- parse(text = paste(src, collapse = "\\n"))
                    for (i in seq_along(expr)) {
                        e <- expr[[i]]
                        cat("__LINE__", deparse(e, width.cutoff = 500L)[1], "\\n", sep = "")
                        val <- withVisible(eval(e, envir = .GlobalEnv))
                        if (val\$visible) {
                            print(val\$value)
                        }
                    }
                }, error = function(e) {
                    cat("__ERROR__", conditionMessage(e), "\\n", sep = "")
                })
                cat("__END_R__\\n")

                while (length(dev.list()) > 0) tryCatch(dev.off(), error = function(e) {})

                save.image($ws)

                tryCatch({
                    defaults <- c("base","stats","graphics","grDevices","utils","datasets","methods")
                    extra <- setdiff(.packages(), defaults)
                    writeLines(extra, $pkgFile)
                }, error = function(e) {})

                vars <- ls(envir = .GlobalEnv)
                summaries <- lapply(vars, function(name) {
                    v <- tryCatch(get(name, envir = .GlobalEnv), error = function(e) NULL)
                    cls <- tryCatch(class(v)[1], error = function(e) "unknown")
                    preview <- tryCatch({
                        s <- capture.output(str(v, max.level = 1, vec.len = 4))[1]
                        if (is.null(s)) "" else substr(s, 1, 80)
                    }, error = function(e) "")
                    list(name = name, class = cls, preview = preview)
                })
                if (requireNamespace("jsonlite", quietly = TRUE)) {
                    writeLines(jsonlite::toJSON(summaries, auto_unbox = TRUE), $varsOut)
                } else {
                    lines <- vapply(summaries, function(s) {
                        sprintf('{"name":"%s","class":"%s","preview":"%s"}',
                            gsub('"', '\\\\"', s\$name),
                            gsub('"', '\\\\"', s\$class),
                            gsub('"', '\\\\"', s\$preview))
                    }, character(1))
                    writeLines(paste0('[', paste(lines, collapse = ','), ']'), $varsOut)
                }
            })
            R;
    }

    private function parseEntries(string $code, string $stdout, string $stderr): array
    {
        $entries = [];
        // extract the section between __BEGIN_R__ and __END_R__
        $start = strpos($stdout, "__BEGIN_R__\n");
        $end = strpos($stdout, "__END_R__\n");
        if ($start === false) {
            // execution never even started
            $msg = trim($stderr) !== '' ? $stderr : $stdout;
            if (trim($msg) !== '') {
                $entries[] = ['type' => 'error', 'text' => $msg];
            }

            return $entries;
        }
        $body = substr($stdout, $start + strlen("__BEGIN_R__\n"));
        if ($end !== false) {
            $body = substr($stdout, $start + strlen("__BEGIN_R__\n"), $end - ($start + strlen("__BEGIN_R__\n")));
        }
        $lines = explode("\n", rtrim($body, "\n"));
        $current = null;
        foreach ($lines as $line) {
            if (str_starts_with($line, '__LINE__')) {
                $current = substr($line, strlen('__LINE__'));
                $entries[] = ['type' => 'code', 'text' => $current];

                continue;
            }
            if (str_starts_with($line, '__ERROR__')) {
                $entries[] = ['type' => 'error', 'text' => substr($line, strlen('__ERROR__'))];

                continue;
            }
            if ($line === '') {
                continue;
            }
            $entries[] = ['type' => 'output', 'text' => $line];
        }
        if (trim($stderr) !== '') {
            // R writes install/download progress, package startup messages and
            // many notes to stderr. Only treat lines that look like real R
            // errors as 'error'; everything else surfaces as plain output.
            foreach (explode("\n", rtrim($stderr, "\n")) as $l) {
                if (trim($l) === '') {
                    continue;
                }
                $isError = (bool) preg_match('/^(Error|Fatal|fout):/i', ltrim($l));
                $entries[] = ['type' => $isError ? 'error' : 'output', 'text' => $l];
            }
        }

        return $entries;
    }

    private function loadVars(string $sessionDir): array
    {
        $file = $sessionDir.'/vars.json';
        if (! is_file($file)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) ? array_values($data) : [];
    }

    private function collectPlots(Project $project, User $user, string $dir): array
    {
        if (! is_dir($dir)) {
            return [];
        }
        $files = glob($dir.'/*.png') ?: [];
        sort($files);
        $urls = [];
        foreach ($files as $f) {
            $urls[] = route('editor.plot', [
                'project' => $project->id,
                'name' => basename($f),
                'v' => filemtime($f) ?: time(),
            ]);
        }

        return $urls;
    }

    public function sessionDir(Project $project, User $user): string
    {
        return storage_path('app/private/'.$project->userRSessionPath($user->id));
    }

    public function sharedLibPath(): string
    {
        return env('R_LIB_DIR') ?: storage_path('app/r-site-library');
    }

    private function ensureDir(string $dir): void
    {
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }

    private function clearDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (glob($dir.'/*') ?: [] as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
    }

    private function rString(string $s): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $s).'"';
    }
}
