<?php

declare(strict_types=1);

namespace Ordain\Delegation\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\DelegationAuditInterface;
use Ordain\Delegation\Contracts\DelegationServiceInterface;
use Ordain\Delegation\Contracts\PermissionInterface;
use Ordain\Delegation\Contracts\Repositories\DelegationRepositoryInterface;
use Ordain\Delegation\Contracts\Repositories\PermissionRepositoryInterface;
use Ordain\Delegation\Contracts\Repositories\RoleRepositoryInterface;
use Ordain\Delegation\Contracts\RoleInterface;
use Ordain\Delegation\Domain\ValueObjects\DelegationScope;
use Ordain\Delegation\Exceptions\UnauthorizedDelegationException;

/**
 * Main delegation service implementing business logic.
 *
 * This service orchestrates all delegation operations while respecting
 * the boundaries defined by user delegation scopes.
 */
final class DelegationService implements DelegationServiceInterface
{
    public function __construct(
        private readonly DelegationRepositoryInterface $delegationRepository,
        private readonly RoleRepositoryInterface $roleRepository,
        private readonly PermissionRepositoryInterface $permissionRepository,
        private readonly ?DelegationAuditInterface $audit = null,
        private readonly bool $superAdminBypassEnabled = true,
        private readonly ?string $superAdminIdentifier = null,
    ) {}

    public function canAssignRole(
        DelegatableUserInterface $delegator,
        RoleInterface $role,
        ?DelegatableUserInterface $target = null,
    ): bool {
        if ($this->isSuperAdmin($delegator)) {
            return true;
        }

        if (! $delegator->canManageUsers()) {
            return false;
        }

        if ($target !== null && ! $this->canManageUser($delegator, $target)) {
            return false;
        }

        return $this->delegationRepository->hasAssignableRole($delegator, $role);
    }

    public function canAssignPermission(
        DelegatableUserInterface $delegator,
        PermissionInterface $permission,
        ?DelegatableUserInterface $target = null,
    ): bool {
        if ($this->isSuperAdmin($delegator)) {
            return true;
        }

        if (! $delegator->canManageUsers()) {
            return false;
        }

        if ($target !== null && ! $this->canManageUser($delegator, $target)) {
            return false;
        }

        return $this->delegationRepository->hasAssignablePermission($delegator, $permission);
    }

    public function canRevokeRole(
        DelegatableUserInterface $delegator,
        RoleInterface $role,
        DelegatableUserInterface $target,
    ): bool {
        // Same logic as assignment - if you can assign it, you can revoke it
        return $this->canAssignRole($delegator, $role, $target);
    }

    public function canRevokePermission(
        DelegatableUserInterface $delegator,
        PermissionInterface $permission,
        DelegatableUserInterface $target,
    ): bool {
        // Same logic as assignment - if you can grant it, you can revoke it
        return $this->canAssignPermission($delegator, $permission, $target);
    }

    public function canCreateUsers(DelegatableUserInterface $delegator): bool
    {
        if ($this->isSuperAdmin($delegator)) {
            return true;
        }

        if (! $delegator->canManageUsers()) {
            return false;
        }

        return ! $this->hasReachedUserLimit($delegator);
    }

    public function hasReachedUserLimit(DelegatableUserInterface $delegator): bool
    {
        if ($this->isSuperAdmin($delegator)) {
            return false;
        }

        $maxUsers = $delegator->getMaxManageableUsers();

        if ($maxUsers === null) {
            return false; // Unlimited
        }

        return $this->getCreatedUsersCount($delegator) >= $maxUsers;
    }

    public function getCreatedUsersCount(DelegatableUserInterface $delegator): int
    {
        return $this->delegationRepository->getCreatedUsersCount($delegator);
    }

    public function getRemainingUserQuota(DelegatableUserInterface $delegator): ?int
    {
        if ($this->isSuperAdmin($delegator)) {
            return null; // Unlimited
        }

        $maxUsers = $delegator->getMaxManageableUsers();

        if ($maxUsers === null) {
            return null; // Unlimited
        }

        $created = $this->getCreatedUsersCount($delegator);

        return max(0, $maxUsers - $created);
    }

    public function getAssignableRoles(DelegatableUserInterface $delegator): Collection
    {
        if ($this->isSuperAdmin($delegator)) {
            return $this->roleRepository->all();
        }

        return $this->delegationRepository->getAssignableRoles($delegator);
    }

    public function getAssignablePermissions(DelegatableUserInterface $delegator): Collection
    {
        if ($this->isSuperAdmin($delegator)) {
            return $this->permissionRepository->all();
        }

        return $this->delegationRepository->getAssignablePermissions($delegator);
    }

    public function setDelegationScope(
        DelegatableUserInterface $user,
        DelegationScope $scope,
        ?DelegatableUserInterface $admin = null,
    ): void {
        $oldScope = $this->getDelegationScope($user);

        DB::transaction(function () use ($user, $scope): void {
            $this->delegationRepository->updateDelegationSettings(
                $user,
                $scope->canManageUsers,
                $scope->maxManageableUsers,
            );

            $this->delegationRepository->syncAssignableRoles($user, $scope->assignableRoleIds);
            $this->delegationRepository->syncAssignablePermissions($user, $scope->assignablePermissionIds);
        });

        if ($admin !== null && ! $oldScope->equals($scope)) {
            $this->audit?->logDelegationScopeChanged($admin, $user, [
                'old' => $oldScope->toArray(),
                'new' => $scope->toArray(),
            ]);
        }
    }

    public function getDelegationScope(DelegatableUserInterface $user): DelegationScope
    {
        $assignableRoles = $this->delegationRepository->getAssignableRoles($user);
        $assignablePermissions = $this->delegationRepository->getAssignablePermissions($user);

        return new DelegationScope(
            canManageUsers: $user->canManageUsers(),
            maxManageableUsers: $user->getMaxManageableUsers(),
            assignableRoleIds: $assignableRoles->map(fn (RoleInterface $r) => $r->getRoleIdentifier())->toArray(),
            assignablePermissionIds: $assignablePermissions->map(fn (PermissionInterface $p) => $p->getPermissionIdentifier())->toArray(),
        );
    }

    public function delegateRole(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        RoleInterface $role,
    ): void {
        if (! $this->canAssignRole($delegator, $role, $target)) {
            $this->audit?->logUnauthorizedAttempt($delegator, 'assign_role', [
                'role' => $role->getRoleName(),
                'target' => $target->getDelegatableIdentifier(),
            ]);

            throw UnauthorizedDelegationException::cannotAssignRole($delegator, $role->getRoleName());
        }

        $this->roleRepository->assignToUser($target, $role);
        $this->audit?->logRoleAssigned($delegator, $target, $role);
    }

    public function delegatePermission(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        PermissionInterface $permission,
    ): void {
        if (! $this->canAssignPermission($delegator, $permission, $target)) {
            $this->audit?->logUnauthorizedAttempt($delegator, 'grant_permission', [
                'permission' => $permission->getPermissionName(),
                'target' => $target->getDelegatableIdentifier(),
            ]);

            throw UnauthorizedDelegationException::cannotGrantPermission($delegator, $permission->getPermissionName());
        }

        $this->permissionRepository->assignToUser($target, $permission);
        $this->audit?->logPermissionGranted($delegator, $target, $permission);
    }

    public function revokeRole(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        RoleInterface $role,
    ): void {
        if (! $this->canRevokeRole($delegator, $role, $target)) {
            $this->audit?->logUnauthorizedAttempt($delegator, 'revoke_role', [
                'role' => $role->getRoleName(),
                'target' => $target->getDelegatableIdentifier(),
            ]);

            throw UnauthorizedDelegationException::cannotRevokeRole($delegator, $role->getRoleName());
        }

        $this->roleRepository->removeFromUser($target, $role);
        $this->audit?->logRoleRevoked($delegator, $target, $role);
    }

    public function revokePermission(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        PermissionInterface $permission,
    ): void {
        if (! $this->canRevokePermission($delegator, $permission, $target)) {
            $this->audit?->logUnauthorizedAttempt($delegator, 'revoke_permission', [
                'permission' => $permission->getPermissionName(),
                'target' => $target->getDelegatableIdentifier(),
            ]);

            throw UnauthorizedDelegationException::cannotRevokePermission($delegator, $permission->getPermissionName());
        }

        $this->permissionRepository->removeFromUser($target, $permission);
        $this->audit?->logPermissionRevoked($delegator, $target, $permission);
    }

    public function canManageUser(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
    ): bool {
        if ($this->isSuperAdmin($delegator)) {
            return true;
        }

        // Cannot manage self
        if ($delegator->getDelegatableIdentifier() === $target->getDelegatableIdentifier()) {
            return false;
        }

        if (! $delegator->canManageUsers()) {
            return false;
        }

        // Can manage users created by this delegator
        $creator = $target->creator;
        if ($creator !== null) {
            return $creator->getDelegatableIdentifier() === $delegator->getDelegatableIdentifier();
        }

        return false;
    }

    public function validateDelegation(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        array $roles = [],
        array $permissions = [],
    ): array {
        $errors = [];

        if (! $this->canManageUser($delegator, $target)) {
            $errors['target'] = 'You are not authorized to manage this user.';
        }

        foreach ($roles as $roleId) {
            $role = $this->roleRepository->findById($roleId);
            if ($role === null) {
                $errors["role_{$roleId}"] = "Role with ID {$roleId} not found.";

                continue;
            }

            if (! $this->canAssignRole($delegator, $role)) {
                $errors["role_{$roleId}"] = "You cannot assign the role '{$role->getRoleName()}'.";
            }
        }

        foreach ($permissions as $permissionId) {
            $permission = $this->permissionRepository->findById($permissionId);
            if ($permission === null) {
                $errors["permission_{$permissionId}"] = "Permission with ID {$permissionId} not found.";

                continue;
            }

            if (! $this->canAssignPermission($delegator, $permission)) {
                $errors["permission_{$permissionId}"] = "You cannot grant the permission '{$permission->getPermissionName()}'.";
            }
        }

        return $errors;
    }

    /**
     * Check if user is a super admin (bypasses all delegation checks).
     */
    private function isSuperAdmin(DelegatableUserInterface $user): bool
    {
        if (! $this->superAdminBypassEnabled) {
            return false;
        }

        if ($this->superAdminIdentifier === null) {
            return false;
        }

        // Check if user has the super admin role/identifier
        $roles = $this->roleRepository->getUserRoles($user);

        foreach ($roles as $role) {
            if ($role->getRoleName() === $this->superAdminIdentifier) {
                return true;
            }
        }

        return false;
    }
}
