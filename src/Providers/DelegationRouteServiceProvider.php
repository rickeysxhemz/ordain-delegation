<?php

declare(strict_types=1);

namespace Ordain\Delegation\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Ordain\Delegation\Http\Middleware\CanAssignRoleMiddleware;
use Ordain\Delegation\Http\Middleware\CanDelegateMiddleware;
use Ordain\Delegation\Http\Middleware\CanManageUserMiddleware;
use Ordain\Delegation\Routing\RouteMacros;

/**
 * Registers middleware and route macros for delegation.
 */
final class DelegationRouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerMiddleware();

        RouteMacros::register();
    }

    private function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);

        $router->aliasMiddleware('can.delegate', CanDelegateMiddleware::class);
        $router->aliasMiddleware('can.assign.role', CanAssignRoleMiddleware::class);
        $router->aliasMiddleware('can.manage.user', CanManageUserMiddleware::class);
    }
}
