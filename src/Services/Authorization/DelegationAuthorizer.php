<?php

declare(strict_types=1);

namespace Ordain\Delegation\Services\Authorization;

use Ordain\Delegation\Contracts\AuthorizationPipelineInterface;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\DelegationAuthorizerInterface;
use Ordain\Delegation\Contracts\PermissionInterface;
use Ordain\Delegation\Contracts\RoleInterface;

/**
 * Handles all delegation authorization checks using the authorization pipeline.
 */
final readonly class DelegationAuthorizer implements DelegationAuthorizerInterface
{
    public function __construct(
        private AuthorizationPipelineInterface $pipeline,
    ) {}

    public function canAssignRole(
        DelegatableUserInterface $delegator,
        RoleInterface $role,
        ?DelegatableUserInterface $target = null,
    ): bool {
        return $this->pipeline->canAssignRole($delegator, $role, $target);
    }

    public function canAssignPermission(
        DelegatableUserInterface $delegator,
        PermissionInterface $permission,
        ?DelegatableUserInterface $target = null,
    ): bool {
        return $this->pipeline->canAssignPermission($delegator, $permission, $target);
    }

    public function canRevokeRole(
        DelegatableUserInterface $delegator,
        RoleInterface $role,
        DelegatableUserInterface $target,
    ): bool {
        return $this->pipeline->canAssignRole($delegator, $role, $target);
    }

    public function canRevokePermission(
        DelegatableUserInterface $delegator,
        PermissionInterface $permission,
        DelegatableUserInterface $target,
    ): bool {
        return $this->pipeline->canAssignPermission($delegator, $permission, $target);
    }

    public function canManageUser(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
    ): bool {
        return $this->pipeline->canManageUser($delegator, $target);
    }
}
