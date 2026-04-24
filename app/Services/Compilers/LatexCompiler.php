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
        
        $process = Process::path($tempDir)
            ->env(['HOME' => '/tmp', 'PATH' => '/usr/bin:/bin:/usr/local/bin'])
            ->run("{$cmd} -interaction=nonstopmode " . escapeshellarg($file->name));
        
        $output = $process->output() ?: $process->errorOutput();
        $pdfName = pathinfo($file->name, PATHINFO_FILENAME) . '.pdf';
        $url = null;
        $type = 'text';

        if (file_exists($tempDir . '/' . $pdfName)) {
            $fileData = file_get_contents($tempDir . '/' . $pdfName);
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
