<?php

declare(strict_types=1);

namespace Ordain\Delegation\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Collection;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\DelegationServiceInterface;
use Ordain\Delegation\Contracts\PermissionInterface;
use Ordain\Delegation\Contracts\RoleInterface;
use Ordain\Delegation\Domain\ValueObjects\DelegationScope;

/**
 * Caching decorator for DelegationService.
 *
 * Wraps the delegation service to cache frequently accessed data
 * and reduce database queries.
 */
final readonly class CachedDelegationService implements DelegationServiceInterface
{
    public function __construct(
        private DelegationServiceInterface $inner,
        private CacheRepository $cache,
        private int $ttl = 3600,
        private string $prefix = 'delegation_',
    ) {}

    public function canAssignRole(
        DelegatableUserInterface $delegator,
        RoleInterface $role,
        ?DelegatableUserInterface $target = null,
    ): bool {
        // Don't cache when target is involved (too many permutations)
        if ($target !== null) {
            return $this->inner->canAssignRole($delegator, $role, $target);
        }

        $key = $this->cacheKey('can_assign_role', $delegator, (string) $role->getRoleIdentifier());

        return $this->cache->remember($key, $this->ttl, fn (): bool => $this->inner->canAssignRole($delegator, $role));
    }

    public function canAssignPermission(
        DelegatableUserInterface $delegator,
        PermissionInterface $permission,
        ?DelegatableUserInterface $target = null,
    ): bool {
        if ($target !== null) {
            return $this->inner->canAssignPermission($delegator, $permission, $target);
        }

        $key = $this->cacheKey('can_assign_perm', $delegator, (string) $permission->getPermissionIdentifier());

        return $this->cache->remember($key, $this->ttl, fn (): bool => $this->inner->canAssignPermission($delegator, $permission));
    }

    public function canRevokeRole(
        DelegatableUserInterface $delegator,
        RoleInterface $role,
        DelegatableUserInterface $target,
    ): bool {
        return $this->inner->canRevokeRole($delegator, $role, $target);
    }

    public function canRevokePermission(
        DelegatableUserInterface $delegator,
        PermissionInterface $permission,
        DelegatableUserInterface $target,
    ): bool {
        return $this->inner->canRevokePermission($delegator, $permission, $target);
    }

    public function canCreateUsers(DelegatableUserInterface $delegator): bool
    {
        $key = $this->cacheKey('can_create_users', $delegator);

        return $this->cache->remember($key, $this->ttl, fn (): bool => $this->inner->canCreateUsers($delegator));
    }

    public function withQuotaLock(
        DelegatableUserInterface $delegator,
        callable $callback,
    ): DelegatableUserInterface {
        $result = $this->inner->withQuotaLock($delegator, $callback);
        $this->forgetUserCache($delegator);

        return $result;
    }

    public function hasReachedUserLimit(DelegatableUserInterface $delegator): bool
    {
        // Don't cache - this changes frequently
        return $this->inner->hasReachedUserLimit($delegator);
    }

    public function getCreatedUsersCount(DelegatableUserInterface $delegator): int
    {
        // Don't cache - this changes frequently
        return $this->inner->getCreatedUsersCount($delegator);
    }

    public function getRemainingUserQuota(DelegatableUserInterface $delegator): ?int
    {
        // Don't cache - this changes frequently
        return $this->inner->getRemainingUserQuota($delegator);
    }

    /**
     * @return Collection<int, RoleInterface>
     */
    public function getAssignableRoles(DelegatableUserInterface $delegator): Collection
    {
        $key = $this->cacheKey('assignable_roles', $delegator);

        return $this->cache->remember($key, $this->ttl, fn (): Collection => $this->inner->getAssignableRoles($delegator));
    }

    /**
     * @return Collection<int, PermissionInterface>
     */
    public function getAssignablePermissions(DelegatableUserInterface $delegator): Collection
    {
        $key = $this->cacheKey('assignable_perms', $delegator);

        return $this->cache->remember($key, $this->ttl, fn (): Collection => $this->inner->getAssignablePermissions($delegator));
    }

    public function setDelegationScope(
        DelegatableUserInterface $user,
        DelegationScope $scope,
        ?DelegatableUserInterface $admin = null,
    ): void {
        $this->inner->setDelegationScope($user, $scope, $admin);
        $this->forgetUserCache($user);
    }

    public function getDelegationScope(DelegatableUserInterface $user): DelegationScope
    {
        $key = $this->cacheKey('scope', $user);

        return $this->cache->remember($key, $this->ttl, fn (): DelegationScope => $this->inner->getDelegationScope($user));
    }

    public function delegateRole(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        RoleInterface $role,
    ): void {
        $this->inner->delegateRole($delegator, $target, $role);
        $this->forgetUserCache($target);
        $this->forgetRoleCache($delegator, $role);
    }

    public function delegatePermission(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        PermissionInterface $permission,
    ): void {
        $this->inner->delegatePermission($delegator, $target, $permission);
        $this->forgetUserCache($target);
        $this->forgetPermissionCache($delegator, $permission);
    }

    public function revokeRole(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        RoleInterface $role,
    ): void {
        $this->inner->revokeRole($delegator, $target, $role);
        $this->forgetUserCache($target);
        $this->forgetRoleCache($delegator, $role);
    }

    public function revokePermission(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        PermissionInterface $permission,
    ): void {
        $this->inner->revokePermission($delegator, $target, $permission);
        $this->forgetUserCache($target);
        $this->forgetPermissionCache($delegator, $permission);
    }

    public function canManageUser(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
    ): bool {
        return $this->inner->canManageUser($delegator, $target);
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
        return $this->inner->validateDelegation($delegator, $target, $roles, $permissions);
    }

    /**
     * Clear all cached data for a user.
     */
    public function forgetUserCache(DelegatableUserInterface $user): void
    {
        $keys = [
            $this->cacheKey('scope', $user),
            $this->cacheKey('assignable_roles', $user),
            $this->cacheKey('assignable_perms', $user),
            $this->cacheKey('can_create_users', $user),
        ];

        foreach ($keys as $key) {
            $this->cache->forget($key);
        }
    }

    /**
     * Clear cached role assignment status for a user.
     */
    private function forgetRoleCache(DelegatableUserInterface $user, RoleInterface $role): void
    {
        $this->cache->forget(
            $this->cacheKey('can_assign_role', $user, (string) $role->getRoleIdentifier()),
        );
    }

    /**
     * Clear cached permission assignment status for a user.
     */
    private function forgetPermissionCache(DelegatableUserInterface $user, PermissionInterface $permission): void
    {
        $this->cache->forget(
            $this->cacheKey('can_assign_perm', $user, (string) $permission->getPermissionIdentifier()),
        );
    }

    /**
     * Generate a cache key.
     */
    private function cacheKey(string $type, DelegatableUserInterface $user, string ...$extra): string
    {
        $parts = [$this->prefix, $type, (string) $user->getDelegatableIdentifier(), ...$extra];

        return implode('_', $parts);
    }
}
