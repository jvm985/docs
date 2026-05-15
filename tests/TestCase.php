<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        // DatabaseMigrations on Pest 4 sometimes doesn't actually migrate
        // (the artisan migrate:fresh inside the trait runs but Schema doesn't
        // see the schema on the same connection). Force a fresh migrate.
        if (! Schema::hasTable('users')) {
            $this->artisan('migrate:fresh', ['--force' => true]);
        }
    }
}
