<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Ordain\Delegation\Adapters\SpatiePermissionAdapter;
use Ordain\Delegation\Adapters\SpatieRoleAdapter;
use Ordain\Delegation\Repositories\EloquentDelegationRepository;
use Ordain\Delegation\Tests\Fixtures\User;
use Ordain\Delegation\Tests\TestCase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->repository = new EloquentDelegationRepository;
    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'can_manage_users' => true,
        'max_manageable_users' => 5,
    ]);
});

describe('EloquentDelegationRepository', function (): void {
    it('gets assignable roles', function (): void {
        $role1 = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $role2 = Role::create(['name' => 'editor', 'guard_name' => 'web']);

        $this->user->assignableRoles()->attach([$role1->id, $role2->id]);

        $roles = $this->repository->getAssignableRoles($this->user);

        expect($roles)->toHaveCount(2);
    });

    it('gets assignable permissions', function (): void {
        $perm1 = Permission::create(['name' => 'create-posts', 'guard_name' => 'web']);
        $perm2 = Permission::create(['name' => 'delete-posts', 'guard_name' => 'web']);

        $this->user->assignablePermissions()->attach([$perm1->id, $perm2->id]);

        $permissions = $this->repository->getAssignablePermissions($this->user);

        expect($permissions)->toHaveCount(2);
    });

    it('sets assignable roles', function (): void {
        $role1 = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $role2 = Role::create(['name' => 'editor', 'guard_name' => 'web']);

        $this->repository->setAssignableRoles($this->user, [$role1->id, $role2->id]);

        expect($this->user->assignableRoles()->count())->toBe(2);
    });

    it('sets assignable permissions', function (): void {
        $perm1 = Permission::create(['name' => 'create-posts', 'guard_name' => 'web']);
        $perm2 = Permission::create(['name' => 'delete-posts', 'guard_name' => 'web']);

        $this->repository->setAssignablePermissions($this->user, [$perm1->id, $perm2->id]);

        expect($this->user->assignablePermissions()->count())->toBe(2);
    });

    it('adds assignable role', function (): void {
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $roleAdapter = SpatieRoleAdapter::fromModel($role);

        $this->repository->addAssignableRole($this->user, $roleAdapter);

        expect($this->user->assignableRoles()->count())->toBe(1);
    });

    it('removes assignable role', function (): void {
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $roleAdapter = SpatieRoleAdapter::fromModel($role);

        $this->user->assignableRoles()->attach($role->id);

        $this->repository->removeAssignableRole($this->user, $roleAdapter);

        expect($this->user->assignableRoles()->count())->toBe(0);
    });

    it('adds assignable permission', function (): void {
        $permission = Permission::create(['name' => 'edit-posts', 'guard_name' => 'web']);
        $permAdapter = SpatiePermissionAdapter::fromModel($permission);

        $this->repository->addAssignablePermission($this->user, $permAdapter);

        expect($this->user->assignablePermissions()->count())->toBe(1);
    });

    it('removes assignable permission', function (): void {
        $permission = Permission::create(['name' => 'edit-posts', 'guard_name' => 'web']);
        $permAdapter = SpatiePermissionAdapter::fromModel($permission);

        $this->user->assignablePermissions()->attach($permission->id);

        $this->repository->removeAssignablePermission($this->user, $permAdapter);

        expect($this->user->assignablePermissions()->count())->toBe(0);
    });

    it('checks if user has assignable role', function (): void {
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $roleAdapter = SpatieRoleAdapter::fromModel($role);

        expect($this->repository->hasAssignableRole($this->user, $roleAdapter))->toBeFalse();

        $this->user->assignableRoles()->attach($role->id);

        expect($this->repository->hasAssignableRole($this->user, $roleAdapter))->toBeTrue();
    });

    it('checks if user has assignable permission', function (): void {
        $permission = Permission::create(['name' => 'edit-posts', 'guard_name' => 'web']);
        $permAdapter = SpatiePermissionAdapter::fromModel($permission);

        expect($this->repository->hasAssignablePermission($this->user, $permAdapter))->toBeFalse();

        $this->user->assignablePermissions()->attach($permission->id);

        expect($this->repository->hasAssignablePermission($this->user, $permAdapter))->toBeTrue();
    });

    it('gets created users count', function (): void {
        expect($this->repository->getCreatedUsersCount($this->user))->toBe(0);

        User::create([
            'name' => 'Child 1',
            'email' => 'child1@example.com',
            'created_by_user_id' => $this->user->id,
        ]);

        User::create([
            'name' => 'Child 2',
            'email' => 'child2@example.com',
            'created_by_user_id' => $this->user->id,
        ]);

        expect($this->repository->getCreatedUsersCount($this->user))->toBe(2);
    });

    it('updates delegation settings', function (): void {
        $this->repository->updateDelegationSettings($this->user, false, 10);

        $this->user->refresh();

        expect($this->user->can_manage_users)->toBeFalse();
        expect($this->user->max_manageable_users)->toBe(10);
    });

    it('syncs assignable roles', function (): void {
        $role1 = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $role2 = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $role3 = Role::create(['name' => 'viewer', 'guard_name' => 'web']);

        $this->user->assignableRoles()->attach($role1->id);

        $this->repository->syncAssignableRoles($this->user, [$role2->id, $role3->id]);

        $roleIds = $this->user->assignableRoles()->pluck('id')->toArray();

        expect($roleIds)->not->toContain($role1->id);
        expect($roleIds)->toContain($role2->id);
        expect($roleIds)->toContain($role3->id);
    });

    it('syncs assignable permissions', function (): void {
        $perm1 = Permission::create(['name' => 'create-posts', 'guard_name' => 'web']);
        $perm2 = Permission::create(['name' => 'edit-posts', 'guard_name' => 'web']);
        $perm3 = Permission::create(['name' => 'delete-posts', 'guard_name' => 'web']);

        $this->user->assignablePermissions()->attach($perm1->id);

        $this->repository->syncAssignablePermissions($this->user, [$perm2->id, $perm3->id]);

        $permIds = $this->user->assignablePermissions()->pluck('id')->toArray();

        expect($permIds)->not->toContain($perm1->id);
        expect($permIds)->toContain($perm2->id);
        expect($permIds)->toContain($perm3->id);
    });

    it('clears assignable roles', function (): void {
        $role1 = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $role2 = Role::create(['name' => 'editor', 'guard_name' => 'web']);

        $this->user->assignableRoles()->attach([$role1->id, $role2->id]);

        $this->repository->clearAssignableRoles($this->user);

        expect($this->user->assignableRoles()->count())->toBe(0);
    });

    it('clears assignable permissions', function (): void {
        $perm1 = Permission::create(['name' => 'create-posts', 'guard_name' => 'web']);
        $perm2 = Permission::create(['name' => 'edit-posts', 'guard_name' => 'web']);

        $this->user->assignablePermissions()->attach([$perm1->id, $perm2->id]);

        $this->repository->clearAssignablePermissions($this->user);

        expect($this->user->assignablePermissions()->count())->toBe(0);
    });
});
