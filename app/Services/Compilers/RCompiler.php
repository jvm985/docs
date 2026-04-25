<?php

namespace App\Services\Compilers;

use App\Models\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RCompiler implements CompilerInterface
{
    public function compile(File $file, string $tempDir, array $options = []): array
    {
        $projectDir = $tempDir . '/' . $file->project->name;
        $codeToRun = $options['code'] ?? $file->content;
        file_put_contents($projectDir . '/user_code.R', $codeToRun);
        
        $sessionFile = storage_path("app/r_sessions/project_{$file->project_id}.RData");
        if (!file_exists(dirname($sessionFile))) {
            mkdir(dirname($sessionFile), 0777, true);
        }

        $wrapper = $this->generateWrapper($sessionFile);
        file_put_contents($projectDir . '/wrapper.R', $wrapper);

        $process = Process::path($projectDir)
            ->env(['HOME' => '/tmp', 'PATH' => '/usr/bin:/bin:/usr/local/bin'])
            ->run("/usr/bin/Rscript wrapper.R");
        
        $rawOutput = $process->output() ?: $process->errorOutput();
        
        $resultJson = null;
        if (file_exists($tempDir . '/result.json')) {
            $jsonContent = file_get_contents($tempDir . '/result.json');
            $resultJson = json_decode($jsonContent, true);
        }
        
        $plots = $this->collectPlots($tempDir);
        
        $structuredOutput = $resultJson['output'] ?? [];
        if (empty($structuredOutput) && !empty($rawOutput)) {
            $structuredOutput[] = ['type' => 'output', 'content' => $rawOutput];
        }

        return [
            'type' => 'r',
            'output' => $rawOutput,
            'result' => [
                'structured_output' => $structuredOutput,
                'plots' => $plots,
                'variables' => $resultJson['variables'] ?? []
            ]
        ];
    }

    private function generateWrapper($sessionFile): string
    {
        return "
options(warn=-1)
library(jsonlite)

if (file.exists('{$sessionFile}')) {
    load('{$sessionFile}', envir = .GlobalEnv)
}

# RADICAL CLEANUP of ANY internal variable names that might have leaked
.internal_names <- c('i', 'exprs', 'config', 'code_text', 'expr', 'res', 'out', 'out_lines', 'tmp_out_file', 'val', 'code_to_run', 'run_with_capture', 'structured_output', 'app_out', 'app_res', 'app_l', 'app_tmp', 'app_val', 'app_eval_res', 'app_captured', 'app_res_lines', 'app_user_vars', 'app_var_data', 'code_lines', 'tmp_out', 'captured', 'var_metadata', 'f', 'line', 'lines', 'res_lines', 'app_internal_env', 'curr', 'txt', 'tmpf', 'cap', 'var_list', 'v')
rm(list = intersect(.internal_names, ls(envir = .GlobalEnv, all.names = TRUE)), envir = .GlobalEnv)

png('plot_%03d.png')

.app_structured_output <- list()

tryCatch({
    .app_exprs <- parse('user_code.R')
    if (length(.app_exprs) > 0) {
        for (.app_i in seq_along(.app_exprs)) {
            .app_curr_expr <- .app_exprs[.app_i]
            
            # Record code
            .app_code_text <- paste(deparse(.app_curr_expr[[1]]), collapse='\\n')
            .app_structured_output <- c(.app_structured_output, list(list(type='code', content=.app_code_text)))
            
            # Capture output
            .app_tmpf <- tempfile()
            sink(.app_tmpf)
            tryCatch({
                .app_res_obj <- withVisible(eval(.app_curr_expr, envir = .GlobalEnv))
                if (.app_res_obj\$visible) print(.app_res_obj\$value)
            }, error = function(e) {
                cat('Error:', e\$message, '\\n')
            })
            sink()
            
            .app_res_lines <- readLines(.app_tmpf)
            if (length(.app_res_lines) > 0) {
                .app_structured_output <- c(.app_structured_output, list(list(type='output', content=paste(.app_res_lines, collapse='\\n'))))
            }
            unlink(.app_tmpf)
        }
    }
}, error = function(e) {
    .app_structured_output <- c(.app_structured_output, list(list(type='error', content=e\$message)))
})

dev.off()

# Save user variables
.app_user_vars <- ls(envir = .GlobalEnv, all.names = FALSE)
save(list = .app_user_vars, file = '{$sessionFile}', envir = .GlobalEnv)

# Prepare variable metadata
.app_var_metadata <- lapply(.app_user_vars, function(x) {
    .app_val_obj <- get(x, envir = .GlobalEnv)
    list(
        name = x, 
        type = class(.app_val_obj)[1], 
        value = paste(capture.output(print(.app_val_obj)), collapse='\\n')
    )
})

write_json(list(output=.app_structured_output, variables=.app_var_metadata), 'result.json', auto_unbox = TRUE)
";
    }

    private function collectPlots($projectDir): array
    {
        $plots = [];
        foreach (glob($projectDir . '/plot_*.png') as $plotFile) {
            $pPath = 'outputs/' . Str::random(20) . '.png';
            Storage::disk('public')->put($pPath, file_get_contents($plotFile));
            $plots[] = Storage::url($pPath);
        }
        return $plots;
    }
}
