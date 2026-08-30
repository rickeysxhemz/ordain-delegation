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
 *
 * Directives are registered once, but the closures they install outlive the
 * request that registered them. Under Octane that matters twice over: the
 * delegation service and role repository are scoped bindings flushed at every
 * request boundary, and Octane serves each request from a *clone* of the booted
 * application, so a container captured at registration stays pointed at the base
 * app forever. Both services are therefore resolved through the `app()` helper on
 * each evaluation, which reads the container Octane makes current per request.
 */
readonly class BladeDirectives
{
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

    /**
     * Resolve the delegation service for the current request.
     */
    protected function delegationService(): DelegationServiceInterface
    {
        return app(DelegationServiceInterface::class);
    }

    /**
     * Resolve the role repository for the current request.
     */
    protected function roleRepository(): RoleRepositoryInterface
    {
        return app(RoleRepositoryInterface::class);
    }

    protected function registerCanDelegate(): void
    {
        Blade::if('canDelegate', function (): bool {
            try {
                $user = self::resolveUser();

                if ($user === null) {
                    return false;
                }

                return $this->delegationService()->canCreateUsers($user);
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

                $role = $this->roleRepository()->findByName($roleName);

                if ($role === null) {
                    return false;
                }

                return $this->delegationService()->canAssignRole($user, $role);
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

                return $this->delegationService()->canManageUser($user, $targetUser);
            } catch (Throwable) {
                return false;
            }
        });
    }
}
