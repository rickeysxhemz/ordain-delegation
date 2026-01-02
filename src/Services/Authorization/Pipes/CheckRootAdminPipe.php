<?php

declare(strict_types=1);

namespace Ordain\Delegation\Services\Authorization\Pipes;

use Closure;
use Ordain\Delegation\Contracts\RootAdminResolverInterface;
use Ordain\Delegation\Services\Authorization\AuthorizationContext;

/**
 * Grants access if the delegator is a root admin.
 */
final readonly class CheckRootAdminPipe implements AuthorizationPipeInterface
{
    public function __construct(
        private RootAdminResolverInterface $rootAdminResolver,
    ) {}

    public function handle(AuthorizationContext $context, Closure $next): AuthorizationContext
    {
        if ($this->rootAdminResolver->isRootAdmin($context->delegator)) {
            return $context->grant();
        }

        return $next($context);
    }
}
