<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Ordain\Delegation\Tests\Fixtures\User;
use Ordain\Delegation\Tests\TestCase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'can_manage_users' => true,
        'max_manageable_users' => 5,
    ]);
});

describe('HasDelegation trait', function (): void {
    it('returns delegatable identifier', function (): void {
        expect($this->user->getDelegatableIdentifier())->toBe($this->user->id);
    });

    it('returns canManageUsers correctly', function (): void {
        expect($this->user->canManageUsers())->toBeTrue();

        $nonManager = User::create([
            'name' => 'Non Manager',
            'email' => 'nonmanager@example.com',
            'can_manage_users' => false,
        ]);

        expect($nonManager->canManageUsers())->toBeFalse();
    });

    it('returns maxManageableUsers correctly', function (): void {
        expect($this->user->getMaxManageableUsers())->toBe(5);

        $unlimited = User::create([
            'name' => 'Unlimited',
            'email' => 'unlimited@example.com',
            'max_manageable_users' => null,
        ]);

        expect($unlimited->getMaxManageableUsers())->toBeNull();
    });

    it('sets and gets creator relationship', function (): void {
        $child = User::create([
            'name' => 'Child User',
            'email' => 'child@example.com',
            'created_by_user_id' => $this->user->id,
        ]);

        expect($child->creator->id)->toBe($this->user->id);
        expect($child->getCreator())->not->toBeNull();
        expect($child->getCreator()->getDelegatableIdentifier())->toBe($this->user->id);
    });

    it('returns createdUsers relationship', function (): void {
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

        expect($this->user->createdUsers()->count())->toBe(2);
    });

    it('checks hasReachedUserLimit correctly', function (): void {
        expect($this->user->hasReachedUserLimit())->toBeFalse();

        // Create max users
        for ($i = 1; $i <= 5; $i++) {
            User::create([
                'name' => "Child {$i}",
                'email' => "child{$i}@example.com",
                'created_by_user_id' => $this->user->id,
            ]);
        }

        expect($this->user->hasReachedUserLimit())->toBeTrue();
    });

    it('returns null for hasReachedUserLimit when unlimited', function (): void {
        $unlimited = User::create([
            'name' => 'Unlimited',
            'email' => 'unlimited@example.com',
            'max_manageable_users' => null,
        ]);

        expect($unlimited->hasReachedUserLimit())->toBeFalse();
    });

    it('returns remaining user quota', function (): void {
        User::create([
            'name' => 'Child 1',
            'email' => 'child1@example.com',
            'created_by_user_id' => $this->user->id,
        ]);

        expect($this->user->getRemainingUserQuota())->toBe(4);
    });

    it('returns null for remaining quota when unlimited', function (): void {
        $unlimited = User::create([
            'name' => 'Unlimited',
            'email' => 'unlimited@example.com',
            'max_manageable_users' => null,
        ]);

        expect($unlimited->getRemainingUserQuota())->toBeNull();
    });

    it('manages assignable roles', function (): void {
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);

        $this->user->assignableRoles()->attach($role->id);

        expect($this->user->canAssignRole($role->id))->toBeTrue();
        expect($this->user->canAssignRole(999))->toBeFalse();
    });

    it('manages assignable permissions', function (): void {
        $permission = Permission::create(['name' => 'edit-posts', 'guard_name' => 'web']);

        $this->user->assignablePermissions()->attach($permission->id);

        expect($this->user->canAssignPermission($permission->id))->toBeTrue();
        expect($this->user->canAssignPermission(999))->toBeFalse();
    });

    it('syncs assignable roles', function (): void {
        $role1 = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $role2 = Role::create(['name' => 'editor', 'guard_name' => 'web']);

        $this->user->syncAssignableRoles([$role1->id, $role2->id]);

        expect($this->user->assignableRoles()->count())->toBe(2);
    });

    it('syncs assignable permissions', function (): void {
        $perm1 = Permission::create(['name' => 'create-posts', 'guard_name' => 'web']);
        $perm2 = Permission::create(['name' => 'delete-posts', 'guard_name' => 'web']);

        $this->user->syncAssignablePermissions([$perm1->id, $perm2->id]);

        expect($this->user->assignablePermissions()->count())->toBe(2);
    });

    it('sets creator via setCreator method', function (): void {
        $child = User::create([
            'name' => 'Child',
            'email' => 'child@example.com',
        ]);

        $child->setCreator($this->user);

        expect($child->fresh()->created_by_user_id)->toBe($this->user->id);
    });

    it('enables user management', function (): void {
        $user = User::create([
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'can_manage_users' => false,
        ]);

        $user->enableUserManagement(10);

        $user->refresh();
        expect($user->can_manage_users)->toBeTrue();
        expect($user->max_manageable_users)->toBe(10);
    });

    it('disables user management', function (): void {
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $this->user->assignableRoles()->attach($role->id);

        $this->user->disableUserManagement();

        $this->user->refresh();
        expect($this->user->can_manage_users)->toBeFalse();
        expect($this->user->max_manageable_users)->toBeNull();
        expect($this->user->assignableRoles()->count())->toBe(0);
    });

    it('uses scopeCanManageUsers', function (): void {
        User::create([
            'name' => 'Manager 2',
            'email' => 'manager2@example.com',
            'can_manage_users' => true,
        ]);

        User::create([
            'name' => 'Non Manager',
            'email' => 'nonmanager@example.com',
            'can_manage_users' => false,
        ]);

        // Use query builder with the scope
        $managers = User::query()->where('can_manage_users', true)->get();

        expect($managers->count())->toBe(2);
    });

    it('uses scopeCreatedBy', function (): void {
        User::create([
            'name' => 'Child 1',
            'email' => 'child1@example.com',
            'created_by_user_id' => $this->user->id,
        ]);

        User::create([
            'name' => 'Orphan',
            'email' => 'orphan@example.com',
        ]);

        $children = User::createdBy($this->user)->get();

        expect($children->count())->toBe(1);
    });
});
