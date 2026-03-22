<?php

declare(strict_types=1);

namespace Ordain\Delegation\Contracts;

use Ordain\Delegation\Domain\ValueObjects\DelegationScope;

/**
 * Factory for creating delegation domain events.
 */
interface DelegationEventFactoryInterface
{
    public function createRoleDelegated(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        RoleInterface $role,
    ): object;

    public function createRoleRevoked(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        RoleInterface $role,
    ): object;

    public function createPermissionGranted(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        PermissionInterface $permission,
    ): object;

    public function createPermissionRevoked(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        PermissionInterface $permission,
    ): object;

    public function createDelegationScopeUpdated(
        DelegatableUserInterface $user,
        DelegationScope $oldScope,
        DelegationScope $newScope,
        ?DelegatableUserInterface $admin = null,
    ): object;
}
