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
        $compiler = $file->preferred_compiler ?: ($options['compiler'] ?? 'pdflatex');
        $cmd = "/usr/bin/{$compiler}";
        
        $projectDir = $tempDir . '/' . $file->project->name;
        
        // Forceer permissies via LOKAAL configuratiebestand in de projectmap
        file_put_contents($projectDir . '/texmf.cnf', "openout_any = a\nopenin_any = a\n");
        
        $process = Process::path($projectDir)
            ->env([
                'HOME' => '/tmp', 
                'PATH' => '/usr/bin:/bin:/usr/local/bin',
                'openout_any' => 'a',
                'openin_any' => 'a'
            ])
            ->run("{$cmd} -interaction=nonstopmode -cnf-line=\"openout_any=a\" -cnf-line=\"openin_any=a\" " . escapeshellarg($file->name));
        
        $output = $process->output() ?: $process->errorOutput();
        
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
