<?php

declare(strict_types=1);

namespace Ordain\Delegation\Contracts\Repositories;

use Illuminate\Support\Collection;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\PermissionInterface;
use Ordain\Delegation\Contracts\RoleInterface;

/**
 * Repository interface for delegation data operations.
 *
 * Implement this to use your own data storage mechanism.
 */
interface DelegationRepositoryInterface
{
    /**
     * Get all roles assignable by a user.
     *
     * @return Collection<int, RoleInterface>
     */
    public function getAssignableRoles(DelegatableUserInterface $user): Collection;

    /**
     * Get all permissions grantable by a user.
     *
     * @return Collection<int, PermissionInterface>
     */
    public function getAssignablePermissions(DelegatableUserInterface $user): Collection;

    /**
     * Set assignable roles for a user.
     *
     * @param  array<int|string>  $roleIds
     */
    public function setAssignableRoles(DelegatableUserInterface $user, array $roleIds): void;

    /**
     * Set assignable permissions for a user.
     *
     * @param  array<int|string>  $permissionIds
     */
    public function setAssignablePermissions(DelegatableUserInterface $user, array $permissionIds): void;

    /**
     * Add a role to user's assignable roles.
     */
    public function addAssignableRole(DelegatableUserInterface $user, RoleInterface $role): void;

    /**
     * Remove a role from user's assignable roles.
     */
    public function removeAssignableRole(DelegatableUserInterface $user, RoleInterface $role): void;

    /**
     * Add a permission to user's assignable permissions.
     */
    public function addAssignablePermission(DelegatableUserInterface $user, PermissionInterface $permission): void;

    /**
     * Remove a permission from user's assignable permissions.
     */
    public function removeAssignablePermission(DelegatableUserInterface $user, PermissionInterface $permission): void;

    /**
     * Check if user can assign a specific role.
     */
    public function hasAssignableRole(DelegatableUserInterface $user, RoleInterface $role): bool;

    /**
     * Check if user can assign a specific permission.
     */
    public function hasAssignablePermission(DelegatableUserInterface $user, PermissionInterface $permission): bool;

    /**
     * Get count of users created by a user.
     */
    public function getCreatedUsersCount(DelegatableUserInterface $user): int;

    /**
     * Update user's delegation settings.
     */
    public function updateDelegationSettings(
        DelegatableUserInterface $user,
        bool $canManageUsers,
        ?int $maxManageableUsers
    ): void;

    /**
     * Sync all assignable roles for a user.
     *
     * @param  array<int|string>  $roleIds
     */
    public function syncAssignableRoles(DelegatableUserInterface $user, array $roleIds): void;

    /**
     * Sync all assignable permissions for a user.
     *
     * @param  array<int|string>  $permissionIds
     */
    public function syncAssignablePermissions(DelegatableUserInterface $user, array $permissionIds): void;

    /**
     * Clear all assignable roles for a user.
     */
    public function clearAssignableRoles(DelegatableUserInterface $user): void;

    /**
     * Clear all assignable permissions for a user.
     */
    public function clearAssignablePermissions(DelegatableUserInterface $user): void;
}