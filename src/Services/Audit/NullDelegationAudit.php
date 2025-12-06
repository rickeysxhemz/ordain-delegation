<?php

declare(strict_types=1);

namespace Ordain\Delegation\Services\Audit;

use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\DelegationAuditInterface;
use Ordain\Delegation\Contracts\PermissionInterface;
use Ordain\Delegation\Contracts\RoleInterface;

/**
 * Null implementation of audit logging (does nothing).
 *
 * Use this when audit logging is disabled.
 */
final class NullDelegationAudit implements DelegationAuditInterface
{
    public function logRoleAssigned(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        RoleInterface $role
    ): void {
        // No-op
    }

    public function logRoleRevoked(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        RoleInterface $role
    ): void {
        // No-op
    }

    public function logPermissionGranted(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        PermissionInterface $permission
    ): void {
        // No-op
    }

    public function logPermissionRevoked(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        PermissionInterface $permission
    ): void {
        // No-op
    }

    public function logDelegationScopeChanged(
        DelegatableUserInterface $admin,
        DelegatableUserInterface $user,
        array $changes
    ): void {
        // No-op
    }

    public function logUnauthorizedAttempt(
        DelegatableUserInterface $delegator,
        string $action,
        array $context = []
    ): void {
        // No-op
    }

    public function logUserCreated(
        DelegatableUserInterface $creator,
        DelegatableUserInterface $createdUser
    ): void {
        // No-op
    }
}