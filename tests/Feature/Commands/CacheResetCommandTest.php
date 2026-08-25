<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Ordain\Delegation\Contracts\DelegationServiceInterface;
use Ordain\Delegation\Contracts\Repositories\UserRepositoryInterface;
use Ordain\Delegation\Tests\Fixtures\User;

uses(RefreshDatabase::class);

/**
 * Read a user's quota through the delegation service using a freshly loaded
 * model, so the only possible source of staleness is the delegation cache.
 *
 * Deliberately avoids naming cache keys: the command and the caching decorator
 * must agree on the key format, and a test that rebuilds that format by hand
 * would pass even when they disagree.
 */
function quotaThroughDelegationService(int|string $userId): ?int
{
    return app(DelegationServiceInterface::class)
        ->getDelegationScope(User::findOrFail($userId))
        ->maxManageableUsers;
}

function createCacheResetUser(string $email, int $quota = 5): User
{
    return User::create([
        'name' => 'Test User',
        'email' => $email,
        'can_manage_users' => true,
        'max_manageable_users' => $quota,
    ]);
}

describe('CacheResetCommand', function (): void {
    it('shows usage information when no arguments provided', function (): void {
        $this->artisan('delegation:cache-reset')
            ->assertSuccessful()
            ->expectsOutputToContain('Delegation Cache Reset')
            ->expectsOutputToContain('Usage:');
    });

    it('clears the cache entries the delegation service actually wrote', function (): void {
        $user = createCacheResetUser('test@example.com');

        expect(quotaThroughDelegationService($user->id))->toBe(5);

        DB::table('users')->where('id', $user->id)->update(['max_manageable_users' => 99]);

        expect(quotaThroughDelegationService($user->id))
            ->toBe(5, 'expected the delegation cache to still be warm');

        $this->artisan('delegation:cache-reset', ['user' => (string) $user->id])
            ->assertSuccessful()
            ->expectsOutputToContain("Cache cleared for user #{$user->id}");

        expect(quotaThroughDelegationService($user->id))
            ->toBe(99, 'expected the command to have cleared the cached scope');
    });

    it('fails when user not found', function (): void {
        $this->artisan('delegation:cache-reset', ['user' => '999'])
            ->assertFailed()
            ->expectsOutputToContain('User with ID 999 not found');
    });

    it('reports that there is nothing to clear when caching is disabled', function (): void {
        $user = createCacheResetUser('test@example.com');

        config()->set('permission-delegation.cache.enabled', false);
        $this->app->forgetScopedInstances();

        $this->artisan('delegation:cache-reset', ['user' => (string) $user->id])
            ->assertSuccessful()
            ->expectsOutputToContain('Delegation caching is disabled');
    });

    it('clears the cache for every user with --all', function (): void {
        $first = createCacheResetUser('user1@example.com', 5);
        $second = createCacheResetUser('user2@example.com', 7);

        expect(quotaThroughDelegationService($first->id))->toBe(5);
        expect(quotaThroughDelegationService($second->id))->toBe(7);

        DB::table('users')->update(['max_manageable_users' => 99]);

        expect(quotaThroughDelegationService($first->id))->toBe(5);
        expect(quotaThroughDelegationService($second->id))->toBe(7);

        $this->artisan('delegation:cache-reset', ['--all' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Clearing all delegation cache...');

        expect(quotaThroughDelegationService($first->id))
            ->toBe(99, 'expected --all to have cleared the first user\'s cached scope');
        expect(quotaThroughDelegationService($second->id))
            ->toBe(99, 'expected --all to have cleared the second user\'s cached scope');
    });

    it('reports that there is nothing to clear with --all when caching is disabled', function (): void {
        createCacheResetUser('user1@example.com');

        config()->set('permission-delegation.cache.enabled', false);
        $this->app->forgetScopedInstances();

        $this->artisan('delegation:cache-reset', ['--all' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Clearing all delegation cache...')
            ->expectsOutputToContain('Delegation caching is disabled');
    });

    it('skips user ids that no longer resolve to a user', function (): void {
        $repository = Mockery::mock(UserRepositoryInterface::class);
        $repository->shouldReceive('getAllIds')->andReturn(collect([12345]));
        $repository->shouldReceive('findById')->with(12345)->andReturn(null);

        $this->app->instance(UserRepositoryInterface::class, $repository);

        $this->artisan('delegation:cache-reset', ['--all' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Users processed: 0');
    });
});
