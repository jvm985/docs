<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        $dir = storage_path('app/private/projects');
        if (is_dir($dir)) {
            removeDirRecursive($dir);
        }
    })
    ->in('Feature', 'Browser');

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

function removeDirRecursive(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $full = $dir.'/'.$item;
        is_dir($full) ? removeDirRecursive($full) : @unlink($full);
    }
    @rmdir($dir);
}
