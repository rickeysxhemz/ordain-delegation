<?php

declare(strict_types=1);

namespace Ordain\Delegation\Services\Authorization;

use Illuminate\Contracts\Pipeline\Pipeline;
use Ordain\Delegation\Contracts\AuthorizationPipelineInterface;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\PermissionInterface;
use Ordain\Delegation\Contracts\RoleInterface;

/**
 * Processes authorization checks through a series of pipes.
 */
final readonly class AuthorizationPipeline implements AuthorizationPipelineInterface
{
    public function __construct(
        private Pipeline $pipeline,
        /** @var array<object> */
        private array $pipes,
    ) {}

    public function canAssignRole(
        DelegatableUserInterface $delegator,
        RoleInterface $role,
        ?DelegatableUserInterface $target = null,
    ): bool {
        $context = AuthorizationContext::forRoleAssignment($delegator, $role, $target);

        return $this->process($context)->isGranted();
    }

    public function canAssignPermission(
        DelegatableUserInterface $delegator,
        PermissionInterface $permission,
        ?DelegatableUserInterface $target = null,
    ): bool {
        $context = AuthorizationContext::forPermissionAssignment($delegator, $permission, $target);

        return $this->process($context)->isGranted();
    }

    public function canRevokeRole(
        DelegatableUserInterface $delegator,
        RoleInterface $role,
        DelegatableUserInterface $target,
    ): bool {
        $context = AuthorizationContext::forRoleRevocation($delegator, $role, $target);

        return $this->process($context)->isGranted();
    }

    public function canRevokePermission(
        DelegatableUserInterface $delegator,
        PermissionInterface $permission,
        DelegatableUserInterface $target,
    ): bool {
        $context = AuthorizationContext::forPermissionRevocation($delegator, $permission, $target);

        return $this->process($context)->isGranted();
    }

    public function canManageUser(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
    ): bool {
        $context = AuthorizationContext::forUserManagement($delegator, $target);

        return $this->process($context)->isGranted();
    }

    private function process(AuthorizationContext $context): AuthorizationContext
    {
        /** @var AuthorizationContext */
        return $this->pipeline
            ->send($context)
            ->through($this->pipes)
            ->then(fn (AuthorizationContext $ctx): AuthorizationContext => $ctx->isDenied() ? $ctx : $ctx->deny('No authorization granted'));
    }
}
