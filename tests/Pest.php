<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        // Redirect storage to a per-suite test dir so tests never touch real user files.
        $testStorage = storage_path('framework/testing/disks');
        app()->useStoragePath($testStorage);
        $dir = $testStorage.'/app/private/projects';
        if (is_dir($dir)) {
            removeDirRecursive($dir);
        }
        @mkdir($dir, 0775, true);
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
