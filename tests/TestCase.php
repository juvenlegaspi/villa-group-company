<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        $cachedConfig = dirname(__DIR__).'/bootstrap/cache/config.php';

        if (is_file($cachedConfig)) {
            throw new \RuntimeException(
                'Refusing to run tests while Laravel configuration is cached. '
                .'Run "php artisan config:clear" first so PHPUnit can use its isolated SQLite database.'
            );
        }

        parent::setUp();

        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new \RuntimeException(
                "Unsafe test database configuration detected ({$connection}:{$database}). "
                .'Tests are only allowed against the isolated in-memory SQLite database.'
            );
        }
    }
}
