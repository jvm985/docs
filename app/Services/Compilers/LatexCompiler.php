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
        
        // Werkmap is het project binnen de workspace van de huidige gebruiker
        $projectDir = $tempDir . '/' . $file->project->name;
        $relativePath = $file->getPath();
        
        // Forceer LaTeX om schrijven naar ../ mappen toe te staan via de shell command
        // We voegen openout_any=a direct toe aan de opdrachtregel
        $process = Process::path($projectDir)
            ->env([
                'HOME' => '/tmp', 
                'PATH' => '/usr/bin:/bin:/usr/local/bin',
            ])
            ->run("{$cmd} -interaction=nonstopmode -cnf-line=\"openout_any=a\" -cnf-line=\"openin_any=a\" " . escapeshellarg($relativePath));
        
        $output = $process->output() ?: $process->errorOutput();
        
        // PDF wordt gewoon naast het bronbestand aangemaakt
        $pdfName = pathinfo($relativePath, PATHINFO_FILENAME) . '.pdf';
        $fullPdfPath = $projectDir . '/' . dirname($relativePath) . '/' . $pdfName;
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
