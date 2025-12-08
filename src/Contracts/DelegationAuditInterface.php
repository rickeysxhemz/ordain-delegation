<?php

declare(strict_types=1);

namespace Ordain\Delegation\Contracts;

/**
 * Interface for audit logging of delegation operations.
 *
 * Implement this to customize audit logging behavior.
 */
interface DelegationAuditInterface
{
    /**
     * Log a role assignment.
     */
    public function logRoleAssigned(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        RoleInterface $role,
    ): void;

    /**
     * Log a role revocation.
     */
    public function logRoleRevoked(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        RoleInterface $role,
    ): void;

    /**
     * Log a permission grant.
     */
    public function logPermissionGranted(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        PermissionInterface $permission,
    ): void;

    /**
     * Log a permission revocation.
     */
    public function logPermissionRevoked(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        PermissionInterface $permission,
    ): void;

    /**
     * Log a delegation scope change.
     *
     * @param  array{old: array<string, bool|int|array<int|string>|null>, new: array<string, bool|int|array<int|string>|null>}  $changes
     */
    public function logDelegationScopeChanged(
        DelegatableUserInterface $admin,
        DelegatableUserInterface $user,
        array $changes,
    ): void;

    /**
     * Log an unauthorized delegation attempt.
     *
     * @param  array<string, int|string>  $context
     */
    public function logUnauthorizedAttempt(
        DelegatableUserInterface $delegator,
        string $action,
        array $context = [],
    ): void;

    /**
     * Log user creation through delegation.
     */
    public function logUserCreated(
        DelegatableUserInterface $creator,
        DelegatableUserInterface $createdUser,
    ): void;
}
