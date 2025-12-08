<?php

declare(strict_types=1);

namespace Ordain\Delegation\Contracts;

use Illuminate\Support\Collection;
use Ordain\Delegation\Domain\ValueObjects\DelegationScope;
use Ordain\Delegation\Exceptions\UnauthorizedDelegationException;

/**
 * Main service interface for permission delegation operations.
 *
 * This is the primary API for the delegation system.
 */
interface DelegationServiceInterface
{
    /**
     * Check if a delegator can assign a specific role to a target user.
     */
    public function canAssignRole(
        DelegatableUserInterface $delegator,
        RoleInterface $role,
        ?DelegatableUserInterface $target = null,
    ): bool;

    /**
     * Check if a delegator can assign a specific permission to a target user.
     */
    public function canAssignPermission(
        DelegatableUserInterface $delegator,
        PermissionInterface $permission,
        ?DelegatableUserInterface $target = null,
    ): bool;

    /**
     * Check if a delegator can revoke a specific role from a target user.
     */
    public function canRevokeRole(
        DelegatableUserInterface $delegator,
        RoleInterface $role,
        DelegatableUserInterface $target,
    ): bool;

    /**
     * Check if a delegator can revoke a specific permission from a target user.
     */
    public function canRevokePermission(
        DelegatableUserInterface $delegator,
        PermissionInterface $permission,
        DelegatableUserInterface $target,
    ): bool;

    /**
     * Check if a delegator can create new users.
     */
    public function canCreateUsers(DelegatableUserInterface $delegator): bool;

    /**
     * Atomically check and reserve quota for user creation.
     *
     * @param  callable(): DelegatableUserInterface  $callback
     *
     * @throws UnauthorizedDelegationException
     */
    public function withQuotaLock(
        DelegatableUserInterface $delegator,
        callable $callback,
    ): DelegatableUserInterface;

    /**
     * Check if a delegator has reached their user creation limit.
     */
    public function hasReachedUserLimit(DelegatableUserInterface $delegator): bool;

    /**
     * Get the count of users created by a delegator.
     */
    public function getCreatedUsersCount(DelegatableUserInterface $delegator): int;

    /**
     * Get remaining user creation quota for a delegator.
     * Returns null for unlimited.
     */
    public function getRemainingUserQuota(DelegatableUserInterface $delegator): ?int;

    /**
     * Get all roles that a delegator can assign.
     *
     * @return Collection<int, RoleInterface>
     */
    public function getAssignableRoles(DelegatableUserInterface $delegator): Collection;

    /**
     * Get all permissions that a delegator can grant.
     *
     * @return Collection<int, PermissionInterface>
     */
    public function getAssignablePermissions(DelegatableUserInterface $delegator): Collection;

    public function setDelegationScope(
        DelegatableUserInterface $user,
        DelegationScope $scope,
        ?DelegatableUserInterface $admin = null,
    ): void;

    /**
     * Get the delegation scope for a user.
     */
    public function getDelegationScope(DelegatableUserInterface $user): DelegationScope;

    /**
     * Assign a role through delegation (with validation).
     *
     * @throws UnauthorizedDelegationException
     */
    public function delegateRole(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        RoleInterface $role,
    ): void;

    /**
     * Grant a permission through delegation (with validation).
     *
     * @throws UnauthorizedDelegationException
     */
    public function delegatePermission(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        PermissionInterface $permission,
    ): void;

    /**
     * Revoke a role through delegation (with validation).
     *
     * @throws UnauthorizedDelegationException
     */
    public function revokeRole(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        RoleInterface $role,
    ): void;

    /**
     * Revoke a permission through delegation (with validation).
     *
     * @throws UnauthorizedDelegationException
     */
    public function revokePermission(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        PermissionInterface $permission,
    ): void;

    /**
     * Check if delegator can manage target user.
     */
    public function canManageUser(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
    ): bool;

    /**
     * Validate entire delegation operation before executing.
     *
     * @return array<string, string> Validation errors (empty if valid)
     */
    public function validateDelegation(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        array $roles = [],
        array $permissions = [],
    ): array;
}
