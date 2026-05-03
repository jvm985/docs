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
        // clear plots from previous run
        $this->clearDir($plotDir);

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

        $env = [
            'HOME' => $sessionDir,
            'XDG_CACHE_HOME' => $sessionDir.'/.cache',
            'XDG_CONFIG_HOME' => $sessionDir.'/.config',
            'TMPDIR' => sys_get_temp_dir(),
            'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
        ];
        $this->ensureDir($env['XDG_CACHE_HOME']);
        $this->ensureDir($env['XDG_CONFIG_HOME']);

        $process = new Process(['Rscript', '--vanilla', $wrapperFile], $cwd, $env, null, 60);
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

    public function reset(Project $project, User $user): void
    {
        $sessionDir = $this->sessionDir($project, $user);
        if (is_dir($sessionDir)) {
            $this->clearDir($sessionDir.'/plots');
            @unlink($sessionDir.'/workspace.RData');
            @unlink($sessionDir.'/vars.json');
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
        $proj = $this->rString($projectDir);

        return <<<R
            local({
                tryCatch(setwd($proj), error = function(e) {})

                if (file.exists($ws)) {
                    tryCatch(load($ws, envir = .GlobalEnv), error = function(e) {})
                }

                options(device = function(...) png(
                    filename = file.path($plots, sprintf("plot-%03d.png",
                        length(list.files($plots, pattern = "\\\\.png\$")) + 1)),
                    width = 1600, height = 1200, res = 150))

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
            // append stderr that wasn't caught (often warnings)
            foreach (explode("\n", rtrim($stderr, "\n")) as $l) {
                if (trim($l) === '') {
                    continue;
                }
                $entries[] = ['type' => 'error', 'text' => $l];
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
