<?php

declare(strict_types=1);

namespace Ordain\Delegation\Repositories;

use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\PermissionInterface;
use Ordain\Delegation\Contracts\Repositories\DelegationRepositoryInterface;
use Ordain\Delegation\Contracts\RoleInterface;
use Illuminate\Support\Collection;

/**
 * Eloquent implementation of the delegation repository.
 *
 * Uses model relationships defined via the HasDelegation trait.
 */
final class EloquentDelegationRepository implements DelegationRepositoryInterface
{
    public function getAssignableRoles(DelegatableUserInterface $user): Collection
    {
        /** @phpstan-ignore-next-line */
        return $user->assignableRoles()->get();
    }

    public function getAssignablePermissions(DelegatableUserInterface $user): Collection
    {
        /** @phpstan-ignore-next-line */
        return $user->assignablePermissions()->get();
    }

    public function setAssignableRoles(DelegatableUserInterface $user, array $roleIds): void
    {
        /** @phpstan-ignore-next-line */
        $user->assignableRoles()->sync($roleIds);
    }

    public function setAssignablePermissions(DelegatableUserInterface $user, array $permissionIds): void
    {
        /** @phpstan-ignore-next-line */
        $user->assignablePermissions()->sync($permissionIds);
    }

    public function addAssignableRole(DelegatableUserInterface $user, RoleInterface $role): void
    {
        /** @phpstan-ignore-next-line */
        $user->assignableRoles()->syncWithoutDetaching([$role->getRoleIdentifier()]);
    }

    public function removeAssignableRole(DelegatableUserInterface $user, RoleInterface $role): void
    {
        /** @phpstan-ignore-next-line */
        $user->assignableRoles()->detach($role->getRoleIdentifier());
    }

    public function addAssignablePermission(DelegatableUserInterface $user, PermissionInterface $permission): void
    {
        /** @phpstan-ignore-next-line */
        $user->assignablePermissions()->syncWithoutDetaching([$permission->getPermissionIdentifier()]);
    }

    public function removeAssignablePermission(DelegatableUserInterface $user, PermissionInterface $permission): void
    {
        /** @phpstan-ignore-next-line */
        $user->assignablePermissions()->detach($permission->getPermissionIdentifier());
    }

    public function hasAssignableRole(DelegatableUserInterface $user, RoleInterface $role): bool
    {
        /** @phpstan-ignore-next-line */
        return $user->assignableRoles()
            ->where('id', $role->getRoleIdentifier())
            ->exists();
    }

    public function hasAssignablePermission(DelegatableUserInterface $user, PermissionInterface $permission): bool
    {
        /** @phpstan-ignore-next-line */
        return $user->assignablePermissions()
            ->where('id', $permission->getPermissionIdentifier())
            ->exists();
    }

    public function getCreatedUsersCount(DelegatableUserInterface $user): int
    {
        /** @phpstan-ignore-next-line */
        return $user->createdUsers()->count();
    }

    public function updateDelegationSettings(
        DelegatableUserInterface $user,
        bool $canManageUsers,
        ?int $maxManageableUsers
    ): void {
        /** @phpstan-ignore-next-line */
        $user->update([
            'can_manage_users' => $canManageUsers,
            'max_manageable_users' => $maxManageableUsers,
        ]);
    }

    public function syncAssignableRoles(DelegatableUserInterface $user, array $roleIds): void
    {
        /** @phpstan-ignore-next-line */
        $user->assignableRoles()->sync($roleIds);
    }

    public function syncAssignablePermissions(DelegatableUserInterface $user, array $permissionIds): void
    {
        /** @phpstan-ignore-next-line */
        $user->assignablePermissions()->sync($permissionIds);
    }

    public function clearAssignableRoles(DelegatableUserInterface $user): void
    {
        /** @phpstan-ignore-next-line */
        $user->assignableRoles()->detach();
    }

    public function clearAssignablePermissions(DelegatableUserInterface $user): void
    {
        /** @phpstan-ignore-next-line */
        $user->assignablePermissions()->detach();
    }
}