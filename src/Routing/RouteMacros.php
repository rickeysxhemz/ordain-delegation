<?php

declare(strict_types=1);

namespace Ordain\Delegation\Routing;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;

final class RouteMacros
{
    public static function register(): void
    {
        self::registerCanDelegate();
        self::registerCanAssignRole();
        self::registerCanManageUser();
    }

    private static function registerCanDelegate(): void
    {
        Route::macro('canDelegate', function (): mixed {
            /** @phpstan-ignore-next-line */
            return $this->middleware('can.delegate');
        });
    }

    private static function registerCanAssignRole(): void
    {
        /** @param string|array<string> $roles */
        Route::macro('canAssignRole', function (string|array $roles): mixed {
            $rolesString = implode(',', Arr::wrap($roles));

            /** @phpstan-ignore-next-line */
            return $this->middleware("can.assign.role:{$rolesString}");
        });
    }

    private static function registerCanManageUser(): void
    {
        Route::macro('canManageUser', function (?string $userParameter = 'user'): mixed {
            /** @phpstan-ignore-next-line */
            return $this->middleware("can.manage.user:{$userParameter}");
        });
    }
}
