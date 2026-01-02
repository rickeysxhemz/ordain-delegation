<?php

declare(strict_types=1);

namespace Ordain\Delegation\Tests\Unit;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Collection;
use Mockery;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\DelegationServiceInterface;
use Ordain\Delegation\Contracts\PermissionInterface;
use Ordain\Delegation\Contracts\RoleInterface;
use Ordain\Delegation\Domain\ValueObjects\DelegationScope;
use Ordain\Delegation\Services\CachedDelegationService;

beforeEach(function (): void {
    $this->inner = Mockery::mock(DelegationServiceInterface::class);
    $this->cache = Mockery::mock(CacheRepository::class);
    $this->delegator = Mockery::mock(DelegatableUserInterface::class);
    $this->target = Mockery::mock(DelegatableUserInterface::class);
    $this->role = Mockery::mock(RoleInterface::class);
    $this->permission = Mockery::mock(PermissionInterface::class);

    $this->delegator->shouldReceive('getDelegatableIdentifier')->andReturn(1);
    $this->target->shouldReceive('getDelegatableIdentifier')->andReturn(2);
    $this->role->shouldReceive('getRoleIdentifier')->andReturn(10);
    $this->permission->shouldReceive('getPermissionIdentifier')->andReturn(20);

    $this->service = new CachedDelegationService(
        inner: $this->inner,
        cache: $this->cache,
        ttl: 3600,
        prefix: 'delegation_',
        guardName: 'web',
    );
});

describe('CachedDelegationService', function (): void {
    it('caches canAssignRole without target', function (): void {
        $this->cache->shouldReceive('remember')
            ->once()
            ->withArgs(fn ($key, $ttl, $callback) => str_contains($key, 'car_1_10') && $ttl === 3600)
            ->andReturn(true);

        $result = $this->service->canAssignRole($this->delegator, $this->role);

        expect($result)->toBeTrue();
    });

    it('delegates canAssignRole with target without caching', function (): void {
        $this->inner->shouldReceive('canAssignRole')
            ->once()
            ->with($this->delegator, $this->role, $this->target)
            ->andReturn(true);

        $result = $this->service->canAssignRole($this->delegator, $this->role, $this->target);

        expect($result)->toBeTrue();
    });

    it('caches canAssignPermission without target', function (): void {
        $this->cache->shouldReceive('remember')
            ->once()
            ->withArgs(fn ($key, $ttl, $callback) => str_contains($key, 'cap_1_20') && $ttl === 3600)
            ->andReturn(true);

        $result = $this->service->canAssignPermission($this->delegator, $this->permission);

        expect($result)->toBeTrue();
    });

    it('delegates canAssignPermission with target without caching', function (): void {
        $this->inner->shouldReceive('canAssignPermission')
            ->once()
            ->with($this->delegator, $this->permission, $this->target)
            ->andReturn(false);

        $result = $this->service->canAssignPermission($this->delegator, $this->permission, $this->target);

        expect($result)->toBeFalse();
    });

    it('delegates canRevokeRole to inner service', function (): void {
        $this->inner->shouldReceive('canRevokeRole')
            ->once()
            ->with($this->delegator, $this->role, $this->target)
            ->andReturn(true);

        $result = $this->service->canRevokeRole($this->delegator, $this->role, $this->target);

        expect($result)->toBeTrue();
    });

    it('delegates canRevokePermission to inner service', function (): void {
        $this->inner->shouldReceive('canRevokePermission')
            ->once()
            ->with($this->delegator, $this->permission, $this->target)
            ->andReturn(false);

        $result = $this->service->canRevokePermission($this->delegator, $this->permission, $this->target);

        expect($result)->toBeFalse();
    });

    it('caches canCreateUsers', function (): void {
        $this->cache->shouldReceive('remember')
            ->once()
            ->withArgs(fn ($key, $ttl, $callback) => str_contains($key, 'ccreate_1'))
            ->andReturn(true);

        $result = $this->service->canCreateUsers($this->delegator);

        expect($result)->toBeTrue();
    });

    it('delegates hasReachedUserLimit without caching', function (): void {
        $this->inner->shouldReceive('hasReachedUserLimit')
            ->once()
            ->with($this->delegator)
            ->andReturn(false);

        $result = $this->service->hasReachedUserLimit($this->delegator);

        expect($result)->toBeFalse();
    });

    it('delegates getCreatedUsersCount without caching', function (): void {
        $this->inner->shouldReceive('getCreatedUsersCount')
            ->once()
            ->with($this->delegator)
            ->andReturn(5);

        $result = $this->service->getCreatedUsersCount($this->delegator);

        expect($result)->toBe(5);
    });

    it('delegates getRemainingUserQuota without caching', function (): void {
        $this->inner->shouldReceive('getRemainingUserQuota')
            ->once()
            ->with($this->delegator)
            ->andReturn(10);

        $result = $this->service->getRemainingUserQuota($this->delegator);

        expect($result)->toBe(10);
    });

    it('caches getAssignableRoles', function (): void {
        $roles = new Collection;
        $this->cache->shouldReceive('remember')
            ->once()
            ->withArgs(fn ($key, $ttl, $callback) => str_contains($key, 'aroles_1'))
            ->andReturn($roles);

        $result = $this->service->getAssignableRoles($this->delegator);

        expect($result)->toBe($roles);
    });

    it('caches getAssignablePermissions', function (): void {
        $permissions = new Collection;
        $this->cache->shouldReceive('remember')
            ->once()
            ->withArgs(fn ($key, $ttl, $callback) => str_contains($key, 'aperms_1'))
            ->andReturn($permissions);

        $result = $this->service->getAssignablePermissions($this->delegator);

        expect($result)->toBe($permissions);
    });

    it('caches getDelegationScope', function (): void {
        $scope = DelegationScope::none();
        $this->cache->shouldReceive('remember')
            ->once()
            ->withArgs(fn ($key, $ttl, $callback) => str_contains($key, 'scope'))
            ->andReturn($scope);

        $result = $this->service->getDelegationScope($this->delegator);

        expect($result)->toBe($scope);
    });

    it('invalidates cache on setDelegationScope', function (): void {
        $scope = DelegationScope::none();

        $this->inner->shouldReceive('setDelegationScope')
            ->once()
            ->with($this->delegator, $scope, null);

        $this->cache->shouldReceive('forget')->times(4);

        $this->service->setDelegationScope($this->delegator, $scope);
    });

    it('invalidates cache on delegateRole', function (): void {
        $this->inner->shouldReceive('delegateRole')
            ->once()
            ->with($this->delegator, $this->target, $this->role);

        // Forget target cache (4 keys) + role cache (1 key)
        $this->cache->shouldReceive('forget')->times(5);

        $this->service->delegateRole($this->delegator, $this->target, $this->role);
    });

    it('invalidates cache on delegatePermission', function (): void {
        $this->inner->shouldReceive('delegatePermission')
            ->once()
            ->with($this->delegator, $this->target, $this->permission);

        // Forget target cache (4 keys) + permission cache (1 key)
        $this->cache->shouldReceive('forget')->times(5);

        $this->service->delegatePermission($this->delegator, $this->target, $this->permission);
    });

    it('invalidates cache on revokeRole', function (): void {
        $this->inner->shouldReceive('revokeRole')
            ->once()
            ->with($this->delegator, $this->target, $this->role);

        $this->cache->shouldReceive('forget')->times(5);

        $this->service->revokeRole($this->delegator, $this->target, $this->role);
    });

    it('invalidates cache on revokePermission', function (): void {
        $this->inner->shouldReceive('revokePermission')
            ->once()
            ->with($this->delegator, $this->target, $this->permission);

        $this->cache->shouldReceive('forget')->times(5);

        $this->service->revokePermission($this->delegator, $this->target, $this->permission);
    });

    it('delegates canManageUser to inner service', function (): void {
        $this->inner->shouldReceive('canManageUser')
            ->once()
            ->with($this->delegator, $this->target)
            ->andReturn(true);

        $result = $this->service->canManageUser($this->delegator, $this->target);

        expect($result)->toBeTrue();
    });

    it('delegates validateDelegation to inner service', function (): void {
        $errors = ['error' => 'message'];
        $this->inner->shouldReceive('validateDelegation')
            ->once()
            ->with($this->delegator, $this->target, [1], [2])
            ->andReturn($errors);

        $result = $this->service->validateDelegation($this->delegator, $this->target, [1], [2]);

        expect($result)->toBe($errors);
    });

    it('invalidates cache and delegates withQuotaLock', function (): void {
        $newUser = Mockery::mock(DelegatableUserInterface::class);
        $callback = fn () => $newUser;

        $this->inner->shouldReceive('withQuotaLock')
            ->once()
            ->with($this->delegator, $callback)
            ->andReturn($newUser);

        $this->cache->shouldReceive('forget')->times(4);

        $result = $this->service->withQuotaLock($this->delegator, $callback);

        expect($result)->toBe($newUser);
    });

    it('forgetUserCache clears all user-related cache keys', function (): void {
        $this->cache->shouldReceive('forget')
            ->times(4)
            ->withArgs(fn ($key) => str_contains($key, 'delegation_'));

        $this->service->forgetUserCache($this->delegator);
    });

    it('invalidates cache on delegateRoles', function (): void {
        $role2 = Mockery::mock(RoleInterface::class);
        $role2->shouldReceive('getRoleIdentifier')->andReturn(11);

        $this->inner->shouldReceive('delegateRoles')
            ->once()
            ->with($this->delegator, $this->target, [$this->role, $role2]);

        // Forget target cache (4 keys) + role cache for each role (2 keys)
        $this->cache->shouldReceive('forget')->times(6);

        $this->service->delegateRoles($this->delegator, $this->target, [$this->role, $role2]);
    });

    it('invalidates cache on delegatePermissions', function (): void {
        $perm2 = Mockery::mock(PermissionInterface::class);
        $perm2->shouldReceive('getPermissionIdentifier')->andReturn(21);

        $this->inner->shouldReceive('delegatePermissions')
            ->once()
            ->with($this->delegator, $this->target, [$this->permission, $perm2]);

        // Forget target cache (4 keys) + permission cache for each permission (2 keys)
        $this->cache->shouldReceive('forget')->times(6);

        $this->service->delegatePermissions($this->delegator, $this->target, [$this->permission, $perm2]);
    });

    it('invalidates cache on revokeRoles', function (): void {
        $role2 = Mockery::mock(RoleInterface::class);
        $role2->shouldReceive('getRoleIdentifier')->andReturn(11);

        $this->inner->shouldReceive('revokeRoles')
            ->once()
            ->with($this->delegator, $this->target, [$this->role, $role2]);

        // Forget target cache (4 keys) + role cache for each role (2 keys)
        $this->cache->shouldReceive('forget')->times(6);

        $this->service->revokeRoles($this->delegator, $this->target, [$this->role, $role2]);
    });

    it('invalidates cache on revokePermissions', function (): void {
        $perm2 = Mockery::mock(PermissionInterface::class);
        $perm2->shouldReceive('getPermissionIdentifier')->andReturn(21);

        $this->inner->shouldReceive('revokePermissions')
            ->once()
            ->with($this->delegator, $this->target, [$this->permission, $perm2]);

        // Forget target cache (4 keys) + permission cache for each permission (2 keys)
        $this->cache->shouldReceive('forget')->times(6);

        $this->service->revokePermissions($this->delegator, $this->target, [$this->permission, $perm2]);
    });
});
