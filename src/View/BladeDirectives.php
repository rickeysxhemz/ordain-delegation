<?php

declare(strict_types=1);

namespace Ordain\Delegation\View;

use Illuminate\Support\Facades\Blade;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\DelegationServiceInterface;
use Ordain\Delegation\Contracts\Repositories\RoleRepositoryInterface;

/**
 * Registers Blade directives for delegation permission checks.
 */
final readonly class BladeDirectives
{
    public function register(): void
    {
        $this->registerCanDelegate();
        $this->registerCanAssignRole();
        $this->registerCanManageUser();
    }

    private function registerCanDelegate(): void
    {
        Blade::if('canDelegate', static function (): bool {
            $user = auth()->user();

            if (! $user instanceof DelegatableUserInterface) {
                return false;
            }

            return app(DelegationServiceInterface::class)->canCreateUsers($user);
        });
    }

    private function registerCanAssignRole(): void
    {
        Blade::if('canAssignRole', static function (string $roleName): bool {
            $user = auth()->user();

            if (! $user instanceof DelegatableUserInterface) {
                return false;
            }

            $roleRepository = app(RoleRepositoryInterface::class);
            $role = $roleRepository->findByName($roleName);

            if ($role === null) {
                return false;
            }

            return app(DelegationServiceInterface::class)->canAssignRole($user, $role);
        });
    }

    private function registerCanManageUser(): void
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
