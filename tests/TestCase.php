<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        $log = '/tmp/test-debug.log';
        file_put_contents($log, "[DEBUG] setUp begin: ".static::class."\n", FILE_APPEND);
        parent::setUp();
        file_put_contents($log, "[DEBUG] after parent::setUp; default conn=".config('database.default').", db=".config('database.connections.'.config('database.default').'.database').", users? ".(\Illuminate\Support\Facades\Schema::hasTable('users') ? 'YES' : 'NO')."\n", FILE_APPEND);

        // Manual fallback: if DatabaseMigrations didn't actually migrate, do it now.
        if (! \Illuminate\Support\Facades\Schema::hasTable('users')) {
            file_put_contents($log, "[DEBUG] running manual migrate:fresh\n", FILE_APPEND);
            $exit = $this->artisan('migrate:fresh', ['--force' => true, '--seed' => false]);
            file_put_contents($log, "[DEBUG] manual migrate done; users? ".(\Illuminate\Support\Facades\Schema::hasTable('users') ? 'YES' : 'NO')."\n", FILE_APPEND);
        }
    }
}
