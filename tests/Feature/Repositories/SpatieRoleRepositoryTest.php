<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Ordain\Delegation\Adapters\SpatieRoleAdapter;
use Ordain\Delegation\Repositories\SpatieRoleRepository;
use Ordain\Delegation\Tests\Fixtures\User;
use Ordain\Delegation\Tests\TestCase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->repository = new SpatieRoleRepository;
    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);
});

describe('SpatieRoleRepository', function (): void {
    it('finds role by id', function (): void {
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);

        $found = $this->repository->findById($role->id);

        expect($found)->not->toBeNull();
        expect($found->getRoleName())->toBe('admin');
    });

    it('returns null when role not found by id', function (): void {
        $found = $this->repository->findById(999);

        expect($found)->toBeNull();
    });

    it('finds roles by ids', function (): void {
        $role1 = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $role2 = Role::create(['name' => 'editor', 'guard_name' => 'web']);

        $found = $this->repository->findByIds([$role1->id, $role2->id]);

        expect($found)->toHaveCount(2);
    });

    it('returns empty collection for empty ids array', function (): void {
        $found = $this->repository->findByIds([]);

        expect($found)->toBeEmpty();
    });

    it('finds role by name', function (): void {
        Role::create(['name' => 'admin', 'guard_name' => 'web']);

        $found = $this->repository->findByName('admin');

        expect($found)->not->toBeNull();
        expect($found->getRoleName())->toBe('admin');
    });

    it('finds role by name and guard', function (): void {
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        Role::create(['name' => 'admin', 'guard_name' => 'api']);

        $found = $this->repository->findByName('admin', 'api');

        expect($found)->not->toBeNull();
        expect($found->getRoleGuard())->toBe('api');
    });

    it('returns null when role not found by name', function (): void {
        $found = $this->repository->findByName('nonexistent');

        expect($found)->toBeNull();
    });

    it('gets all roles', function (): void {
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        Role::create(['name' => 'editor', 'guard_name' => 'web']);

        $all = $this->repository->all();

        expect($all)->toHaveCount(2);
    });

    it('gets all roles filtered by guard', function (): void {
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        Role::create(['name' => 'api-admin', 'guard_name' => 'api']);

        $webRoles = $this->repository->all('web');

        expect($webRoles)->toHaveCount(1);
        expect($webRoles->first()->getRoleName())->toBe('admin');
    });

    it('gets user roles', function (): void {
        $role1 = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $role2 = Role::create(['name' => 'editor', 'guard_name' => 'web']);

        $this->user->roles()->attach([$role1->id, $role2->id]);

        $userRoles = $this->repository->getUserRoles($this->user);

        expect($userRoles)->toHaveCount(2);
    });

    it('assigns role to user', function (): void {
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $roleAdapter = SpatieRoleAdapter::fromModel($role);

        $this->repository->assignToUser($this->user, $roleAdapter);

        expect($this->user->hasRole('admin'))->toBeTrue();
    });

    it('removes role from user', function (): void {
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $roleAdapter = SpatieRoleAdapter::fromModel($role);

        $this->user->assignRole($role);

        $this->repository->removeFromUser($this->user, $roleAdapter);

        $this->user->refresh();
        expect($this->user->hasRole('admin'))->toBeFalse();
    });

    it('checks if user has role', function (): void {
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $roleAdapter = SpatieRoleAdapter::fromModel($role);

        expect($this->repository->userHasRole($this->user, $roleAdapter))->toBeFalse();

        $this->user->assignRole($role);

        expect($this->repository->userHasRole($this->user, $roleAdapter))->toBeTrue();
    });

    it('syncs user roles', function (): void {
        $role1 = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $role2 = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $role3 = Role::create(['name' => 'viewer', 'guard_name' => 'web']);

        $this->user->assignRole($role1);

        $this->repository->syncUserRoles($this->user, [$role2->id, $role3->id]);

        $this->user->refresh();
        expect($this->user->hasRole('admin'))->toBeFalse();
        expect($this->user->hasRole('editor'))->toBeTrue();
        expect($this->user->hasRole('viewer'))->toBeTrue();
    });
});
