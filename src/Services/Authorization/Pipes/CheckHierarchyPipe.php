<?php

declare(strict_types=1);

namespace Ordain\Delegation\Services\Authorization\Pipes;

use Closure;
use Ordain\Delegation\Services\Authorization\AuthorizationContext;

/**
 * Denies access if the delegator is trying to manage a user they didn't create.
 */
final readonly class CheckHierarchyPipe implements AuthorizationPipeInterface
{
    public function handle(AuthorizationContext $context, Closure $next): AuthorizationContext
    {
        if ($context->target === null) {
            return $next($context);
        }

        // Cannot manage yourself
        if ($context->delegator->getDelegatableIdentifier() === $context->target->getDelegatableIdentifier()) {
            return $context->deny('Cannot manage yourself');
        }

        // Must be the creator of the target user
        $creator = $context->target->getCreator();

        if ($creator === null) {
            return $context->deny('Target user has no creator');
        }

        if ($creator->getDelegatableIdentifier() !== $context->delegator->getDelegatableIdentifier()) {
            return $context->deny('Can only manage users you created');
        }

        return $next($context);
    }
}
