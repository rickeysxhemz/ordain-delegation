<?php

declare(strict_types=1);

namespace Ordain\Delegation\Contracts\Repositories;

use Illuminate\Support\Collection;
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
     * Find a role by its name.
     */
    public function findByName(string $name, ?string $guard = null): ?RoleInterface;

    /**
     * Get all roles.
     *
     * @return Collection<int, RoleInterface>
     */
    public function all(?string $guard = null): Collection;

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
     * Sync roles for a user (replace all).
     *
     * @param  array<int|string>  $roleIds
     */
    public function syncUserRoles(DelegatableUserInterface $user, array $roleIds): void;
}
