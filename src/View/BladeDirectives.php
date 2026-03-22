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
    /**
     * Resolve the authenticated user using the configured guard.
     */
    private static function resolveUser(): ?DelegatableUserInterface
    {
        /** @var string|null $guard */
        $guard = config('permission-delegation.guard');

        $user = auth($guard)->user();

        if (! $user instanceof DelegatableUserInterface) {
            return null;
        }

        return $user;
    }

    public function register(): void
    {
        $this->registerCanDelegate();
        $this->registerCanAssignRole();
        $this->registerCanManageUser();
    }

    private function registerCanDelegate(): void
    {
        Blade::if('canDelegate', static function (): bool {
            $user = self::resolveUser();

            if ($user === null) {
                return false;
            }

            return app(DelegationServiceInterface::class)->canCreateUsers($user);
        });
    }

    private function registerCanAssignRole(): void
    {
        Blade::if('canAssignRole', static function (string $roleName): bool {
            $user = self::resolveUser();

            if ($user === null) {
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
            $user = self::resolveUser();

            if ($user === null) {
                return false;
            }

            return app(DelegationServiceInterface::class)->canManageUser($user, $targetUser);
        });
    }
}
