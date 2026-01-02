<?php

declare(strict_types=1);

namespace Ordain\Delegation\Services\Authorization\Pipes;

use Closure;
use Ordain\Delegation\Services\Authorization\AuthorizationContext;

/**
 * Denies access if the delegator cannot manage users.
 */
final readonly class CheckUserManagementPipe implements AuthorizationPipeInterface
{
    public function handle(AuthorizationContext $context, Closure $next): AuthorizationContext
    {
        if (! $context->delegator->canManageUsers()) {
            return $context->deny('User cannot manage other users');
        }

        return $next($context);
    }
}
