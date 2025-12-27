<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Ordain\Delegation\Tests\Fixtures\User;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->admin = User::create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'can_manage_users' => true,
    ]);

    $this->target = User::create([
        'name' => 'Target',
        'email' => 'target@example.com',
        'created_by_user_id' => $this->admin->id,
    ]);

    $this->role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
    $this->admin->assignableRoles()->attach($this->role->id);
});

describe('AssignRoleCommand', function (): void {
    it('assigns role successfully with force flag', function (): void {
        $this->artisan('delegation:assign', [
            'delegator' => $this->admin->id,
            'target' => $this->target->id,
            'role' => 'editor',
            '--force' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('assigned');
    });

    it('fails when delegator not found', function (): void {
        $this->artisan('delegation:assign', [
            'delegator' => 999,
            'target' => $this->target->id,
            'role' => 'editor',
            '--force' => true,
        ])
            ->assertFailed()
            ->expectsOutputToContain('Delegator user with ID 999 not found');
    });

    it('fails when target not found', function (): void {
        $this->artisan('delegation:assign', [
            'delegator' => $this->admin->id,
            'target' => 999,
            'role' => 'editor',
            '--force' => true,
        ])
            ->assertFailed()
            ->expectsOutputToContain('Target user with ID 999 not found');
    });

    it('fails when role not found by name', function (): void {
        $this->artisan('delegation:assign', [
            'delegator' => $this->admin->id,
            'target' => $this->target->id,
            'role' => 'nonexistent',
            '--force' => true,
        ])
            ->assertFailed()
            ->expectsOutputToContain("Role with name 'nonexistent' not found");
    });

    it('finds role by id with by-id flag', function (): void {
        $this->artisan('delegation:assign', [
            'delegator' => $this->admin->id,
            'target' => $this->target->id,
            'role' => $this->role->id,
            '--by-id' => true,
            '--force' => true,
        ])
            ->assertSuccessful();
    });

    it('fails when role not found by id', function (): void {
        $this->artisan('delegation:assign', [
            'delegator' => $this->admin->id,
            'target' => $this->target->id,
            'role' => 999,
            '--by-id' => true,
            '--force' => true,
        ])
            ->assertFailed()
            ->expectsOutputToContain('Role with ID 999 not found');
    });

    it('cancels when user declines confirmation', function (): void {
        $this->artisan('delegation:assign', [
            'delegator' => $this->admin->id,
            'target' => $this->target->id,
            'role' => 'editor',
        ])
            ->expectsConfirmation(
                "Assign role 'editor' to user #{$this->target->id} as delegated by user #{$this->admin->id}?",
                'no',
            )
            ->assertSuccessful()
            ->expectsOutputToContain('Operation cancelled');
    });

    it('proceeds when user confirms', function (): void {
        $this->artisan('delegation:assign', [
            'delegator' => $this->admin->id,
            'target' => $this->target->id,
            'role' => 'editor',
        ])
            ->expectsConfirmation(
                "Assign role 'editor' to user #{$this->target->id} as delegated by user #{$this->admin->id}?",
                'yes',
            )
            ->assertSuccessful();
    });
});
