<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Ordain\Delegation\Adapters\SpatiePermissionAdapter;
use Ordain\Delegation\Repositories\SpatiePermissionRepository;
use Ordain\Delegation\Tests\Fixtures\User;
use Ordain\Delegation\Tests\TestCase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->repository = new SpatiePermissionRepository;
    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);
});

describe('SpatiePermissionRepository', function (): void {
    it('finds permission by id', function (): void {
        $permission = Permission::create(['name' => 'edit-posts', 'guard_name' => 'web']);

        $found = $this->repository->findById($permission->id);

        expect($found)->not->toBeNull();
        expect($found->getPermissionName())->toBe('edit-posts');
    });

    it('returns null when permission not found by id', function (): void {
        $found = $this->repository->findById(999);

        expect($found)->toBeNull();
    });

    it('finds permissions by ids', function (): void {
        $perm1 = Permission::create(['name' => 'create-posts', 'guard_name' => 'web']);
        $perm2 = Permission::create(['name' => 'edit-posts', 'guard_name' => 'web']);

        $found = $this->repository->findByIds([$perm1->id, $perm2->id]);

        expect($found)->toHaveCount(2);
    });

    it('returns empty collection for empty ids array', function (): void {
        $found = $this->repository->findByIds([]);

        expect($found)->toBeEmpty();
    });

    it('finds permission by name', function (): void {
        Permission::create(['name' => 'edit-posts', 'guard_name' => 'web']);

        $found = $this->repository->findByName('edit-posts');

        expect($found)->not->toBeNull();
        expect($found->getPermissionName())->toBe('edit-posts');
    });

    it('finds permission by name and guard', function (): void {
        Permission::create(['name' => 'edit-posts', 'guard_name' => 'web']);
        Permission::create(['name' => 'edit-posts', 'guard_name' => 'api']);

        $found = $this->repository->findByName('edit-posts', 'api');

        expect($found)->not->toBeNull();
        expect($found->getPermissionGuard())->toBe('api');
    });

    it('returns null when permission not found by name', function (): void {
        $found = $this->repository->findByName('nonexistent');

        expect($found)->toBeNull();
    });

    it('gets all permissions', function (): void {
        Permission::create(['name' => 'create-posts', 'guard_name' => 'web']);
        Permission::create(['name' => 'edit-posts', 'guard_name' => 'web']);

        $all = $this->repository->all();

        expect($all)->toHaveCount(2);
    });

    it('gets all permissions filtered by guard', function (): void {
        Permission::create(['name' => 'edit-posts', 'guard_name' => 'web']);
        Permission::create(['name' => 'api-edit', 'guard_name' => 'api']);

        $webPerms = $this->repository->all('web');

        expect($webPerms)->toHaveCount(1);
        expect($webPerms->first()->getPermissionName())->toBe('edit-posts');
    });

    it('gets user direct permissions', function (): void {
        $perm1 = Permission::create(['name' => 'create-posts', 'guard_name' => 'web']);
        $perm2 = Permission::create(['name' => 'edit-posts', 'guard_name' => 'web']);

        $this->user->permissions()->attach([$perm1->id, $perm2->id]);

        $userPerms = $this->repository->getUserPermissions($this->user);

        expect($userPerms)->toHaveCount(2);
    });

    it('gets all user permissions including via roles', function (): void {
        $permission = Permission::create(['name' => 'edit-posts', 'guard_name' => 'web']);
        $this->user->givePermissionTo($permission);

        $allPerms = $this->repository->getAllUserPermissions($this->user);

        expect($allPerms)->toHaveCount(1);
    });

    it('assigns permission to user', function (): void {
        $permission = Permission::create(['name' => 'edit-posts', 'guard_name' => 'web']);
        $permAdapter = SpatiePermissionAdapter::fromModel($permission);

        $this->repository->assignToUser($this->user, $permAdapter);

        expect($this->user->hasPermissionTo('edit-posts'))->toBeTrue();
    });

    it('removes permission from user', function (): void {
        $permission = Permission::create(['name' => 'edit-posts', 'guard_name' => 'web']);
        $permAdapter = SpatiePermissionAdapter::fromModel($permission);

        $this->user->givePermissionTo($permission);

        $this->repository->removeFromUser($this->user, $permAdapter);

        $this->user->refresh();
        expect($this->user->hasPermissionTo('edit-posts'))->toBeFalse();
    });

    it('checks if user has permission', function (): void {
        $permission = Permission::create(['name' => 'edit-posts', 'guard_name' => 'web']);
        $permAdapter = SpatiePermissionAdapter::fromModel($permission);

        expect($this->repository->userHasPermission($this->user, $permAdapter))->toBeFalse();

        $this->user->givePermissionTo($permission);

        expect($this->repository->userHasPermission($this->user, $permAdapter))->toBeTrue();
    });

    it('syncs user permissions', function (): void {
        $perm1 = Permission::create(['name' => 'create-posts', 'guard_name' => 'web']);
        $perm2 = Permission::create(['name' => 'edit-posts', 'guard_name' => 'web']);
        $perm3 = Permission::create(['name' => 'delete-posts', 'guard_name' => 'web']);

        $this->user->givePermissionTo($perm1);

        $this->repository->syncUserPermissions($this->user, [$perm2->id, $perm3->id]);

        $this->user->refresh();
        expect($this->user->hasPermissionTo('create-posts'))->toBeFalse();
        expect($this->user->hasPermissionTo('edit-posts'))->toBeTrue();
        expect($this->user->hasPermissionTo('delete-posts'))->toBeTrue();
    });
});
