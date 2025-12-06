<?php

declare(strict_types=1);

namespace Ordain\Delegation\Facades;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\DelegationServiceInterface;
use Ordain\Delegation\Contracts\PermissionInterface;
use Ordain\Delegation\Contracts\RoleInterface;
use Ordain\Delegation\Domain\ValueObjects\DelegationScope;

/**
 * Facade for the Delegation service.
 *
 * @method static bool canAssignRole(DelegatableUserInterface $delegator, RoleInterface $role, ?DelegatableUserInterface $target = null)
 * @method static bool canAssignPermission(DelegatableUserInterface $delegator, PermissionInterface $permission, ?DelegatableUserInterface $target = null)
 * @method static bool canRevokeRole(DelegatableUserInterface $delegator, RoleInterface $role, DelegatableUserInterface $target)
 * @method static bool canRevokePermission(DelegatableUserInterface $delegator, PermissionInterface $permission, DelegatableUserInterface $target)
 * @method static bool canCreateUsers(DelegatableUserInterface $delegator)
 * @method static bool hasReachedUserLimit(DelegatableUserInterface $delegator)
 * @method static int getCreatedUsersCount(DelegatableUserInterface $delegator)
 * @method static int|null getRemainingUserQuota(DelegatableUserInterface $delegator)
 * @method static Collection<int, RoleInterface> getAssignableRoles(DelegatableUserInterface $delegator)
 * @method static Collection<int, PermissionInterface> getAssignablePermissions(DelegatableUserInterface $delegator)
 * @method static void setDelegationScope(DelegatableUserInterface $user, DelegationScope $scope)
 * @method static DelegationScope getDelegationScope(DelegatableUserInterface $user)
 * @method static void delegateRole(DelegatableUserInterface $delegator, DelegatableUserInterface $target, RoleInterface $role)
 * @method static void delegatePermission(DelegatableUserInterface $delegator, DelegatableUserInterface $target, PermissionInterface $permission)
 * @method static void revokeRole(DelegatableUserInterface $delegator, DelegatableUserInterface $target, RoleInterface $role)
 * @method static void revokePermission(DelegatableUserInterface $delegator, DelegatableUserInterface $target, PermissionInterface $permission)
 * @method static bool canManageUser(DelegatableUserInterface $delegator, DelegatableUserInterface $target)
 * @method static array<string, string> validateDelegation(DelegatableUserInterface $delegator, DelegatableUserInterface $target, array $roles = [], array $permissions = [])
 *
 * @see \Ordain\Delegation\Services\DelegationService
 */
final class Delegation extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return DelegationServiceInterface::class;
    }
}
