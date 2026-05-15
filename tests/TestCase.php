<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        file_put_contents('/tmp/test-debug.log', "[DEBUG] TestCase::setUp called for ".static::class."\n", FILE_APPEND);
        parent::setUp();
        file_put_contents('/tmp/test-debug.log', "[DEBUG] After parent::setUp; users table exists: ".(\Illuminate\Support\Facades\Schema::hasTable('users') ? 'yes' : 'NO')."\n", FILE_APPEND);
    }
}
