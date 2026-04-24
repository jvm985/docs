<?php

namespace App\Services\Compilers;

use App\Models\File;

interface CompilerInterface
{
    /**
     * Compile the given file.
     * 
     * @param File $file
     * @param string $tempDir
     * @param array $options
     * @return array {type: string, output: string, url: ?string, result: ?array}
     */
    public function compile(File $file, string $tempDir, array $options = []): array;
}
