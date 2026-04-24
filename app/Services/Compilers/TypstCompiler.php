<?php

namespace App\Services\Compilers;

use App\Models\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TypstCompiler implements CompilerInterface
{
    public function compile(File $file, string $tempDir, array $options = []): array
    {
        $pdfName = pathinfo($file->name, PATHINFO_FILENAME) . '.pdf';
        $process = Process::path($tempDir)->env(['HOME' => '/tmp'])->run("/usr/local/bin/typst compile " . escapeshellarg($file->name) . " " . escapeshellarg($pdfName));
        
        $output = $process->output() ?: $process->errorOutput();
        $url = null;
        $type = 'text';

        if (file_exists($tempDir . '/' . $pdfName)) {
            $type = 'pdf';
            $publicPath = 'outputs/' . Str::random(20) . '.pdf';
            Storage::disk('public')->put($publicPath, file_get_contents($tempDir . '/' . $pdfName));
            $url = Storage::url($publicPath);
        }

        return [
            'type' => $type,
            'output' => $output,
            'url' => $url,
            'result' => null
        ];
    }
}
