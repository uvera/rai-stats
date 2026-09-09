<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * DDEV injects DB_DATABASE=db, CACHE_STORE=database and
     * SESSION_DRIVER=database into the container as real environment
     * variables. Those land in $_SERVER, where they beat both Dotenv's
     * non-destructive safeLoad() and PHPUnit's <env> handling (which only
     * ever touches $_ENV / putenv, never $_SERVER) - so the suite would
     * otherwise run against the real dev database, the real database cache
     * table, and the real sessions table.
     *
     * The database name gap once let a RefreshDatabase run migrate:fresh
     * against the dev database and wipe it. The cache/session gap is
     * subtler: the database cache and session stores issue their own writes
     * mid-request, and on Postgres a write that conflicts (an expired-row
     * delete, the rate limiter's duplicate-key insert, ...) aborts
     * RefreshDatabase's wrapping transaction - so the acting user and
     * everything else the test created silently vanishes part-way through.
     * That bit every Filament/Livewire component test: Livewire 4's
     * checksum guard hits the RateLimiter on the first ->set()/->call(),
     * the RateLimiter had been resolved against the database cache during
     * boot, and that first hit killed the transaction.
     *
     * Fix it at the source: rewrite the variables in every place env()
     * reads from ($_SERVER, $_ENV, putenv) before the framework boots, so
     * config() sees the test values from the start and nothing - the
     * RateLimiter singleton included - ever captures a database-backed
     * store. The config()/purge() pins in createApplication() are
     * belt-and-braces, plus a hard abort if the database name is somehow
     * still wrong.
     */
    protected function setUp(): void
    {
        self::pinTestEnvironment();

        parent::setUp();
    }

    public function createApplication()
    {
        self::pinTestEnvironment();

        $app = parent::createApplication();

        $app['config']->set('database.connections.pgsql.database', 'db_test');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['cache']->forgetDriver('array');

        $app['db']->purge('pgsql');

        $database = $app['db']->connection()->getDatabaseName();

        if ($database !== 'db_test') {
            throw new RuntimeException(
                "Refusing to run tests: resolved database is \"{$database}\", not \"db_test\". ".
                'Tests must never run against the real dev database - see the comment on '.
                self::class.'::createApplication().'
            );
        }

        return $app;
    }

    private static function pinTestEnvironment(): void
    {
        $overrides = [
            'DB_DATABASE' => 'db_test',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
        ];

        foreach ($overrides as $key => $value) {
            $_SERVER[$key] = $value;
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}
