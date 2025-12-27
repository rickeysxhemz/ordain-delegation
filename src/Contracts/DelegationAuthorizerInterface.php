<?php

declare(strict_types=1);

namespace Ordain\Delegation\Contracts;

/**
 * Authorization checks for delegation operations.
 */
interface DelegationAuthorizerInterface
{
    /**
     * Check if delegator can assign role to optional target.
     */
    public function canAssignRole(
        DelegatableUserInterface $delegator,
        RoleInterface $role,
        ?DelegatableUserInterface $target = null,
    ): bool;

    /**
     * Check if delegator can assign permission to optional target.
     */
    public function canAssignPermission(
        DelegatableUserInterface $delegator,
        PermissionInterface $permission,
        ?DelegatableUserInterface $target = null,
    ): bool;

    /**
     * Check if delegator can revoke role from target.
     */
    public function canRevokeRole(
        DelegatableUserInterface $delegator,
        RoleInterface $role,
        DelegatableUserInterface $target,
    ): bool;

    /**
     * Check if delegator can revoke permission from target.
     */
    public function canRevokePermission(
        DelegatableUserInterface $delegator,
        PermissionInterface $permission,
        DelegatableUserInterface $target,
    ): bool;

    /**
     * Check if delegator can manage target user.
     */
    public function canManageUser(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
    ): bool;
}
