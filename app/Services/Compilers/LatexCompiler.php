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
        
        $projectDir = $tempDir . '/' . $file->project->name;
        $relativePath = $file->getPath();
        
        // We draaien vanuit de projectmap zelf
        $process = Process::path($projectDir)
            ->env([
                'HOME' => '/tmp', 
                'PATH' => '/usr/bin:/bin:/usr/local/bin',
                'openout_any' => 'a',
                'openin_any' => 'a'
            ])
            ->run("{$cmd} -interaction=nonstopmode " . escapeshellarg($file->name));
        
        $output = $process->output() ?: $process->errorOutput();
        
        // PDF wordt in de projectmap gegenereerd
        $pdfName = pathinfo($file->name, PATHINFO_FILENAME) . '.pdf';
        $fullPdfPath = $projectDir . '/' . $pdfName;

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
