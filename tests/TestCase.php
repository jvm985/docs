<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        $traits = class_uses_recursive(static::class);
        fwrite(STDERR, "[DEBUG] TestCase setUp class=".static::class." traits=".implode(',', array_keys($traits))."\n");
        parent::setUp();
        fwrite(STDERR, "[DEBUG] After parent::setUp; schema has users? ".(\Illuminate\Support\Facades\Schema::hasTable('users') ? 'yes' : 'NO')."\n");
    }
}
