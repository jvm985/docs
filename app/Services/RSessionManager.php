<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Manages persistent R sessions per user using rscript with a shared workspace.
 * Each user gets a dedicated workspace directory where their .RData is persisted.
 */
class RSessionManager
{
    public function execute(User $user, string $code): void
    {
        $workspaceDir = $this->workspaceDir($user);
        $this->ensureWorkspaceExists($workspaceDir);

        $script = $this->buildScript($workspaceDir, $code);
        $scriptFile = $workspaceDir.'/run_'.uniqid().'.R';
        file_put_contents($scriptFile, $script);

        $cmd = sprintf('Rscript --no-restore %s 2>&1', escapeshellarg($scriptFile));
        exec($cmd, $outputLines, $exitCode);
        @unlink($scriptFile);

        $output = implode("\n", $outputLines);

        // Parse output sections (code echo, result, errors, variables, plots)
        $this->dispatchROutput($user, $code, $output, $workspaceDir);
    }

    private function buildScript(string $workspaceDir, string $code): string
    {
        $dataFile = $workspaceDir.'/.RData';
        $plotDir = $workspaceDir.'/plots';
        $escapedCode = str_replace(['\\', '"'], ['\\\\', '\\"'], $code);

        return <<<RSCRIPT
# Load previous session
dataFile <- "{$dataFile}"
plotDir <- "{$plotDir}"
if (file.exists(dataFile)) load(dataFile)

# Capture plots
dir.create(plotDir, showWarnings = FALSE, recursive = TRUE)
plot_count <- length(list.files(plotDir, pattern = "[.]png\$"))
png_device <- function() {
    plot_count <<- plot_count + 1
    png(filename = file.path(plotDir, paste0("plot_", plot_count, ".png")), width = 800, height = 600)
}
options(device = png_device)

# Execute user code line by line
.user_lines <- strsplit("{$escapedCode}", "\\n")[[1]]
for (.line in .user_lines) {
    .line <- trimws(.line)
    if (nchar(.line) == 0) next
    cat("__LINE_CODE__", .line, "\\n")
    tryCatch({
        .out <- capture.output(.result <- eval(parse(text = .line)))
        if (length(.out) > 0) {
            for (.o in .out) cat("__LINE_OUT__", .o, "\\n")
        } else if (!is.null(.result) && class(.result) != "function") {
            .printed <- capture.output(print(.result))
            for (.o in .printed) cat("__LINE_OUT__", .o, "\\n")
        }
    }, error = function(e) {
        cat("__LINE_ERR__", conditionMessage(e), "\\n")
    }, warning = function(w) {
        cat("__LINE_WARN__", conditionMessage(w), "\\n")
    })
}

# List environment variables
cat("__VARS_START__\\n")
vars <- ls(envir = .GlobalEnv)
for (v in vars) {
    val <- get(v, envir = .GlobalEnv)
    cat(v, "|", class(val)[1], "|", paste(utils::capture.output(str(val)), collapse = " "), "\\n")
}
cat("__VARS_END__\\n")

# Save session
save.image(file = dataFile)
while (dev.cur() > 1) dev.off()
RSCRIPT;
    }

    private function dispatchROutput(User $user, string $code, string $rawOutput, string $workspaceDir): void
    {
        // Parse sections from output
        $entries = [];

        // Parse line-by-line output (code + response interleaved)
        foreach (explode("\n", $rawOutput) as $line) {
            if (preg_match('/^__LINE_CODE__ (.*)$/', $line, $m)) {
                $entries[] = ['type' => 'code', 'text' => $m[1]];
            } elseif (preg_match('/^__LINE_OUT__ (.*)$/', $line, $m)) {
                $entries[] = ['type' => 'output', 'text' => $m[1]];
            } elseif (preg_match('/^__LINE_ERR__ (.*)$/', $line, $m)) {
                $entries[] = ['type' => 'error', 'text' => $m[1]];
            } elseif (preg_match('/^__LINE_WARN__ (.*)$/', $line, $m)) {
                $entries[] = ['type' => 'error', 'text' => 'Warning: '.$m[1]];
            }
        }

        // Store output in cache for Livewire to pick up via polling/events
        $cacheKey = "r_output_{$user->id}";
        $existing = Cache::get($cacheKey, []);
        Cache::put($cacheKey, array_merge($existing, $entries), now()->addHour());

        // Variables (filter interne script-variabelen)
        $variables = [];
        $internalVars = ['dataFile', 'plotDir', 'plot_count', 'png_device', 'result', '.captured_output', 'v', 'val', 'vars'];
        if (preg_match('/__VARS_START__\n(.*?)\n__VARS_END__/s', $rawOutput, $m)) {
            foreach (explode("\n", trim($m[1])) as $line) {
                if (empty(trim($line))) {
                    continue;
                }
                $parts = explode('|', $line, 3);
                if (count($parts) === 3 && ! in_array(trim($parts[0]), $internalVars)) {
                    $variables[] = [
                        'name' => trim($parts[0]),
                        'class' => trim($parts[1]),
                        'preview' => trim($parts[2]),
                    ];
                }
            }
        }
        Cache::put("r_vars_{$user->id}", $variables, now()->addHour());

        // Plots
        $plotDir = $workspaceDir.'/plots';
        $plots = [];
        if (is_dir($plotDir)) {
            foreach (glob($plotDir.'/*.png') as $plotFile) {
                $plots[] = 'data:image/png;base64,'.base64_encode(file_get_contents($plotFile));
            }
        }
        if (! empty($plots)) {
            Cache::put("r_plots_{$user->id}", $plots, now()->addHour());
        }
    }

    private function workspaceDir(User $user): string
    {
        return storage_path("app/r_sessions/{$user->id}");
    }

    private function ensureWorkspaceExists(string $dir): void
    {
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    public function clearSession(User $user): void
    {
        $workspaceDir = $this->workspaceDir($user);
        $dataFile = $workspaceDir.'/.RData';
        if (file_exists($dataFile)) {
            @unlink($dataFile);
        }

        Cache::forget("r_output_{$user->id}");
        Cache::forget("r_vars_{$user->id}");
        Cache::forget("r_plots_{$user->id}");
    }
}
