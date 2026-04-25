<?php

namespace App\Services\Compilers;

use App\Models\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LatexCompiler implements CompilerInterface
{
    public function compile(File $file, string $tempDir, array $options = []): array
    {
        $compiler = $options['compiler'] ?? 'pdflatex';
        $cmd = "/usr/bin/{$compiler}";
        
        // Werkmap is de ROOT van de workspace
        $workspacePath = $file->project->name . '/' . $file->getPath();
        
        // Forceer permissies via een LOKAAL configuratiebestand in de werkmap
        // TeX Live laadt texmf.cnf uit de huidige map met de hoogste prioriteit
        file_put_contents($tempDir . '/texmf.cnf', "openout_any = a\nopenin_any = a\n");
        
        $process = Process::path($tempDir)
            ->env([
                'HOME' => '/tmp', 
                'PATH' => '/usr/bin:/bin:/usr/local/bin',
                // TEXMFCNF op . zetten dwingt LaTeX om in de huidige map te kijken
                'TEXMFCNF' => $tempDir . ':',
            ])
            ->run("{$cmd} -interaction=nonstopmode " . escapeshellarg($workspacePath));
        
        $output = $process->output() ?: $process->errorOutput();
        
        $pdfName = pathinfo($workspacePath, PATHINFO_FILENAME) . '.pdf';
        $fullPdfPath = $tempDir . '/' . dirname($workspacePath) . '/' . $pdfName;
        $fullPdfPath = str_replace('/./', '/', $fullPdfPath);

        $url = null;
        $type = 'text';

        if (file_exists($fullPdfPath)) {
            $fileData = file_get_contents($fullPdfPath);
            if (str_starts_with($fileData, "%PDF-")) {
                $type = 'pdf';
                $publicPath = 'outputs/' . Str::random(20) . '.pdf';
                Storage::disk('public')->put($publicPath, $fileData);
                $url = Storage::url($publicPath);
            }
        }

        return [
            'type' => $type,
            'output' => $output,
            'url' => $url,
            'result' => null
        ];
    }
}
