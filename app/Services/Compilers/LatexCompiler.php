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
        
        // The project directory (source)
        $projectDir = $tempDir . '/' . $file->project->name;
        $relativePath = $file->getPath();
        
        // Create a dedicated, writable output directory
        $outputDir = $tempDir . '/_output_' . Str::random(5);
        mkdir($outputDir, 0777, true);

        // MIRROR the directory structure in the output directory
        // This is crucial for \include{subfolder/file} to work with -output-directory
        $this->mirrorStructure($tempDir, $outputDir);
        
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

    private function mirrorStructure(string $sourceRoot, string $targetRoot): void
    {
        $directories = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceRoot, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($directories as $item) {
            if ($item->isDir() && !str_contains($item->getPathname(), '_output_')) {
                $targetPath = str_replace($sourceRoot, $targetRoot, $item->getPathname());
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0777, true);
                }
            }
        }
    }
}
