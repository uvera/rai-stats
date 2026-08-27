<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Env-based overrides for the test database (.env.testing, phpunit.xml
     * <env force="true">) are not reliable under DDEV: it injects
     * DB_DATABASE=db as a real container environment variable, which lands
     * in $_SERVER and wins over Dotenv's non-destructive safeLoad() and
     * over PHPUnit's <env> handling (which only ever touches $_ENV/putenv,
     * never $_SERVER). That gap once let a test's RefreshDatabase run
     * migrate:fresh against the real dev database and wipe it.
     *
     * This is the one place nothing else can silently override: it runs
     * right after the app boots and before RefreshDatabase gets a chance to
     * touch anything, so the database name is pinned here in code, then
     * verified - if it's still not "db_test" for any reason, abort hard
     * instead of letting a migration run against the wrong database.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $app['config']->set('database.connections.pgsql.database', 'db_test');

        // A connection may already have been resolved (and cached) during
        // boot with the pre-override config - purge it so the next
        // connection() call re-resolves using the value just set above.
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
}
