<?php

namespace App\Services\Compilers;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Process;

class TypstCompiler implements CompilerInterface
{
    public function compile(string $mainFilePath, string $workspaceDir): array
    {
        $outputFileName = Str::random(20) . '.pdf';
        $outputDir = storage_path('app/public/outputs');

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        $outputPath = $outputDir . '/' . $outputFileName;

        // Typst is van zichzelf al heel snel, maar we draaien hem nu in de persistente map
        // zodat alle lokale fonts en includes direct beschikbaar zijn.
        $process = Process::path($workspaceDir)
            ->run([
                'typst',
                'compile',
                $mainFilePath,
                $outputPath
            ]);

        if ($process->successful() && file_exists($outputPath)) {
            return [
                'type' => 'pdf',
                'url' => '/storage/outputs/' . $outputFileName,
                'output' => $process->output(),
                'result' => true
            ];
        }

        return [
            'type' => 'text',
            'output' => $process->output() . "\n" . $process->errorOutput(),
            'url' => null,
            'result' => false
        ];
    }
}
