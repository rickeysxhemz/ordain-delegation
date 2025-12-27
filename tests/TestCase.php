<?php

declare(strict_types=1);

namespace Ordain\Delegation\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Ordain\Delegation\Providers\DelegationServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            PermissionServiceProvider::class,
            DelegationServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('permission-delegation.user_model', \Ordain\Delegation\Tests\Fixtures\User::class);

        // Prevent package from loading its migrations (we use test migrations)
        $app['config']->set('permission-delegation.run_migrations', false);

        // Configure auth guard for Spatie permission
        $app['config']->set('auth.defaults.guard', 'web');
        $app['config']->set('auth.guards.web', [
            'driver' => 'session',
            'provider' => 'users',
        ]);
        $app['config']->set('auth.providers.users', [
            'driver' => 'eloquent',
            'model' => \Ordain\Delegation\Tests\Fixtures\User::class,
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
