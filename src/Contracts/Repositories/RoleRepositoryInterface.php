<?php

declare(strict_types=1);

namespace Ordain\Delegation\Contracts\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\RoleInterface;

/**
 * Repository interface for role operations.
 *
 * Implement this to integrate with your role system (Spatie, custom, etc.)
 */
interface RoleRepositoryInterface
{
    /**
     * Find a role by its identifier.
     */
    public function findById(int|string $id): ?RoleInterface;

    /**
     * Find multiple roles by their identifiers.
     *
     * @param  array<int|string>  $ids
     * @return Collection<int, RoleInterface>
     */
    public function findByIds(array $ids): Collection;

    /**
     * Find a role by its name.
     */
    public function findByName(string $name, ?string $guard = null): ?RoleInterface;

    /**
     * Get all roles.
     *
     * @param  int|null  $limit  Maximum roles to return (null = no limit, use with caution)
     * @return Collection<int, RoleInterface>
     */
    public function all(?string $guard = null, ?int $limit = 500): Collection;

    /**
     * Get all roles as a lazy collection for memory-efficient iteration.
     *
     * @return LazyCollection<int, RoleInterface>
     */
    public function allLazy(?string $guard = null): LazyCollection;

    /**
     * Get roles assigned to a user.
     *
     * @return Collection<int, RoleInterface>
     */
    public function getUserRoles(DelegatableUserInterface $user): Collection;

    /**
     * Assign a role to a user.
     */
    public function assignToUser(DelegatableUserInterface $user, RoleInterface $role): void;

    /**
     * Remove a role from a user.
     */
    public function removeFromUser(DelegatableUserInterface $user, RoleInterface $role): void;

    /**
     * Check if user has a specific role.
     */
    public function userHasRole(DelegatableUserInterface $user, RoleInterface $role): bool;

    /**
     * Check if user has a role by name (optimized single query).
     */
    public function userHasRoleByName(DelegatableUserInterface $user, string $roleName, ?string $guard = null): bool;

    /**
     * Find multiple roles by their names.
     *
     * @param  array<string>  $names
     * @return Collection<int, RoleInterface>
     */
    public function findByNames(array $names, ?string $guard = null): Collection;

    /**
     * Sync roles for a user (replace all).
     *
     * @param  array<int|string>  $roleIds
     */
    public function syncUserRoles(DelegatableUserInterface $user, array $roleIds): void;
}
