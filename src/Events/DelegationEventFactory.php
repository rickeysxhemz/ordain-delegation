<?php

declare(strict_types=1);

namespace Ordain\Delegation\Events;

use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\DelegationEventFactoryInterface;
use Ordain\Delegation\Contracts\PermissionInterface;
use Ordain\Delegation\Contracts\RoleInterface;
use Ordain\Delegation\Domain\ValueObjects\DelegationScope;

/**
 * Creates delegation domain events.
 */
final readonly class DelegationEventFactory implements DelegationEventFactoryInterface
{
    public function createRoleDelegated(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        RoleInterface $role,
    ): object {
        return new RoleDelegated($delegator, $target, $role);
    }

    public function createRoleRevoked(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        RoleInterface $role,
    ): object {
        return new RoleRevoked($delegator, $target, $role);
    }

    public function createPermissionGranted(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        PermissionInterface $permission,
    ): object {
        return new PermissionGranted($delegator, $target, $permission);
    }

    public function createPermissionRevoked(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        PermissionInterface $permission,
    ): object {
        return new PermissionRevoked($delegator, $target, $permission);
    }

    public function createDelegationScopeUpdated(
        DelegatableUserInterface $user,
        DelegationScope $oldScope,
        DelegationScope $newScope,
        ?DelegatableUserInterface $admin = null,
    ): object {
        return new DelegationScopeUpdated($user, $oldScope, $newScope, $admin);
    }
}
