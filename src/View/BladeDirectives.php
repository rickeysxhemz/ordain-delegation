<?php

declare(strict_types=1);

namespace Ordain\Delegation\View;

use Illuminate\Support\Facades\Blade;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\DelegationServiceInterface;
use Ordain\Delegation\Contracts\Repositories\RoleRepositoryInterface;

final class BladeDirectives
{
    public static function register(): void
    {
        self::registerCanDelegate();
        self::registerCanAssignRole();
        self::registerCanManageUser();
    }

    private static function registerCanDelegate(): void
    {
        Blade::if('canDelegate', static function (): bool {
            $user = auth()->user();

            if (! $user instanceof DelegatableUserInterface) {
                return false;
            }

            return app(DelegationServiceInterface::class)->canCreateUsers($user);
        });
    }

    private static function registerCanAssignRole(): void
    {
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
    }

    private static function registerCanManageUser(): void
    {
        Blade::if('canManageUser', static function (DelegatableUserInterface $targetUser): bool {
            $user = auth()->user();

            if (! $user instanceof DelegatableUserInterface) {
                return false;
            }

            return app(DelegationServiceInterface::class)->canManageUser($user, $targetUser);
        });
    }
}
