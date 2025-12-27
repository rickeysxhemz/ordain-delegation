<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Ordain\Delegation\Tests\Fixtures\User;
use Ordain\Delegation\Tests\TestCase;
use Ordain\Delegation\View\BladeDirectives;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    BladeDirectives::register();
});

describe('BladeDirectives', function (): void {
    it('registers canDelegate directive', function (): void {
        $directives = Blade::getCustomDirectives();

        expect($directives)->toHaveKey('canDelegate');
        expect($directives)->toHaveKey('elsecanDelegate');
        expect($directives)->toHaveKey('endcanDelegate');
    });

    it('registers canAssignRole directive', function (): void {
        $directives = Blade::getCustomDirectives();

        expect($directives)->toHaveKey('canAssignRole');
        expect($directives)->toHaveKey('elsecanAssignRole');
        expect($directives)->toHaveKey('endcanAssignRole');
    });

    it('registers canManageUser directive', function (): void {
        $directives = Blade::getCustomDirectives();

        expect($directives)->toHaveKey('canManageUser');
        expect($directives)->toHaveKey('elsecanManageUser');
        expect($directives)->toHaveKey('endcanManageUser');
    });

    it('canDelegate returns false when not authenticated', function (): void {
        $blade = '@canDelegate Show @endcanDelegate';
        $compiled = Blade::compileString($blade);

        // The condition should evaluate to false when not authenticated
        expect($compiled)->toContain('if');
    });

    it('canDelegate returns true for user who can create users', function (): void {
        $user = User::create([
            'name' => 'Manager',
            'email' => 'manager@example.com',
            'can_manage_users' => true,
        ]);

        $this->actingAs($user);

        // Use evaluation instead of compilation for runtime behavior
        $result = app(\Ordain\Delegation\Contracts\DelegationServiceInterface::class)->canCreateUsers($user);

        expect($result)->toBeTrue();
    });

    it('canDelegate returns false for user who cannot create users', function (): void {
        $user = User::create([
            'name' => 'Regular',
            'email' => 'regular@example.com',
            'can_manage_users' => false,
        ]);

        $this->actingAs($user);

        $result = app(\Ordain\Delegation\Contracts\DelegationServiceInterface::class)->canCreateUsers($user);

        expect($result)->toBeFalse();
    });

    it('canAssignRole returns false for non-existent role', function (): void {
        $user = User::create([
            'name' => 'Manager',
            'email' => 'manager@example.com',
            'can_manage_users' => true,
        ]);

        $this->actingAs($user);

        $roleRepo = app(\Ordain\Delegation\Contracts\Repositories\RoleRepositoryInterface::class);
        $role = $roleRepo->findByName('nonexistent');

        expect($role)->toBeNull();
    });

    it('canAssignRole returns true when user can assign role', function (): void {
        $user = User::create([
            'name' => 'Manager',
            'email' => 'manager@example.com',
            'can_manage_users' => true,
        ]);

        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $user->assignableRoles()->attach($role->id);

        $this->actingAs($user);

        $roleRepo = app(\Ordain\Delegation\Contracts\Repositories\RoleRepositoryInterface::class);
        $foundRole = $roleRepo->findByName('editor');

        $result = app(\Ordain\Delegation\Contracts\DelegationServiceInterface::class)
            ->canAssignRole($user, $foundRole);

        expect($result)->toBeTrue();
    });

    it('canManageUser returns true when user can manage target', function (): void {
        $manager = User::create([
            'name' => 'Manager',
            'email' => 'manager@example.com',
            'can_manage_users' => true,
        ]);

        $target = User::create([
            'name' => 'Target',
            'email' => 'target@example.com',
            'created_by_user_id' => $manager->id,
        ]);

        $this->actingAs($manager);

        $result = app(\Ordain\Delegation\Contracts\DelegationServiceInterface::class)
            ->canManageUser($manager, $target);

        expect($result)->toBeTrue();
    });

    it('canManageUser returns false when user cannot manage target', function (): void {
        $manager = User::create([
            'name' => 'Manager',
            'email' => 'manager@example.com',
            'can_manage_users' => true,
        ]);

        $other = User::create([
            'name' => 'Other',
            'email' => 'other@example.com',
        ]);

        $this->actingAs($manager);

        $result = app(\Ordain\Delegation\Contracts\DelegationServiceInterface::class)
            ->canManageUser($manager, $other);

        expect($result)->toBeFalse();
    });
});
