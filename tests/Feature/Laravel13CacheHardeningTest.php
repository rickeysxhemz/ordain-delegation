<?php

declare(strict_types=1);

namespace Ordain\Delegation\Tests\Feature;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Contracts\DelegationServiceInterface;
use Ordain\Delegation\Domain\ValueObjects\DelegationScope;
use Ordain\Delegation\Tests\Fixtures\User;
use Ordain\Delegation\Tests\TestCase;
use Spatie\Permission\Models\Role;

/**
 * Runs the package cache against Laravel 13's real
 * cache.serializable_classes enforcement instead of a simulation.
 *
 * The array store is configured to serialize values, so every cache read
 * passes through unserialize(['allowed_classes' => false]) inside the
 * framework itself. Skipped on Laravel 11/12, where the option does not
 * exist; CacheSerializationTest covers those versions via simulation.
 */
final class Laravel13CacheHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (version_compare(Application::VERSION, '13.0', '<')) {
            $this->markTestSkipped('cache.serializable_classes requires Laravel 13.');
        }

        parent::setUp();
    }

    public function test_enforcement_is_active_objects_do_not_survive_the_cache(): void
    {
        Cache::put('hardening-control', DelegationScope::none(), 60);

        $this->assertNotInstanceOf(DelegationScope::class, Cache::get('hardening-control'));
    }

    public function test_cached_reads_round_trip_under_real_enforcement(): void
    {
        $user = $this->createDelegator();
        $service = $this->app->make(DelegationServiceInterface::class);

        $missScope = $service->getDelegationScope($user);
        $missRoles = $service->getAssignableRoles($user);

        $hitScope = $service->getDelegationScope($user);
        $hitRoles = $service->getAssignableRoles($user);

        $this->assertTrue($hitScope->equals($missScope));
        $this->assertSame(
            $missRoles->map(fn ($role) => $role->getRoleIdentifier())->all(),
            $hitRoles->map(fn ($role) => $role->getRoleIdentifier())->all(),
        );
    }

    public function test_stale_object_payloads_are_refetched_without_errors(): void
    {
        $user = $this->createDelegator();
        $service = $this->app->make(DelegationServiceInterface::class);

        Cache::put($this->scopeCacheKey($user), DelegationScope::none(), 60);

        $scope = $service->getDelegationScope($user);

        $this->assertTrue($scope->canManageUsers);
        $this->assertIsArray(Cache::get($this->scopeCacheKey($user)));
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('cache.default', 'array');
        $app['config']->set('cache.stores.array.serialize', true);
        $app['config']->set('cache.serializable_classes', false);
    }

    private function createDelegator(): User
    {
        $user = User::create([
            'name' => 'Hardened User',
            'email' => 'hardened@example.com',
            'can_manage_users' => true,
            'max_manageable_users' => 5,
        ]);

        $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignableRoles()->attach([$role->id]);

        return $user;
    }

    private function scopeCacheKey(DelegatableUserInterface $user): string
    {
        $guard = (string) config('permission-delegation.guard', 'web');
        $prefix = (string) config('permission-delegation.cache.prefix', 'delegation_');

        return "{$prefix}{$guard}_scope_{$user->getDelegatableIdentifier()}";
    }
}
