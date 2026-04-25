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
        
        // The project directory (might be read-only for viewers)
        $projectDir = $tempDir . '/' . $file->project->name;
        $relativePath = $file->getPath();
        
        // Create a dedicated, writable output directory for this run
        $outputDir = $tempDir . '/_output_' . Str::random(5);
        mkdir($outputDir, 0777, true);
        
        $process = Process::path($projectDir)
            ->env([
                'HOME' => '/tmp', 
                'PATH' => '/usr/bin:/bin:/usr/local/bin',
            ])
            ->run("openout_any=a openin_any=a {$cmd} -interaction=nonstopmode -output-directory=" . escapeshellarg($outputDir) . " " . escapeshellarg($relativePath));
        
        $output = $process->output() ?: $process->errorOutput();
        
        // PDF is now in our dedicated output directory
        $pdfName = pathinfo($relativePath, PATHINFO_FILENAME) . '.pdf';
        $fullPdfPath = $outputDir . '/' . $pdfName;

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
