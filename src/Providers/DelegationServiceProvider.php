<?php

declare(strict_types=1);

namespace Ordain\Delegation\Providers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Ordain\Delegation\Commands\AssignRoleCommand;
use Ordain\Delegation\Commands\CacheResetCommand;
use Ordain\Delegation\Commands\ShowDelegationCommand;
use Ordain\Delegation\Contracts\DelegationAuditInterface;
use Ordain\Delegation\Contracts\DelegationAuthorizerInterface;
use Ordain\Delegation\Contracts\DelegationServiceInterface;
use Ordain\Delegation\Contracts\DelegationValidatorInterface;
use Ordain\Delegation\Contracts\EventDispatcherInterface;
use Ordain\Delegation\Contracts\QuotaManagerInterface;
use Ordain\Delegation\Contracts\Repositories\DelegationRepositoryInterface;
use Ordain\Delegation\Contracts\Repositories\PermissionRepositoryInterface;
use Ordain\Delegation\Contracts\Repositories\RoleRepositoryInterface;
use Ordain\Delegation\Contracts\RootAdminResolverInterface;
use Ordain\Delegation\Contracts\TransactionManagerInterface;
use Ordain\Delegation\Repositories\EloquentDelegationRepository;
use Ordain\Delegation\Repositories\SpatiePermissionRepository;
use Ordain\Delegation\Repositories\SpatieRoleRepository;
use Ordain\Delegation\Services\Audit\AuditDriverFactory;
use Ordain\Delegation\Services\Authorization\DelegationAuthorizer;
use Ordain\Delegation\Services\Authorization\RootAdminResolver;
use Ordain\Delegation\Services\CachedDelegationService;
use Ordain\Delegation\Services\DelegationService;
use Ordain\Delegation\Services\Infrastructure\EventDispatcher;
use Ordain\Delegation\Services\Infrastructure\NullEventDispatcher;
use Ordain\Delegation\Services\Infrastructure\TransactionManager;
use Ordain\Delegation\Services\Quota\QuotaManager;
use Ordain\Delegation\Services\Validation\DelegationValidator;

/**
 * Main service provider for the Ordain Delegation package.
 */
final class DelegationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../Config/permission-delegation.php',
            'permission-delegation',
        );

        $this->registerRepositories();
        $this->registerCoreServices();
        $this->registerDelegationService();
    }

    public function boot(): void
    {
        $this->publishAssets();
        $this->registerCommands();
    }

    private function registerRepositories(): void
    {
        $this->app->scoped(
            DelegationRepositoryInterface::class,
            EloquentDelegationRepository::class,
        );

        $this->app->scoped(
            RoleRepositoryInterface::class,
            static fn (): SpatieRoleRepository => new SpatieRoleRepository(
                (string) config('permission-delegation.role_model'),
            ),
        );

        $this->app->scoped(
            PermissionRepositoryInterface::class,
            static fn (): SpatiePermissionRepository => new SpatiePermissionRepository(
                (string) config('permission-delegation.permission_model'),
            ),
        );
    }

    private function registerCoreServices(): void
    {
        $this->app->scoped(
            TransactionManagerInterface::class,
            static fn (): TransactionManager => new TransactionManager(
                config('permission-delegation.user_model'),
            ),
        );

        $this->app->scoped(
            EventDispatcherInterface::class,
            static fn (Application $app): EventDispatcherInterface => config('permission-delegation.events.enabled', true)
                ? new EventDispatcher($app->make(Dispatcher::class))
                : new NullEventDispatcher,
        );

        $this->app->scoped(
            RootAdminResolverInterface::class,
            static fn (Application $app): RootAdminResolver => new RootAdminResolver(
                roleRepository: $app->make(RoleRepositoryInterface::class),
                enabled: (bool) config('permission-delegation.root_admin.enabled', true),
                roleIdentifier: config('permission-delegation.root_admin.role'),
            ),
        );

        $this->app->scoped(DelegationAuthorizerInterface::class, DelegationAuthorizer::class);
        $this->app->scoped(QuotaManagerInterface::class, QuotaManager::class);
        $this->app->scoped(DelegationValidatorInterface::class, DelegationValidator::class);
        $this->app->scoped(DelegationAuditInterface::class, static fn (Application $app): DelegationAuditInterface => AuditDriverFactory::create($app));
    }

    private function registerDelegationService(): void
    {
        $this->app->scoped(
            DelegationServiceInterface::class,
            static function (Application $app): DelegationServiceInterface {
                $service = $app->make(DelegationService::class);

                if (! config('permission-delegation.cache.enabled', true)) {
                    return $service;
                }

                return new CachedDelegationService(
                    inner: $service,
                    cache: $app->make('cache.store'),
                    ttl: (int) config('permission-delegation.cache.ttl', 3600),
                    prefix: (string) config('permission-delegation.cache.prefix', 'delegation_'),
                );
            },
        );

        $this->app->alias(DelegationServiceInterface::class, 'delegation');
    }

    private function publishAssets(): void
    {
        if (config('permission-delegation.run_migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        }

        $this->publishes([
            __DIR__.'/../Config/permission-delegation.php' => config_path('permission-delegation.php'),
        ], 'delegation-config');

        $this->publishes([
            __DIR__.'/../Database/Migrations/' => database_path('migrations'),
        ], 'delegation-migrations');
    }

    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ShowDelegationCommand::class,
                AssignRoleCommand::class,
                CacheResetCommand::class,
            ]);
        }
    }
}
