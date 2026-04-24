<?php

namespace App\Services\Compilers;

use App\Models\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PandocCompiler implements CompilerInterface
{
    public function compile(File $file, string $tempDir, array $options = []): array
    {
        $pdfName = pathinfo($file->name, PATHINFO_FILENAME) . '.pdf';
        $output = "";
        $url = null;
        $type = 'text';

        if (strtolower($file->extension) === 'rmd') {
            $renderCmd = "rmarkdown::render('{$file->name}', output_format='pdf_document', output_options=list(pdf_engine='/usr/bin/pdflatex', pandoc_args=c('-V', 'pagemode=UseNone')))";
            $process = Process::path($tempDir)
                ->env(['HOME' => '/tmp', 'PATH' => '/usr/bin:/bin:/usr/local/bin'])
                ->run("/usr/bin/Rscript -e " . escapeshellarg($renderCmd));
        } else {
            $process = Process::path($tempDir)
                ->env(['HOME' => '/tmp', 'PATH' => '/usr/bin:/bin:/usr/local/bin'])
                ->run("/usr/bin/pandoc " . escapeshellarg($file->name) . " --pdf-engine=/usr/bin/pdflatex -V pagemode=UseNone -o " . escapeshellarg($pdfName));
        }

        $output = $process->output() ?: $process->errorOutput();

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
