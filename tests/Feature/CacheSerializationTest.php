<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Ordain\Delegation\Contracts\DelegationServiceInterface;
use Ordain\Delegation\Domain\ValueObjects\DelegationScope;
use Ordain\Delegation\Services\CachedDelegationService;
use Ordain\Delegation\Tests\Fixtures\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Laravel 13 ships cache.serializable_classes => false by default, which
 * blocks unserializing arbitrary PHP objects from the cache. These tests
 * assert the raw cached payloads contain no objects at all, by round-tripping
 * them through unserialize() with allowed_classes disabled — the same
 * restriction Laravel 13 applies.
 */
function expectLaravel13CacheSafe(mixed $payload): void
{
    $restored = unserialize(serialize($payload), ['allowed_classes' => false]);

    expect($restored)->toEqual($payload);
}

beforeEach(function (): void {
    $this->user = User::create([
        'name' => 'Cache User',
        'email' => 'cache@example.com',
        'can_manage_users' => true,
        'max_manageable_users' => 5,
    ]);

    $this->role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
    $this->permission = Permission::create(['name' => 'create-posts', 'guard_name' => 'web']);

    $this->user->assignableRoles()->attach([$this->role->id]);
    $this->user->assignablePermissions()->attach([$this->permission->id]);

    $this->service = $this->app->make(DelegationServiceInterface::class);

    // Mirrors CachedDelegationService::cacheKey()
    $guard = (string) config('permission-delegation.guard', 'web');
    $prefix = (string) config('permission-delegation.cache.prefix', 'delegation_');
    $this->keyFor = fn (string $shortType): string => "{$prefix}{$guard}_{$shortType}_{$this->user->id}";
});

describe('Cache payloads under Laravel 13 serialization hardening', function (): void {
    it('resolves the caching decorator from the container', function (): void {
        expect($this->service)->toBeInstanceOf(CachedDelegationService::class);
    });

    it('stores the delegation scope as an object-free array', function (): void {
        $scope = $this->service->getDelegationScope($this->user);

        $payload = Cache::get(($this->keyFor)('scope'));

        expect($payload)->toBeArray();
        expectLaravel13CacheSafe($payload);
        expect($payload)->toBe($scope->toArray());
    });

    it('stores assignable roles as object-free identifiers', function (): void {
        $this->service->getAssignableRoles($this->user);

        $payload = Cache::get(($this->keyFor)('aroles'));

        expect($payload)->toBeArray();
        expectLaravel13CacheSafe($payload);
        expect($payload)->toBe([$this->role->id]);
    });

    it('stores assignable permissions as object-free identifiers', function (): void {
        $this->service->getAssignablePermissions($this->user);

        $payload = Cache::get(($this->keyFor)('aperms'));

        expect($payload)->toBeArray();
        expectLaravel13CacheSafe($payload);
        expect($payload)->toBe([$this->permission->id]);
    });

    it('rehydrates equivalent results from the cache on a second call', function (): void {
        $missScope = $this->service->getDelegationScope($this->user);
        $missRoles = $this->service->getAssignableRoles($this->user);
        $missPermissions = $this->service->getAssignablePermissions($this->user);

        $hitScope = $this->service->getDelegationScope($this->user);
        $hitRoles = $this->service->getAssignableRoles($this->user);
        $hitPermissions = $this->service->getAssignablePermissions($this->user);

        expect($hitScope->equals($missScope))->toBeTrue()
            ->and($hitRoles->map(fn ($role) => $role->getRoleIdentifier())->all())
            ->toBe($missRoles->map(fn ($role) => $role->getRoleIdentifier())->all())
            ->and($hitPermissions->map(fn ($permission) => $permission->getPermissionIdentifier())->all())
            ->toBe($missPermissions->map(fn ($permission) => $permission->getPermissionIdentifier())->all());
    });

    it('ignores and overwrites stale object payloads from previous package versions', function (): void {
        Cache::put(($this->keyFor)('scope'), DelegationScope::none(), 3600);

        $scope = $this->service->getDelegationScope($this->user);

        expect($scope->canManageUsers)->toBeTrue();
        expectLaravel13CacheSafe(Cache::get(($this->keyFor)('scope')));
    });
});
