<?php

namespace App\Services;

/**
 * Forward-search a source-line context string into a compiled PDF and return
 * the page number where it appears.
 *
 * Uses `pdftotext` to extract per-page plain text once per PDF (cached in
 * a sidecar JSON next to the PDF), then string-matches the needle.
 */
class PdfLocator
{
    public function locate(string $pdfPath, string $needle): ?int
    {
        if (! is_file($pdfPath) || trim($needle) === '') {
            return null;
        }

        $pages = $this->pagesText($pdfPath);
        if (! $pages) {
            return null;
        }

        $needleNorm = $this->normalise($needle);
        if ($needleNorm === '') {
            return null;
        }

        // Skip TOC pages — they contain every heading and would always match
        // any short needle. A TOC page is detected by having many
        // "Title......N" leader-dot patterns.
        foreach ($pages as $idx => $text) {
            if ($this->looksLikeToc($text)) {
                continue;
            }
            if ($text !== '' && str_contains($this->normalise($text), $needleNorm)) {
                return $idx + 1;
            }
        }

        return null;
    }

    private function looksLikeToc(string $text): bool
    {
        // 3+ TOC-style leader lines on the same page is a strong signal.
        return preg_match_all('/\.{4,}\s*\d+\s*$/m', $text) >= 3;
    }

    /**
     * Return per-page text array (0-indexed). Cached as pdf-pages.json
     * next to the PDF, invalidated when the PDF mtime changes.
     *
     * @return string[]
     */
    private function pagesText(string $pdfPath): array
    {
        $cachePath = $pdfPath.'.pages.json';
        $pdfMtime = filemtime($pdfPath);

        if (is_file($cachePath)) {
            $cached = json_decode((string) @file_get_contents($cachePath), true);
            if (is_array($cached) && ($cached['mtime'] ?? null) === $pdfMtime) {
                return $cached['pages'] ?? [];
            }
        }

        $pages = [];
        // pdftotext -f N -l N -layout pdf - prints page N to stdout
        // Build incrementally: ask for total pages first via pdfinfo if available;
        // otherwise loop until empty output.
        $total = $this->pageCount($pdfPath);
        for ($i = 1; $i <= $total; $i++) {
            $cmd = sprintf(
                'pdftotext -f %d -l %d -layout %s - 2>/dev/null',
                $i, $i, escapeshellarg($pdfPath)
            );
            $pages[] = (string) shell_exec($cmd);
        }

        @file_put_contents($cachePath, json_encode([
            'mtime' => $pdfMtime,
            'pages' => $pages,
        ]));

        return $pages;
    }

    private function pageCount(string $pdfPath): int
    {
        $out = shell_exec(sprintf('pdfinfo %s 2>/dev/null', escapeshellarg($pdfPath)));
        if ($out && preg_match('/^Pages:\s+(\d+)/m', $out, $m)) {
            return (int) $m[1];
        }

        return 1;
    }

    private function normalise(string $s): string
    {
        // Lowercase, drop non-letters/digits except spaces, collapse whitespace.
        $s = mb_strtolower($s);
        $s = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $s) ?? '';
        $s = preg_replace('/\s+/u', ' ', $s) ?? '';

        return trim($s);
    }
}
