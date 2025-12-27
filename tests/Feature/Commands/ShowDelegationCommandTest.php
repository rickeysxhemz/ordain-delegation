<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Ordain\Delegation\Tests\Fixtures\User;
use Ordain\Delegation\Tests\TestCase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

describe('ShowDelegationCommand', function (): void {
    it('shows delegation scope for a user', function (): void {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'can_manage_users' => true,
            'max_manageable_users' => 10,
        ]);

        $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignableRoles()->attach($role->id);

        $this->artisan('delegation:show', ['user' => $user->id])
            ->assertSuccessful()
            ->expectsOutputToContain('Delegation Scope for User');
    });

    it('fails when user not found', function (): void {
        $this->artisan('delegation:show', ['user' => 999])
            ->assertFailed()
            ->expectsOutputToContain('User with ID 999 not found');
    });

    it('shows users with no roles or permissions', function (): void {
        $user = User::create([
            'name' => 'Empty User',
            'email' => 'empty@example.com',
            'can_manage_users' => false,
        ]);

        $this->artisan('delegation:show', ['user' => $user->id])
            ->assertSuccessful();
    });

    it('shows creator information', function (): void {
        $creator = User::create([
            'name' => 'Creator',
            'email' => 'creator@example.com',
            'can_manage_users' => true,
        ]);

        $child = User::create([
            'name' => 'Child',
            'email' => 'child@example.com',
            'created_by_user_id' => $creator->id,
        ]);

        $this->artisan('delegation:show', ['user' => $child->id])
            ->assertSuccessful()
            ->expectsOutputToContain('Created by');
    });

    it('shows no creator for top-level user', function (): void {
        $user = User::create([
            'name' => 'Top Level',
            'email' => 'top@example.com',
        ]);

        $this->artisan('delegation:show', ['user' => $user->id])
            ->assertSuccessful()
            ->expectsOutputToContain('No creator');
    });
});
