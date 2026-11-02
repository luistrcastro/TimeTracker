<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refuse to boot the test app unless it is on in-memory SQLite.
     *
     * Feature tests use RefreshDatabase, which runs migrate:fresh (drops every
     * table). If phpunit.xml's env overrides are bypassed — e.g. DB_* already set
     * in the container environment, or a cached config — that would wipe a real
     * database. This check runs before any test trait touches the database.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $default  = $app['config']->get('database.default');
        $database = $app['config']->get("database.connections.{$default}.database");

        if ($default !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException(
                "Refusing to run tests against database connection [{$default}] ({$database}). "
                .'Tests must use in-memory SQLite: check phpunit.xml and run `php artisan config:clear`.'
            );
        }

        return $app;
    }
}
