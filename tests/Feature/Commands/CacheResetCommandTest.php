<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Ordain\Delegation\Tests\Fixtures\User;

uses(RefreshDatabase::class);

describe('CacheResetCommand', function (): void {
    it('shows usage information when no arguments provided', function (): void {
        $this->artisan('delegation:cache-reset')
            ->assertSuccessful()
            ->expectsOutputToContain('Delegation Cache Reset')
            ->expectsOutputToContain('Usage:');
    });

    it('clears cache for specific user', function (): void {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Set some cache values
        $prefix = config('permission-delegation.cache.prefix', 'delegation_');
        Cache::put("{$prefix}scope_{$user->id}", 'test', 3600);
        Cache::put("{$prefix}assignable_roles_{$user->id}", 'test', 3600);

        $this->artisan('delegation:cache-reset', ['user' => (string) $user->id])
            ->assertSuccessful()
            ->expectsOutputToContain("Cache cleared for user #{$user->id}");
    });

    it('fails when user not found', function (): void {
        $this->artisan('delegation:cache-reset', ['user' => '999'])
            ->assertFailed()
            ->expectsOutputToContain('User with ID 999 not found');
    });

    it('clears all cache with --all flag', function (): void {
        User::create([
            'name' => 'User 1',
            'email' => 'user1@example.com',
        ]);

        User::create([
            'name' => 'User 2',
            'email' => 'user2@example.com',
        ]);

        $this->artisan('delegation:cache-reset', ['--all' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Clearing all delegation cache...');
    });
});
