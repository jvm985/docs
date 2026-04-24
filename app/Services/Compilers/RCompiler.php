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
        $codeToRun = $options['code'] ?? $file->content;
        file_put_contents($tempDir . '/user_code.R', $codeToRun);
        
        $sessionFile = storage_path("app/r_sessions/project_{$file->project_id}.RData");
        if (!file_exists(dirname($sessionFile))) {
            mkdir(dirname($sessionFile), 0777, true);
        }

        $wrapper = $this->generateWrapper($sessionFile);
        file_put_contents($tempDir . '/wrapper.R', $wrapper);

        $process = Process::path($tempDir)
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
        // We use .app_ internally to stay hidden from ls()
        return "
options(warn=-1)
library(jsonlite)

if (file.exists('{$sessionFile}')) {
    load('{$sessionFile}', envir = .GlobalEnv)
}

png('plot_%03d.png')

.app_out <- list()

tryCatch({
    # Parse the code into expressions
    .app_exprs <- parse('user_code.R')
    
    if (length(.app_exprs) > 0) {
        for (.app_i in seq_along(.app_exprs)) {
            .app_curr <- .app_exprs[.app_i]
            
            # Record code line
            .app_text <- paste(deparse(.app_curr[[1]]), collapse='\\n')
            .app_out <- c(.app_out, list(list(type='code', content=.app_text)))
            
            # Capture output
            .app_tmp <- tempfile()
            sink(.app_tmp)
            tryCatch({
                .app_res <- withVisible(eval(.app_curr, envir = .GlobalEnv))
                if (.app_res\$visible) {
                    print(.app_res\$value)
                }
            }, error = function(e) {
                cat('Error:', e\$message, '\\n')
            })
            sink()
            
            .app_captured <- readLines(.app_tmp)
            if (length(.app_captured) > 0) {
                .app_out <- c(.app_out, list(list(type='output', content=paste(.app_captured, collapse='\\n'))))
            }
            unlink(.app_tmp)
        }
    }
}, error = function(e) {
    .app_out <- c(.app_out, list(list(type='error', content=e\$message)))
})

dev.off()

# Save user variables (ls returns only non-hidden ones by default)
.app_user_vars <- ls(envir = .GlobalEnv, all.names = FALSE)
save(list = .app_user_vars, file = '{$sessionFile}', envir = .GlobalEnv)

# Prepare variable metadata
.app_var_data <- lapply(.app_user_vars, function(x) {
    .app_v <- get(x, envir = .GlobalEnv)
    list(
        name = x, 
        type = class(.app_v)[1], 
        value = paste(capture.output(print(.app_v)), collapse='\\n')
    )
})

write_json(list(output=.app_out, variables=.app_var_data), 'result.json', auto_unbox = TRUE)
";
    }

    private function collectPlots($tempDir): array
    {
        $plots = [];
        foreach (glob($tempDir . '/plot_*.png') as $plotFile) {
            $pPath = 'outputs/' . Str::random(20) . '.png';
            Storage::disk('public')->put($pPath, file_get_contents($plotFile));
            $plots[] = Storage::url($pPath);
        }
        return $plots;
    }
}
