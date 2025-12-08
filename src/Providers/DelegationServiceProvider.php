<?php

declare(strict_types=1);

namespace Ordain\Delegation\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\DelegationAuditInterface;
use Ordain\Delegation\Contracts\DelegationServiceInterface;
use Ordain\Delegation\Contracts\Repositories\DelegationRepositoryInterface;
use Ordain\Delegation\Contracts\Repositories\PermissionRepositoryInterface;
use Ordain\Delegation\Contracts\Repositories\RoleRepositoryInterface;
use Ordain\Delegation\Http\Middleware\CanAssignRoleMiddleware;
use Ordain\Delegation\Http\Middleware\CanDelegateMiddleware;
use Ordain\Delegation\Http\Middleware\CanManageUserMiddleware;
use Ordain\Delegation\Repositories\EloquentDelegationRepository;
use Ordain\Delegation\Repositories\SpatiePermissionRepository;
use Ordain\Delegation\Repositories\SpatieRoleRepository;
use Ordain\Delegation\Services\Audit\DatabaseDelegationAudit;
use Ordain\Delegation\Services\Audit\LogDelegationAudit;
use Ordain\Delegation\Services\Audit\NullDelegationAudit;
use Ordain\Delegation\Services\CachedDelegationService;
use Ordain\Delegation\Services\DelegationService;

/**
 * Service provider for the Ordain Delegation package.
 */
final class DelegationServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../Config/permission-delegation.php',
            'permission-delegation',
        );

        $this->registerRepositories();
        $this->registerAuditService();
        $this->registerDelegationService();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->publishConfig();
        $this->publishMigrations();
        $this->registerMiddleware();
        $this->registerBladeDirectives();
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
            DelegationRepositoryInterface::class,
            RoleRepositoryInterface::class,
            PermissionRepositoryInterface::class,
            DelegationAuditInterface::class,
            'delegation',
        ];
    }

    /**
     * Register repository bindings.
     *
     * Using scoped bindings for Octane compatibility - instances are
     * automatically cleared between requests in long-running processes.
     */
    private function registerRepositories(): void
    {
        $this->app->scoped(DelegationRepositoryInterface::class, static fn (): EloquentDelegationRepository => new EloquentDelegationRepository);

        $this->app->scoped(RoleRepositoryInterface::class, static function (): SpatieRoleRepository {
            /** @var string $roleModel */
            $roleModel = config('permission-delegation.role_model');

            return new SpatieRoleRepository($roleModel);
        });

        $this->app->scoped(PermissionRepositoryInterface::class, static function (): SpatiePermissionRepository {
            /** @var string $permissionModel */
            $permissionModel = config('permission-delegation.permission_model');

            return new SpatiePermissionRepository($permissionModel);
        });
    }

    /**
     * Register audit service based on configuration.
     *
     * Using scoped binding for Octane compatibility.
     */
    private function registerAuditService(): void
    {
        $this->app->scoped(DelegationAuditInterface::class, function (): DelegationAuditInterface {
            if (! config('permission-delegation.audit.enabled', true)) {
                return new NullDelegationAudit;
            }

            /** @var string $driver */
            $driver = config('permission-delegation.audit.driver', 'database');

            return match ($driver) {
                'database' => new DatabaseDelegationAudit,
                'log' => new LogDelegationAudit(
                    config('permission-delegation.audit.log_channel', 'stack'),
                ),
                'null' => new NullDelegationAudit,
                default => $this->app->make($driver),
            };
        });
    }

    /**
     * Register the main delegation service.
     *
     * Using scoped binding for Octane compatibility.
     */
    private function registerDelegationService(): void
    {
        $this->app->scoped(DelegationServiceInterface::class, function (): DelegationServiceInterface {
            $service = new DelegationService(
                delegationRepository: $this->app->make(DelegationRepositoryInterface::class),
                roleRepository: $this->app->make(RoleRepositoryInterface::class),
                permissionRepository: $this->app->make(PermissionRepositoryInterface::class),
                audit: $this->app->make(DelegationAuditInterface::class),
                superAdminBypassEnabled: (bool) config('permission-delegation.super_admin.enabled', true),
                superAdminIdentifier: config('permission-delegation.super_admin.role'),
            );

            if (config('permission-delegation.cache.enabled', true)) {
                return new CachedDelegationService(
                    inner: $service,
                    cache: $this->app->make('cache.store'),
                    ttl: (int) config('permission-delegation.cache.ttl', 3600),
                    prefix: (string) config('permission-delegation.cache.prefix', 'delegation_'),
                );
            }

            return $service;
        });

        $this->app->alias(DelegationServiceInterface::class, 'delegation');
    }

    /**
     * Register middleware aliases.
     */
    private function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);

        $router->aliasMiddleware('can.delegate', CanDelegateMiddleware::class);
        $router->aliasMiddleware('can.assign.role', CanAssignRoleMiddleware::class);
        $router->aliasMiddleware('can.manage.user', CanManageUserMiddleware::class);
    }

    /**
     * Register Blade directives.
     */
    private function registerBladeDirectives(): void
    {
        Blade::if('canDelegate', static function (): bool {
            $user = auth()->user();

            if (! $user instanceof DelegatableUserInterface) {
                return false;
            }

            return app(DelegationServiceInterface::class)->canCreateUsers($user);
        });

        Blade::if('canAssignRole', static function (string $roleName): bool {
            $user = auth()->user();

            if (! $user instanceof DelegatableUserInterface) {
                return false;
            }

            $role = app(RoleRepositoryInterface::class)->findByName($roleName);

            if ($role === null) {
                return false;
            }

            return app(DelegationServiceInterface::class)->canAssignRole($user, $role);
        });

        Blade::if('canManageUser', static function (DelegatableUserInterface $targetUser): bool {
            $user = auth()->user();

            if (! $user instanceof DelegatableUserInterface) {
                return false;
            }

            return app(DelegationServiceInterface::class)->canManageUser($user, $targetUser);
        });
    }

    /**
     * Publish configuration file.
     */
    private function publishConfig(): void
    {
        $this->publishes([
            __DIR__.'/../Config/permission-delegation.php' => config_path('permission-delegation.php'),
        ], 'delegation-config');
    }

    /**
     * Publish migration files.
     */
    private function publishMigrations(): void
    {
        $this->publishes([
            __DIR__.'/../Database/Migrations/' => database_path('migrations'),
        ], 'delegation-migrations');
    }
}
