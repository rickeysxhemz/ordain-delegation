<?php

declare(strict_types=1);

namespace Ordain\Delegation\Services\Authorization\Pipes;

use Closure;
use Ordain\Delegation\Contracts\Repositories\DelegationRepositoryInterface;
use Ordain\Delegation\Services\Authorization\AuthorizationContext;

/**
 * Checks if the role/permission is in the delegator's assignable scope.
 */
final readonly class CheckRoleInScopePipe implements AuthorizationPipeInterface
{
    public function __construct(
        private DelegationRepositoryInterface $delegationRepository,
    ) {}

    public function handle(AuthorizationContext $context, Closure $next): AuthorizationContext
    {
        // Check role assignment
        if ($context->role !== null) {
            if (! $this->delegationRepository->hasAssignableRole($context->delegator, $context->role)) {
                return $context->deny('Role not in assignable scope');
            }

            return $context->grant();
        }

        // Check permission assignment
        if ($context->permission !== null) {
            if (! $this->delegationRepository->hasAssignablePermission($context->delegator, $context->permission)) {
                return $context->deny('Permission not in assignable scope');
            }

            return $context->grant();
        }

        // For manage_user action, grant if all checks passed
        if ($context->action === 'manage_user') {
            return $context->grant();
        }

        return $next($context);
    }
}
