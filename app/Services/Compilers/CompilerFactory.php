<?php

namespace App\Services\Compilers;

use App\Models\File;
use InvalidArgumentException;

class CompilerFactory
{
    public static function make(File $file): CompilerInterface
    {
        $extension = strtolower($file->extension);

        return match ($extension) {
            'tex' => new LatexCompiler(),
            'typ' => new TypstCompiler(),
            'md', 'rmd' => new PandocCompiler(),
            'r' => new RCompiler(),
            default => throw new InvalidArgumentException("No compiler available for extension: {$extension}"),
        };
    }
}
