<?php

namespace App\Services\Compilers;

use App\Models\File;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Process;

class LatexCompiler implements CompilerInterface
{
    public function compile(string $mainFilePath, string $workspaceDir): array
    {
        $projectDir = dirname($mainFilePath);
        $outputFileName = Str::random(20) . '.pdf';
        $outputDir = storage_path('app/public/outputs');

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        // 1. Zorg voor een lokale texmf.cnf voor veiligheid in de project map
        $this->ensureTexmfConfig($projectDir);

        // 2. Symlink fonts lokaal voor XeLaTeX ontdekking
        $this->linkFonts($projectDir);

        // 3. Voer de compilatie uit
        $process = Process::path($projectDir)
            ->env([
                'TEXMFCONFIG' => $projectDir . '/texmf',
                'TEXMFVAR' => $projectDir . '/texmf',
                'OPENOUT_ANY' => 'a',
                'OPENIN_ANY' => 'a',
                'TEXINPUTS' => ".:$workspaceDir:"
            ])
            ->run([
                'latexmk',
                '-xelatex',
                '-interaction=nonstopmode',
                '-shell-escape',
                basename($mainFilePath)
            ]);
        $log = $process->output() . "\n" . $process->errorOutput();
        
        // Zoek de gegenereerde PDF (heeft dezelfde naam als de main file)
        $expectedPdfPath = str_replace('.tex', '.pdf', $mainFilePath);

        if ($process->successful() && file_exists($expectedPdfPath)) {
            copy($expectedPdfPath, $outputDir . '/' . $outputFileName);
            return [
                'type' => 'pdf',
                'url' => '/storage/outputs/' . $outputFileName,
                'output' => $log,
                'result' => true
            ];
        }

        return [
            'type' => 'text',
            'output' => $log,
            'url' => null,
            'result' => false
        ];
    }

    private function ensureTexmfConfig(string $workspaceDir): void
    {
        $texmfDir = $workspaceDir . '/texmf';
        if (!is_dir($texmfDir)) {
            mkdir($texmfDir, 0777, true);
        }
        $cnfFile = $texmfDir . '/texmf.cnf';
        if (!file_exists($cnfFile)) {
            file_put_contents($cnfFile, "openout_any = a\nopenin_any = a\n");
        }
    }

    private function linkFonts(string $workspaceDir): void
    {
        // Alleen linken als het nog niet bestaat
        if (!is_dir($workspaceDir . '/fonts')) {
             @symlink('/usr/share/fonts/truetype/Quicksand/static', $workspaceDir . '/fonts');
        }
    }
}
