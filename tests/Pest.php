<?php

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

// Ensure the sqlite test database file exists before Laravel boots.
$testDb = __DIR__.'/../database/testing.sqlite';
if (! file_exists($testDb)) {
    @mkdir(dirname($testDb), 0775, true);
    touch($testDb);
}

uses(TestCase::class, DatabaseMigrations::class)->in('Feature');

uses()->beforeEach(function () {
    // Belt-and-braces: ensure schema exists. DatabaseMigrations trait should
    // already have migrated, but Pest 4 + browser plugin can skip it.
    \Illuminate\Support\Facades\Schema::hasTable('users') || $this->artisan('migrate:fresh');

    $dir = storage_path('framework/testing/disks/app/private/projects');
    if (is_dir($dir)) {
        removeDirRecursive($dir);
    }
    @mkdir($dir, 0775, true);
})->in('Feature');

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
