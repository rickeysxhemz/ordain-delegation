<?php

declare(strict_types=1);

namespace Ordain\Delegation\View;

use Illuminate\Support\Facades\Blade;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\DelegationServiceInterface;
use Ordain\Delegation\Contracts\Repositories\RoleRepositoryInterface;
use Throwable;

/**
 * Registers Blade directives for delegation permission checks.
 */
readonly class BladeDirectives
{
    public function __construct(
        private DelegationServiceInterface $delegationService,
        private RoleRepositoryInterface $roleRepository,
    ) {}

    /**
     * Resolve the authenticated user using the configured guard.
     */
    protected static function resolveUser(): ?DelegatableUserInterface
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

    protected function registerCanDelegate(): void
    {
        Blade::if('canDelegate', function (): bool {
            try {
                $user = self::resolveUser();

                if ($user === null) {
                    return false;
                }

                return $this->delegationService->canCreateUsers($user);
            } catch (Throwable) {
                return false;
            }
        });
    }

    protected function registerCanAssignRole(): void
    {
        Blade::if('canAssignRole', function (string $roleName): bool {
            try {
                $user = self::resolveUser();

                if ($user === null) {
                    return false;
                }

                $role = $this->roleRepository->findByName($roleName);

                if ($role === null) {
                    return false;
                }

                return $this->delegationService->canAssignRole($user, $role);
            } catch (Throwable) {
                return false;
            }
        });
    }

    protected function registerCanManageUser(): void
    {
        Blade::if('canManageUser', function (DelegatableUserInterface $targetUser): bool {
            try {
                $user = self::resolveUser();

                if ($user === null) {
                    return false;
                }

                return $this->delegationService->canManageUser($user, $targetUser);
            } catch (Throwable) {
                return false;
            }
        });
    }
}
