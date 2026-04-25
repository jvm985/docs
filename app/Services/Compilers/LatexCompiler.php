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
        $relativePath = $file->getPath();
        
        $process = Process::path($tempDir)
            ->env(['HOME' => '/tmp', 'PATH' => '/usr/bin:/bin:/usr/local/bin'])
            ->run("{$cmd} -interaction=nonstopmode " . escapeshellarg($relativePath));
        
        $output = $process->output() ?: $process->errorOutput();
        
        // PDF is usually generated in the same folder as the source file
        $pdfName = pathinfo($relativePath, PATHINFO_FILENAME) . '.pdf';
        $fullPdfPath = $tempDir . '/' . dirname($relativePath) . '/' . $pdfName;
        // Cleanup path if in root
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
