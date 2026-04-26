<?php

namespace App\Services\Compilers;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class RCompiler implements CompilerInterface
{
    public function compile(string $mainFilePath, string $workspaceDir): array
    {
        $sessionToken = Str::random(10);
        $plotDir = storage_path('app/public/r_plots/' . $sessionToken);
        if (!is_dir($plotDir)) mkdir($plotDir, 0777, true);

        // De R-code die we echt gaan draaien, gewikkeld in een capture-laag
        $wrapperScript = "
            options(device = function() {
                png(file.path('$plotDir', 'plot_%03d.png'), width = 800, height = 600)
            })
            
            # Voer de user code uit
            tryCatch({
                source('$mainFilePath', local = FALSE, echo = TRUE)
            }, error = function(e) {
                cat('\nERROR_MARKER\n')
                cat(conditionMessage(e))
            })
            
            # Vang variabelen
            cat('\nVARIABLES_MARKER\n')
            vars <- ls(all.names = TRUE)
            for (v in vars) {
                val <- get(v)
                if (!is.function(val)) {
                    cat(paste0(v, ':', class(val)[1], ':', substr(paste(capture.output(print(val)), collapse=' '), 1, 100), '\n'))
                }
            }
            
            dev.off()
        ";

        $tmpWrapper = tempnam(sys_get_temp_dir(), 'r_wrap');
        file_put_contents($tmpWrapper, $wrapperScript);

        $process = Process::path($workspaceDir)
            ->run([
                'Rscript',
                $tmpWrapper
            ]);

        $fullOutput = $process->output() . "\n" . $process->errorOutput();
        
        // Parsen van de output
        $structured = [];
        $variables = [];
        $plots = [];

        // Zoek gegenereerde plots
        $plotFiles = glob($plotDir . '/*.png');
        foreach ($plotFiles as $file) {
            $plots[] = '/storage/r_plots/' . $sessionToken . '/' . basename($file);
        }

        // Simpele parser voor de structured output (code vs output)
        $lines = explode("\n", $fullOutput);
        $mode = 'output';
        foreach ($lines as $line) {
            if (str_contains($line, 'VARIABLES_MARKER')) {
                $mode = 'vars'; continue;
            }
            if (str_contains($line, 'ERROR_MARKER')) {
                $mode = 'error'; continue;
            }
            
            if ($mode === 'vars') {
                $parts = explode(':', $line, 3);
                if (count($parts) === 3) {
                    $variables[] = ['name' => $parts[0], 'type' => $parts[1], 'value' => $parts[2]];
                }
            } elseif ($mode === 'error') {
                $structured[] = ['type' => 'error', 'content' => $line];
            } else {
                $type = str_starts_with($line, '> ') ? 'code' : 'output';
                $content = str_starts_with($line, '> ') ? substr($line, 2) : $line;
                if (!empty(trim($content))) {
                    $structured[] = ['type' => $type, 'content' => $content];
                }
            }
        }

        unlink($tmpWrapper);

        return [
            'type' => 'r',
            'output' => $fullOutput,
            'url' => null,
            'result' => [
                'structured_output' => $structured,
                'variables' => $variables,
                'plots' => $plots
            ],
            'success' => $process->successful()
        ];
    }
}
