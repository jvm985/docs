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
        
        // We draaien de compiler nu vanuit de ROOT van de workspace
        // De paden worden dan: ProjectNaam/Pad/Naar/Bestand.tex
        $workspacePath = $file->project->name . '/' . $file->getPath();
        
        $process = Process::path($tempDir)
            ->env([
                'HOME' => '/tmp', 
                'PATH' => '/usr/bin:/bin:/usr/local/bin',
                'openout_any' => 'a',
                'openin_any' => 'a'
            ])
            ->run("{$cmd} -interaction=nonstopmode -cnf-line=\"openout_any=a\" " . escapeshellarg($workspacePath));
        
        $output = $process->output() ?: $process->errorOutput();
        
        // PDF wordt gegenereerd in de workspace root of in de projectmap afhankelijk van pdflatex versie
        // Maar meestal in de map van het bronbestand: ProjectNaam/Pad/Naar/Bestand.pdf
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
