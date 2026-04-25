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
        
        $projectName = $file->project->name;
        $workspacePath = $projectName . '/' . $file->getPath();
        
        // MIRROR: Zorg dat alle submappen van het project ook in de root bestaan
        // Zodat LaTeX vanuit de root kan schrijven naar 'hoofdstukken/...'
        $this->mirrorProjectStructure($tempDir, $projectName);
        
        // Forceer permissies via LOKAAL configuratiebestand
        file_put_contents($tempDir . '/texmf.cnf', "openout_any = a\nopenin_any = a\n");
        
        $process = Process::path($tempDir)
            ->env([
                'HOME' => '/tmp', 
                'PATH' => '/usr/bin:/bin:/usr/local/bin',
                'TEXMFCNF' => $tempDir . ':',
                'openout_any' => 'a',
                'openin_any' => 'a'
            ])
            ->run("{$cmd} -interaction=nonstopmode " . escapeshellarg($workspacePath));
        
        $output = $process->output() ?: $process->errorOutput();
        
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

    private function mirrorProjectStructure(string $workspaceRoot, string $projectName): void
    {
        $projectPath = $workspaceRoot . '/' . $projectName;
        if (!is_dir($projectPath)) return;

        $directories = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($projectPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($directories as $item) {
            if ($item->isDir()) {
                // Maak de submap (bijv. 'hoofdstukken') aan in de root
                $relativePath = str_replace($projectPath . '/', '', $item->getPathname());
                $targetPath = $workspaceRoot . '/' . $relativePath;
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0777, true);
                }
            }
        }
    }
}
