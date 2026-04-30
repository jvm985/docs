<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

class FileService
{
    public const MAX_TEXT_BYTES = 5 * 1024 * 1024;

    public const TEXT_EXTENSIONS = [
        'tex', 'sty', 'cls', 'bib',
        'md', 'rmd',
        'r', 'rmd',
        'typ',
        'json', 'xml', 'yaml', 'yml', 'toml',
        'txt', 'log',
        'html', 'htm', 'css', 'js', 'mjs', 'ts',
        'csv', 'tsv',
        'php', 'py', 'sh',
        'gitignore', 'env',
    ];

    public const VIEWABLE_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'pdf',
    ];

    public function basePath(Project $project): string
    {
        $path = storage_path('app/private/'.$project->filesPath());
        if (! is_dir($path)) {
            @mkdir($path, 0775, true);
        }

        return $path;
    }

    public function absolutePath(Project $project, string $relative): string
    {
        $base = $this->basePath($project);
        $clean = $this->validateRelativePath($relative);
        $full = $clean === '' ? $base : $base.'/'.$clean;

        return $full;
    }

    public function tree(Project $project): array
    {
        $base = $this->basePath($project);

        return $this->readDir($base, '');
    }

    private function readDir(string $base, string $relative): array
    {
        $abs = $relative === '' ? $base : $base.'/'.$relative;
        if (! is_dir($abs)) {
            return [];
        }
        $entries = [];
        $items = scandir($abs) ?: [];
        foreach ($items as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $rel = $relative === '' ? $name : $relative.'/'.$name;
            $full = $abs.'/'.$name;
            if (is_dir($full)) {
                $entries[] = [
                    'type' => 'folder',
                    'name' => $name,
                    'path' => $rel,
                    'children' => $this->readDir($base, $rel),
                ];
            } else {
                $entries[] = [
                    'type' => 'file',
                    'name' => $name,
                    'path' => $rel,
                    'extension' => strtolower(pathinfo($name, PATHINFO_EXTENSION)),
                    'size' => @filesize($full) ?: 0,
                ];
            }
        }

        usort($entries, function ($a, $b) {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'folder' ? -1 : 1;
            }

            return strcasecmp($a['name'], $b['name']);
        });

        return $entries;
    }

    public function readFile(Project $project, string $path): array
    {
        $abs = $this->absolutePath($project, $path);
        if (! is_file($abs)) {
            throw new RuntimeException('File not found.');
        }
        $name = basename($abs);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $size = @filesize($abs) ?: 0;

        if (in_array($ext, self::TEXT_EXTENSIONS, true) || $size === 0) {
            if ($size > self::MAX_TEXT_BYTES) {
                return [
                    'kind' => 'binary',
                    'name' => $name,
                    'extension' => $ext,
                    'size' => $size,
                ];
            }
            $content = (string) file_get_contents($abs);
            if (! mb_check_encoding($content, 'UTF-8')) {
                return [
                    'kind' => 'binary',
                    'name' => $name,
                    'extension' => $ext,
                    'size' => $size,
                ];
            }

            return [
                'kind' => 'text',
                'name' => $name,
                'extension' => $ext,
                'size' => $size,
                'content' => $content,
                'language' => $this->editorLanguage($ext),
            ];
        }

        return [
            'kind' => in_array($ext, self::VIEWABLE_EXTENSIONS, true) ? 'viewable' : 'binary',
            'name' => $name,
            'extension' => $ext,
            'size' => $size,
        ];
    }

    public function writeFile(Project $project, string $path, string $content): void
    {
        $abs = $this->absolutePath($project, $path);
        if (! is_file($abs)) {
            throw new RuntimeException('File not found.');
        }
        file_put_contents($abs, $content);
    }

    public function create(Project $project, string $path, string $type, ?string $content = null): array
    {
        $abs = $this->absolutePath($project, $path);
        if (file_exists($abs)) {
            throw new RuntimeException('Already exists.');
        }
        $parent = dirname($abs);
        if (! is_dir($parent)) {
            @mkdir($parent, 0775, true);
        }
        if ($type === 'folder') {
            mkdir($abs, 0775, true);
        } else {
            file_put_contents($abs, $content ?? '');
        }

        return [
            'type' => $type,
            'name' => basename($abs),
            'path' => $this->validateRelativePath($path),
        ];
    }

    public function upload(Project $project, string $folder, UploadedFile $file, ?string $name = null): array
    {
        $name = $name ?? $file->getClientOriginalName();
        $name = $this->sanitizeSegment($name);
        $folderClean = $this->validateRelativePath($folder);
        $rel = $folderClean === '' ? $name : $folderClean.'/'.$name;
        $absParent = $folderClean === '' ? $this->basePath($project) : $this->absolutePath($project, $folderClean);
        if (! is_dir($absParent)) {
            @mkdir($absParent, 0775, true);
        }
        $file->move($absParent, $name);

        return [
            'type' => 'file',
            'name' => $name,
            'path' => $this->validateRelativePath($rel),
        ];
    }

    public function delete(Project $project, string $path): void
    {
        $clean = $this->validateRelativePath($path);
        if ($clean === '') {
            throw new RuntimeException('Cannot delete project root.');
        }
        $abs = $this->absolutePath($project, $clean);
        if (! file_exists($abs)) {
            return;
        }
        if (is_dir($abs)) {
            $this->rrmdir($abs);
        } else {
            unlink($abs);
        }
    }

    private function rrmdir(string $dir): void
    {
        $items = scandir($dir) ?: [];
        foreach ($items as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $full = $dir.'/'.$name;
            if (is_dir($full)) {
                $this->rrmdir($full);
            } else {
                unlink($full);
            }
        }
        rmdir($dir);
    }

    public function rename(Project $project, string $path, string $newName): string
    {
        $cleanPath = $this->validateRelativePath($path);
        if ($cleanPath === '') {
            throw new RuntimeException('Invalid target.');
        }
        $newName = $this->sanitizeSegment($newName);
        $abs = $this->absolutePath($project, $cleanPath);
        $parent = dirname($abs);
        $newAbs = $parent.'/'.$newName;
        if (file_exists($newAbs)) {
            throw new RuntimeException('Target already exists.');
        }
        rename($abs, $newAbs);
        $newRel = trim(dirname($cleanPath) === '.' ? $newName : dirname($cleanPath).'/'.$newName, '/');

        return $newRel;
    }

    public function move(Project $project, string $path, string $newParent): string
    {
        $cleanPath = $this->validateRelativePath($path);
        if ($cleanPath === '') {
            throw new RuntimeException('Invalid target.');
        }
        $cleanParent = $this->validateRelativePath($newParent);
        $abs = $this->absolutePath($project, $cleanPath);
        $name = basename($abs);
        $parentAbs = $cleanParent === '' ? $this->basePath($project) : $this->absolutePath($project, $cleanParent);

        if (is_dir($abs) && str_starts_with($parentAbs.'/', $abs.'/')) {
            throw new RuntimeException('Cannot move a folder into itself.');
        }

        if (! is_dir($parentAbs)) {
            throw new RuntimeException('Target folder not found.');
        }
        $newAbs = $parentAbs.'/'.$name;
        if (file_exists($newAbs)) {
            throw new RuntimeException('Target already exists.');
        }
        rename($abs, $newAbs);

        return trim($cleanParent === '' ? $name : $cleanParent.'/'.$name, '/');
    }

    public function copyAll(Project $from, Project $to): void
    {
        $src = $this->basePath($from);
        $dst = $this->basePath($to);
        if (! is_dir($src)) {
            return;
        }
        if (! is_dir($dst)) {
            @mkdir($dst, 0775, true);
        }
        $finder = (new Finder)->in($src)->ignoreDotFiles(false);
        foreach ($finder as $item) {
            /** @var SplFileInfo $item */
            $rel = $item->getRelativePathname();
            $target = $dst.'/'.$rel;
            if ($item->isDir()) {
                @mkdir($target, 0775, true);
            } else {
                @mkdir(dirname($target), 0775, true);
                copy($item->getPathname(), $target);
            }
        }
    }

    public function copyEntry(Project $from, string $relPath, Project $to, string $targetParent = ''): string
    {
        $src = $this->absolutePath($from, $relPath);
        if (! file_exists($src)) {
            throw new RuntimeException('Source not found.');
        }
        $name = basename($src);
        $cleanParent = $this->validateRelativePath($targetParent);
        $targetRel = $cleanParent === '' ? $name : $cleanParent.'/'.$name;
        $dstAbs = $this->absolutePath($to, $targetRel);
        $dstAbs = $this->makeUnique($dstAbs);
        if (is_dir($src)) {
            $this->copyDir($src, $dstAbs);
        } else {
            @mkdir(dirname($dstAbs), 0775, true);
            copy($src, $dstAbs);
        }

        $base = $this->basePath($to);

        return ltrim(substr($dstAbs, strlen($base)), '/');
    }

    private function makeUnique(string $abs): string
    {
        if (! file_exists($abs)) {
            return $abs;
        }
        $dir = dirname($abs);
        $name = basename($abs);
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $stem = $ext === '' ? $name : substr($name, 0, -strlen($ext) - 1);
        $i = 1;
        while (true) {
            $candidate = $dir.'/'.$stem.'-copy'.($i > 1 ? $i : '').($ext === '' ? '' : '.'.$ext);
            if (! file_exists($candidate)) {
                return $candidate;
            }
            $i++;
        }
    }

    private function copyDir(string $src, string $dst): void
    {
        @mkdir($dst, 0775, true);
        $items = scandir($src) ?: [];
        foreach ($items as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $s = $src.'/'.$name;
            $d = $dst.'/'.$name;
            if (is_dir($s)) {
                $this->copyDir($s, $d);
            } else {
                copy($s, $d);
            }
        }
    }

    public function editorLanguage(string $extension): string
    {
        return match ($extension) {
            'tex', 'sty', 'cls', 'bib' => 'latex',
            'md', 'rmd' => 'markdown',
            'r' => 'r',
            'json' => 'json',
            'xml', 'html', 'htm' => 'xml',
            'css' => 'css',
            'js', 'mjs', 'ts' => 'javascript',
            'php' => 'php',
            'py' => 'python',
            'typ' => 'typst',
            default => 'plaintext',
        };
    }

    private function sanitizeSegment(string $name): string
    {
        $name = trim($name);
        $name = str_replace(['/', '\\', "\0"], '_', $name);
        if ($name === '' || $name === '.' || $name === '..') {
            throw new RuntimeException('Invalid name.');
        }

        return $name;
    }

    public function validateRelativePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = trim($path, '/');
        if ($path === '') {
            return '';
        }
        $segments = [];
        foreach (explode('/', $path) as $seg) {
            if ($seg === '' || $seg === '.') {
                continue;
            }
            if ($seg === '..') {
                throw new RuntimeException('Invalid path.');
            }
            if (str_contains($seg, "\0")) {
                throw new RuntimeException('Invalid path.');
            }
            $segments[] = $seg;
        }

        return implode('/', $segments);
    }
}
