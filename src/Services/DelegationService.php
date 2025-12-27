<?php

declare(strict_types=1);

namespace Ordain\Delegation\Services;

use Illuminate\Support\Collection;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\DelegationAuditInterface;
use Ordain\Delegation\Contracts\DelegationAuthorizerInterface;
use Ordain\Delegation\Contracts\DelegationServiceInterface;
use Ordain\Delegation\Contracts\DelegationValidatorInterface;
use Ordain\Delegation\Contracts\EventDispatcherInterface;
use Ordain\Delegation\Contracts\PermissionInterface;
use Ordain\Delegation\Contracts\QuotaManagerInterface;
use Ordain\Delegation\Contracts\Repositories\DelegationRepositoryInterface;
use Ordain\Delegation\Contracts\Repositories\PermissionRepositoryInterface;
use Ordain\Delegation\Contracts\Repositories\RoleRepositoryInterface;
use Ordain\Delegation\Contracts\RoleInterface;
use Ordain\Delegation\Contracts\RootAdminResolverInterface;
use Ordain\Delegation\Contracts\TransactionManagerInterface;
use Ordain\Delegation\Domain\ValueObjects\DelegationScope;
use Ordain\Delegation\Events\DelegationScopeUpdated;
use Ordain\Delegation\Events\PermissionGranted;
use Ordain\Delegation\Events\PermissionRevoked;
use Ordain\Delegation\Events\RoleDelegated;
use Ordain\Delegation\Events\RoleRevoked;
use Ordain\Delegation\Exceptions\UnauthorizedDelegationException;

/**
 * Orchestrates delegation operations via specialized services.
 */
final readonly class DelegationService implements DelegationServiceInterface
{
    public function __construct(
        private DelegationAuthorizerInterface $authorizer,
        private QuotaManagerInterface $quotaManager,
        private DelegationValidatorInterface $validator,
        private RootAdminResolverInterface $rootAdminResolver,
        private DelegationRepositoryInterface $delegationRepository,
        private RoleRepositoryInterface $roleRepository,
        private PermissionRepositoryInterface $permissionRepository,
        private TransactionManagerInterface $transactionManager,
        private EventDispatcherInterface $eventDispatcher,
        private ?DelegationAuditInterface $audit = null,
    ) {}

    public function canAssignRole(
        DelegatableUserInterface $delegator,
        RoleInterface $role,
        ?DelegatableUserInterface $target = null,
    ): bool {
        return $this->authorizer->canAssignRole($delegator, $role, $target);
    }

    public function canAssignPermission(
        DelegatableUserInterface $delegator,
        PermissionInterface $permission,
        ?DelegatableUserInterface $target = null,
    ): bool {
        return $this->authorizer->canAssignPermission($delegator, $permission, $target);
    }

    public function canRevokeRole(
        DelegatableUserInterface $delegator,
        RoleInterface $role,
        DelegatableUserInterface $target,
    ): bool {
        return $this->authorizer->canRevokeRole($delegator, $role, $target);
    }

    public function canRevokePermission(
        DelegatableUserInterface $delegator,
        PermissionInterface $permission,
        DelegatableUserInterface $target,
    ): bool {
        return $this->authorizer->canRevokePermission($delegator, $permission, $target);
    }

    public function canCreateUsers(DelegatableUserInterface $delegator): bool
    {
        return $this->quotaManager->canCreateUsers($delegator);
    }

    public function withQuotaLock(
        DelegatableUserInterface $delegator,
        callable $callback,
    ): DelegatableUserInterface {
        return $this->quotaManager->withLock($delegator, $callback);
    }

    public function hasReachedUserLimit(DelegatableUserInterface $delegator): bool
    {
        return $this->quotaManager->hasReachedLimit($delegator);
    }

    public function getCreatedUsersCount(DelegatableUserInterface $delegator): int
    {
        return $this->quotaManager->getCreatedUsersCount($delegator);
    }

    public function getRemainingUserQuota(DelegatableUserInterface $delegator): ?int
    {
        return $this->quotaManager->getRemainingQuota($delegator);
    }

    public function getAssignableRoles(DelegatableUserInterface $delegator): Collection
    {
        if ($this->rootAdminResolver->isRootAdmin($delegator)) {
            return $this->roleRepository->all();
        }

        return $this->delegationRepository->getAssignableRoles($delegator);
    }

    public function getAssignablePermissions(DelegatableUserInterface $delegator): Collection
    {
        if ($this->rootAdminResolver->isRootAdmin($delegator)) {
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

        $this->transactionManager->transaction(function () use ($user, $scope): void {
            $this->delegationRepository->updateDelegationSettings(
                $user,
                $scope->canManageUsers,
                $scope->maxManageableUsers,
            );

            $this->delegationRepository->syncAssignableRoles($user, $scope->assignableRoleIds);
            $this->delegationRepository->syncAssignablePermissions($user, $scope->assignablePermissionIds);
        });

        if (! $oldScope->equals($scope)) {
            $this->audit?->logDelegationScopeChanged($admin ?? $user, $user, [
                'old' => $oldScope->toArray(),
                'new' => $scope->toArray(),
            ]);

            $this->eventDispatcher->dispatch(new DelegationScopeUpdated($user, $oldScope, $scope, $admin));
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
        if (! $this->authorizer->canAssignRole($delegator, $role, $target)) {
            $this->audit?->logUnauthorizedAttempt($delegator, 'assign_role', [
                'role' => $role->getRoleName(),
                'target' => $target->getDelegatableIdentifier(),
            ]);

            throw UnauthorizedDelegationException::cannotAssignRole($delegator, $role->getRoleName());
        }

        $this->roleRepository->assignToUser($target, $role);
        $this->audit?->logRoleAssigned($delegator, $target, $role);
        $this->eventDispatcher->dispatch(new RoleDelegated($delegator, $target, $role));
    }

    public function delegatePermission(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        PermissionInterface $permission,
    ): void {
        if (! $this->authorizer->canAssignPermission($delegator, $permission, $target)) {
            $this->audit?->logUnauthorizedAttempt($delegator, 'grant_permission', [
                'permission' => $permission->getPermissionName(),
                'target' => $target->getDelegatableIdentifier(),
            ]);

            throw UnauthorizedDelegationException::cannotGrantPermission($delegator, $permission->getPermissionName());
        }

        $this->permissionRepository->assignToUser($target, $permission);
        $this->audit?->logPermissionGranted($delegator, $target, $permission);
        $this->eventDispatcher->dispatch(new PermissionGranted($delegator, $target, $permission));
    }

    public function revokeRole(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        RoleInterface $role,
    ): void {
        if (! $this->authorizer->canRevokeRole($delegator, $role, $target)) {
            $this->audit?->logUnauthorizedAttempt($delegator, 'revoke_role', [
                'role' => $role->getRoleName(),
                'target' => $target->getDelegatableIdentifier(),
            ]);

            throw UnauthorizedDelegationException::cannotRevokeRole($delegator, $role->getRoleName());
        }

        $this->roleRepository->removeFromUser($target, $role);
        $this->audit?->logRoleRevoked($delegator, $target, $role);
        $this->eventDispatcher->dispatch(new RoleRevoked($delegator, $target, $role));
    }

    public function revokePermission(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        PermissionInterface $permission,
    ): void {
        if (! $this->authorizer->canRevokePermission($delegator, $permission, $target)) {
            $this->audit?->logUnauthorizedAttempt($delegator, 'revoke_permission', [
                'permission' => $permission->getPermissionName(),
                'target' => $target->getDelegatableIdentifier(),
            ]);

            throw UnauthorizedDelegationException::cannotRevokePermission($delegator, $permission->getPermissionName());
        }

        $this->permissionRepository->removeFromUser($target, $permission);
        $this->audit?->logPermissionRevoked($delegator, $target, $permission);
        $this->eventDispatcher->dispatch(new PermissionRevoked($delegator, $target, $permission));
    }

    public function canManageUser(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
    ): bool {
        return $this->authorizer->canManageUser($delegator, $target);
    }

    /**
     * @param  array<int|string>  $roles
     * @param  array<int|string>  $permissions
     * @return array<string, string>
     */
    public function validateDelegation(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        array $roles = [],
        array $permissions = [],
    ): array {
        return $this->validator->validate($delegator, $target, $roles, $permissions);
    }
}
