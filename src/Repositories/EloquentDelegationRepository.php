<?php

declare(strict_types=1);

namespace Ordain\Delegation\Repositories;

use Illuminate\Support\Collection;
use Ordain\Delegation\Adapters\SpatiePermissionAdapter;
use Ordain\Delegation\Adapters\SpatieRoleAdapter;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\PermissionInterface;
use Ordain\Delegation\Contracts\Repositories\DelegationRepositoryInterface;
use Ordain\Delegation\Contracts\RoleInterface;

/**
 * Eloquent implementation of the delegation repository.
 *
 * Uses model relationships defined via the HasDelegation trait.
 * Wraps Spatie models in adapters that implement RoleInterface/PermissionInterface.
 */
final readonly class EloquentDelegationRepository implements DelegationRepositoryInterface
{
    /**
     * @return Collection<int, RoleInterface>
     */
    public function getAssignableRoles(DelegatableUserInterface $user): Collection
    {
        return SpatieRoleAdapter::collection($user->assignableRoles()->get());
    }

    /**
     * @return Collection<int, PermissionInterface>
     */
    public function getAssignablePermissions(DelegatableUserInterface $user): Collection
    {
        return SpatiePermissionAdapter::collection($user->assignablePermissions()->get());
    }

    public function setAssignableRoles(DelegatableUserInterface $user, array $roleIds): void
    {
        $user->assignableRoles()->sync($roleIds);
    }

    public function setAssignablePermissions(DelegatableUserInterface $user, array $permissionIds): void
    {
        $user->assignablePermissions()->sync($permissionIds);
    }

    public function addAssignableRole(DelegatableUserInterface $user, RoleInterface $role): void
    {
        $user->assignableRoles()->syncWithoutDetaching([$role->getRoleIdentifier()]);
    }

    public function removeAssignableRole(DelegatableUserInterface $user, RoleInterface $role): void
    {
        $user->assignableRoles()->detach($role->getRoleIdentifier());
    }

    public function addAssignablePermission(DelegatableUserInterface $user, PermissionInterface $permission): void
    {
        $user->assignablePermissions()->syncWithoutDetaching([$permission->getPermissionIdentifier()]);
    }

    public function removeAssignablePermission(DelegatableUserInterface $user, PermissionInterface $permission): void
    {
        $user->assignablePermissions()->detach($permission->getPermissionIdentifier());
    }

    public function hasAssignableRole(DelegatableUserInterface $user, RoleInterface $role): bool
    {
        return $user->assignableRoles()
            ->where('id', $role->getRoleIdentifier())
            ->exists();
    }

    public function hasAssignablePermission(DelegatableUserInterface $user, PermissionInterface $permission): bool
    {
        return $user->assignablePermissions()
            ->where('id', $permission->getPermissionIdentifier())
            ->exists();
    }

    public function getCreatedUsersCount(DelegatableUserInterface $user): int
    {
        return $user->createdUsers()->count();
    }

    public function updateDelegationSettings(
        DelegatableUserInterface $user,
        bool $canManageUsers,
        ?int $maxManageableUsers,
    ): void {
        $user->update([
            'can_manage_users' => $canManageUsers,
            'max_manageable_users' => $maxManageableUsers,
        ]);
    }

    public function syncAssignableRoles(DelegatableUserInterface $user, array $roleIds): void
    {
        $user->assignableRoles()->sync($roleIds);
    }

    public function syncAssignablePermissions(DelegatableUserInterface $user, array $permissionIds): void
    {
        $user->assignablePermissions()->sync($permissionIds);
    }

    public function clearAssignableRoles(DelegatableUserInterface $user): void
    {
        $user->assignableRoles()->detach();
    }

    public function clearAssignablePermissions(DelegatableUserInterface $user): void
    {
        $user->assignablePermissions()->detach();
    }
}
