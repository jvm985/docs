<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        // DatabaseMigrations on Pest 4 sometimes doesn't actually migrate
        // (the artisan migrate:fresh inside the trait runs but Schema doesn't
        // see the schema on the same connection). Force a clean state ourselves.
        if (! Schema::hasTable('users')) {
            $this->artisan('migrate:fresh', ['--force' => true]);

            return;
        }

        // Tables exist but DatabaseMigrations may not have actually wiped them.
        // Truncate everything ourselves to guarantee per-test isolation.
        DB::statement('PRAGMA foreign_keys = OFF');
        $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' AND name <> 'migrations'");
        foreach ($tables as $t) {
            DB::statement('DELETE FROM "'.$t->name.'"');
        }
        DB::statement('PRAGMA foreign_keys = ON');
    }
}
