<?php

namespace Ecommerce\Shared\Tests;

use Ecommerce\Shared\SharedServiceProvider;
use Ecommerce\Shared\Testing\JwtTestKeys;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        JwtTestKeys::install();
    }

    protected function getPackageProviders($app): array
    {
        return [SharedServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('messaging.service', 'test-service');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
