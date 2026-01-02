<?php

declare(strict_types=1);

namespace Ordain\Delegation\Contracts;

/**
 * Processes authorization checks through a series of pipes.
 */
interface AuthorizationPipelineInterface
{
    public function canAssignRole(
        DelegatableUserInterface $delegator,
        RoleInterface $role,
        ?DelegatableUserInterface $target = null,
    ): bool;

    public function canAssignPermission(
        DelegatableUserInterface $delegator,
        PermissionInterface $permission,
        ?DelegatableUserInterface $target = null,
    ): bool;

    public function canManageUser(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
    ): bool;
}
