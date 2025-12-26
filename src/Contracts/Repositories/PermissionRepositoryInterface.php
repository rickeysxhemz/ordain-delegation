<?php

declare(strict_types=1);

namespace Ordain\Delegation\Contracts\Repositories;

use Illuminate\Support\Collection;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\PermissionInterface;

/**
 * Repository interface for permission operations.
 *
 * Implement this to integrate with your permission system (Spatie, custom, etc.)
 */
interface PermissionRepositoryInterface
{
    /**
     * Find a permission by its identifier.
     */
    public function findById(int|string $id): ?PermissionInterface;

    /**
     * Find multiple permissions by their identifiers.
     *
     * @param  array<int|string>  $ids
     * @return Collection<int, PermissionInterface>
     */
    public function findByIds(array $ids): Collection;

    /**
     * Find a permission by its name.
     */
    public function findByName(string $name, ?string $guard = null): ?PermissionInterface;

    /**
     * Get all permissions.
     *
     * @return Collection<int, PermissionInterface>
     */
    public function all(?string $guard = null): Collection;

    /**
     * Get permissions assigned directly to a user.
     *
     * @return Collection<int, PermissionInterface>
     */
    public function getUserPermissions(DelegatableUserInterface $user): Collection;

    /**
     * Get all permissions a user has (direct + via roles).
     *
     * @return Collection<int, PermissionInterface>
     */
    public function getAllUserPermissions(DelegatableUserInterface $user): Collection;

    /**
     * Assign a permission directly to a user.
     */
    public function assignToUser(DelegatableUserInterface $user, PermissionInterface $permission): void;

    /**
     * Remove a permission from a user.
     */
    public function removeFromUser(DelegatableUserInterface $user, PermissionInterface $permission): void;

    /**
     * Check if user has a specific permission (direct or via role).
     */
    public function userHasPermission(DelegatableUserInterface $user, PermissionInterface $permission): bool;

    /**
     * Sync permissions for a user (replace all direct permissions).
     *
     * @param  array<int|string>  $permissionIds
     */
    public function syncUserPermissions(DelegatableUserInterface $user, array $permissionIds): void;
}
