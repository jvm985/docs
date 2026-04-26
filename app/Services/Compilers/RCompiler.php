<?php

namespace App\Services\Compilers;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Process;

class RCompiler implements CompilerInterface
{
    public function compile(string $mainFilePath, string $workspaceDir): array
    {
        // Voer de R-code direct uit en vang de output op
        $process = Process::path($workspaceDir)
            ->run([
                'Rscript',
                $mainFilePath
            ]);

        $output = $process->output() . "\n" . $process->errorOutput();

        return [
            'type' => 'r',
            'output' => $output,
            'url' => null,
            'result' => $output, // Show.vue verwacht het resultaat hier
            'success' => $process->successful()
        ];
    }
}
