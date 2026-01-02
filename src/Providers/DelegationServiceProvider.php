<?php

declare(strict_types=1);

namespace Ordain\Delegation\Providers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Ordain\Delegation\Commands\AssignRoleCommand;
use Ordain\Delegation\Commands\CacheResetCommand;
use Ordain\Delegation\Commands\HealthCheckCommand;
use Ordain\Delegation\Commands\InstallCommand;
use Ordain\Delegation\Commands\ShowDelegationCommand;
use Ordain\Delegation\Contracts\AuthorizationPipelineInterface;
use Ordain\Delegation\Contracts\DelegationAuditInterface;
use Ordain\Delegation\Contracts\DelegationAuthorizerInterface;
use Ordain\Delegation\Contracts\DelegationServiceInterface;
use Ordain\Delegation\Contracts\DelegationValidatorInterface;
use Ordain\Delegation\Contracts\EventDispatcherInterface;
use Ordain\Delegation\Contracts\QuotaManagerInterface;
use Ordain\Delegation\Contracts\Repositories\DelegationRepositoryInterface;
use Ordain\Delegation\Contracts\Repositories\PermissionRepositoryInterface;
use Ordain\Delegation\Contracts\Repositories\RoleRepositoryInterface;
use Ordain\Delegation\Contracts\Repositories\UserRepositoryInterface;
use Ordain\Delegation\Contracts\RootAdminResolverInterface;
use Ordain\Delegation\Contracts\TransactionManagerInterface;
use Ordain\Delegation\Http\Middleware\CanAssignRoleMiddleware;
use Ordain\Delegation\Http\Middleware\CanDelegateMiddleware;
use Ordain\Delegation\Http\Middleware\CanManageUserMiddleware;
use Ordain\Delegation\Http\Middleware\RateLimitDelegationMiddleware;
use Ordain\Delegation\Repositories\EloquentDelegationRepository;
use Ordain\Delegation\Repositories\EloquentUserRepository;
use Ordain\Delegation\Repositories\SpatiePermissionRepository;
use Ordain\Delegation\Repositories\SpatieRoleRepository;
use Ordain\Delegation\Routing\RouteMacros;
use Ordain\Delegation\Services\Audit\AuditContext;
use Ordain\Delegation\Services\Audit\AuditDriverFactory;
use Ordain\Delegation\Services\Audit\DatabaseDelegationAudit;
use Ordain\Delegation\Services\Audit\LogDelegationAudit;
use Ordain\Delegation\Services\Authorization\AuthorizationPipeline;
use Ordain\Delegation\Services\Authorization\DelegationAuthorizer;
use Ordain\Delegation\Services\Authorization\RootAdminResolver;
use Ordain\Delegation\Services\CachedDelegationService;
use Ordain\Delegation\Services\DelegationService;
use Ordain\Delegation\Services\Infrastructure\EventDispatcher;
use Ordain\Delegation\Services\Infrastructure\NullEventDispatcher;
use Ordain\Delegation\Services\Infrastructure\TransactionManager;
use Ordain\Delegation\Services\Quota\QuotaManager;
use Ordain\Delegation\Services\Validation\DelegationValidator;
use Ordain\Delegation\View\BladeDirectives;

/**
 * Main service provider for the Ordain Delegation package.
 *
 * Implements DeferrableProvider for lazy loading of core services.
 * Services are only resolved when actually needed.
 */
final class DelegationServiceProvider extends ServiceProvider implements DeferrableProvider
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
        $this->registerAboutCommand();
        $this->registerOptionalFeatures();
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            DelegationServiceInterface::class,
            DelegationAuthorizerInterface::class,
            DelegationValidatorInterface::class,
            DelegationAuditInterface::class,
            QuotaManagerInterface::class,
            RootAdminResolverInterface::class,
            TransactionManagerInterface::class,
            EventDispatcherInterface::class,
            AuthorizationPipelineInterface::class,
            DelegationRepositoryInterface::class,
            RoleRepositoryInterface::class,
            PermissionRepositoryInterface::class,
            UserRepositoryInterface::class,
            'delegation',
        ];
    }

    private function registerOptionalFeatures(): void
    {
        if (config('permission-delegation.features.blade_directives', true)) {
            $this->registerBladeDirectives();
        }

        if (config('permission-delegation.features.route_middleware', true)) {
            $this->registerMiddleware();
        }

        if (config('permission-delegation.features.route_macros', true)) {
            $this->registerRouteMacros();
        }
    }

    private function registerBladeDirectives(): void
    {
        (new BladeDirectives)->register();
    }

    private function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);

        $router->aliasMiddleware('can.delegate', CanDelegateMiddleware::class);
        $router->aliasMiddleware('can.assign.role', CanAssignRoleMiddleware::class);
        $router->aliasMiddleware('can.manage.user', CanManageUserMiddleware::class);
        $router->aliasMiddleware('delegation.throttle', RateLimitDelegationMiddleware::class);
    }

    private function registerRouteMacros(): void
    {
        RouteMacros::register();
    }

    private function registerAboutCommand(): void
    {
        AboutCommand::add('Delegation', static fn (): array => [
            'Version' => '1.0.0',
            'Audit Driver' => (string) config('permission-delegation.audit.driver', 'database'),
            'Audit Enabled' => config('permission-delegation.audit.enabled', true) ? '<fg=green;options=bold>YES</>' : '<fg=red;options=bold>NO</>',
            'Cache Enabled' => config('permission-delegation.cache.enabled', true) ? '<fg=green;options=bold>YES</>' : '<fg=red;options=bold>NO</>',
            'Events Enabled' => config('permission-delegation.events.enabled', true) ? '<fg=green;options=bold>YES</>' : '<fg=red;options=bold>NO</>',
            'Root Admin Bypass' => config('permission-delegation.root_admin.enabled', true) ? '<fg=green;options=bold>YES</>' : '<fg=red;options=bold>NO</>',
            'Root Admin Role' => (string) config('permission-delegation.root_admin.role', 'root-admin'),
        ]);
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

        $this->app->scoped(
            UserRepositoryInterface::class,
            static fn (): EloquentUserRepository => new EloquentUserRepository(
                /** @phpstan-ignore-next-line */
                (string) config('permission-delegation.user_model'),
            ),
        );
    }

    private function registerCoreServices(): void
    {
        $this->app->scoped(
            TransactionManagerInterface::class,
            static function (): TransactionManager {
                // Resolve table name once from model class to avoid repeated instantiation
                $userModelClass = config('permission-delegation.user_model', 'App\\Models\\User');
                $userTable = 'users';

                if (class_exists($userModelClass) && is_subclass_of($userModelClass, \Illuminate\Database\Eloquent\Model::class)) {
                    $userTable = (new $userModelClass)->getTable();
                }

                return new TransactionManager(
                    userTable: $userTable,
                );
            },
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

        $this->app->scoped(AuthorizationPipelineInterface::class, AuthorizationPipeline::class);
        $this->app->scoped(DelegationAuthorizerInterface::class, DelegationAuthorizer::class);
        $this->app->scoped(QuotaManagerInterface::class, QuotaManager::class);
        $this->app->scoped(DelegationValidatorInterface::class, DelegationValidator::class);

        $this->registerAuditDrivers();
    }

    private function registerAuditDrivers(): void
    {
        $this->app->scoped(AuditDriverFactory::class);

        // Register the audit interface to use the factory
        $this->app->scoped(
            DelegationAuditInterface::class,
            static fn (Application $app): DelegationAuditInterface => $app->make(AuditDriverFactory::class)->create(),
        );

        $this->app->bind(
            DatabaseDelegationAudit::class,
            static fn (): DatabaseDelegationAudit => new DatabaseDelegationAudit(
                tableName: (string) config('permission-delegation.tables.delegation_audit_logs', 'delegation_audit_logs'),
                context: AuditContext::fromRequest(request()),
            ),
        );

        $this->app->bind(
            LogDelegationAudit::class,
            static fn (): LogDelegationAudit => new LogDelegationAudit(
                channel: (string) config('permission-delegation.audit.log_channel', 'stack'),
            ),
        );
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

        $this->publishesMigrations([
            __DIR__.'/../Database/Migrations' => database_path('migrations'),
        ], 'delegation-migrations');
    }

    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                ShowDelegationCommand::class,
                AssignRoleCommand::class,
                CacheResetCommand::class,
                HealthCheckCommand::class,
            ]);
        }
    }
}
