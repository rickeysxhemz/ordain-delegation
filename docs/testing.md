# Testing

Guidelines for testing delegation functionality in your application.

## Testing Setup

### Database Configuration

Use SQLite in-memory for fast tests:

```php
// phpunit.xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

### Test Case Setup

```php
<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Ordain\Delegation\Providers\DelegationServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            PermissionServiceProvider::class,
            DelegationServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('permission-delegation.models.user', \App\Models\User::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
```

## Testing Authorization

### Basic Authorization Tests

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Ordain\Delegation\Facades\Delegation;
use Ordain\Delegation\Domain\ValueObjects\DelegationScope;
use Spatie\Permission\Models\Role;

class DelegationAuthorizationTest extends TestCase
{
    public function test_manager_can_assign_role_in_scope(): void
    {
        $manager = User::factory()->create(['can_manage_users' => true]);
        $employee = User::factory()->create(['created_by_user_id' => $manager->id]);
        $role = Role::create(['name' => 'editor']);

        // Configure manager's scope
        Delegation::setDelegationScope($manager, new DelegationScope(
            canManageUsers: true,
            assignableRoleIds: [$role->id],
        ));

        $this->assertTrue(
            Delegation::canAssignRole($manager, $role, $employee)
        );
    }

    public function test_manager_cannot_assign_role_outside_scope(): void
    {
        $manager = User::factory()->create(['can_manage_users' => true]);
        $employee = User::factory()->create(['created_by_user_id' => $manager->id]);
        $adminRole = Role::create(['name' => 'admin']);

        // Manager has no assignable roles
        Delegation::setDelegationScope($manager, DelegationScope::none());

        $this->assertFalse(
            Delegation::canAssignRole($manager, $adminRole, $employee)
        );
    }

    public function test_manager_cannot_manage_users_created_by_others(): void
    {
        $manager1 = User::factory()->create(['can_manage_users' => true]);
        $manager2 = User::factory()->create(['can_manage_users' => true]);
        $employee = User::factory()->create(['created_by_user_id' => $manager2->id]);

        $this->assertFalse(
            Delegation::canManageUser($manager1, $employee)
        );
    }

    public function test_root_admin_bypasses_all_checks(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('root-admin');

        $anyUser = User::factory()->create();
        $anyRole = Role::create(['name' => 'super-power']);

        $this->assertTrue(
            Delegation::canAssignRole($admin, $anyRole, $anyUser)
        );
    }
}
```

### Quota Tests

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Ordain\Delegation\Facades\Delegation;
use Ordain\Delegation\Domain\ValueObjects\DelegationScope;

class DelegationQuotaTest extends TestCase
{
    public function test_user_quota_is_enforced(): void
    {
        $manager = User::factory()->create(['can_manage_users' => true]);

        Delegation::setDelegationScope($manager, new DelegationScope(
            canManageUsers: true,
            maxManageableUsers: 2,
        ));

        // Create users up to quota
        User::factory()->count(2)->create(['created_by_user_id' => $manager->id]);

        $this->assertTrue(Delegation::hasReachedUserLimit($manager));
        $this->assertEquals(0, Delegation::getRemainingQuota($manager));
    }

    public function test_unlimited_quota_returns_null(): void
    {
        $manager = User::factory()->create(['can_manage_users' => true]);

        Delegation::setDelegationScope($manager, DelegationScope::unlimited());

        $this->assertNull(Delegation::getRemainingQuota($manager));
        $this->assertFalse(Delegation::hasReachedUserLimit($manager));
    }

    public function test_created_users_count_is_accurate(): void
    {
        $manager = User::factory()->create(['can_manage_users' => true]);
        User::factory()->count(5)->create(['created_by_user_id' => $manager->id]);

        $this->assertEquals(5, Delegation::getCreatedUsersCount($manager));
    }
}
```

## Testing Delegation Operations

### Role Delegation Tests

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Ordain\Delegation\Facades\Delegation;
use Ordain\Delegation\Domain\ValueObjects\DelegationScope;
use Ordain\Delegation\Exceptions\UnauthorizedDelegationException;
use Spatie\Permission\Models\Role;

class RoleDelegationTest extends TestCase
{
    public function test_role_is_assigned_successfully(): void
    {
        $manager = $this->createManager();
        $employee = User::factory()->create(['created_by_user_id' => $manager->id]);
        $role = Role::create(['name' => 'editor']);

        Delegation::setDelegationScope($manager, new DelegationScope(
            canManageUsers: true,
            assignableRoleIds: [$role->id],
        ));

        Delegation::delegateRole($manager, $employee, $role);

        $this->assertTrue($employee->hasRole('editor'));
    }

    public function test_unauthorized_role_assignment_throws_exception(): void
    {
        $manager = User::factory()->create(['can_manage_users' => true]);
        $employee = User::factory()->create(['created_by_user_id' => $manager->id]);
        $role = Role::create(['name' => 'admin']);

        Delegation::setDelegationScope($manager, DelegationScope::none());

        $this->expectException(UnauthorizedDelegationException::class);

        Delegation::delegateRole($manager, $employee, $role);
    }

    public function test_role_revocation_works(): void
    {
        $manager = $this->createManager();
        $employee = User::factory()->create(['created_by_user_id' => $manager->id]);
        $role = Role::create(['name' => 'editor']);

        Delegation::setDelegationScope($manager, new DelegationScope(
            canManageUsers: true,
            assignableRoleIds: [$role->id],
        ));

        // Assign then revoke
        Delegation::delegateRole($manager, $employee, $role);
        Delegation::revokeRole($manager, $employee, $role);

        $this->assertFalse($employee->hasRole('editor'));
    }

    private function createManager(): User
    {
        return User::factory()->create(['can_manage_users' => true]);
    }
}
```

## Testing Events

### Event Dispatch Tests

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Event;
use Ordain\Delegation\Events\RoleDelegated;
use Ordain\Delegation\Events\RoleRevoked;
use Ordain\Delegation\Events\UnauthorizedDelegationAttempted;
use Ordain\Delegation\Facades\Delegation;
use Ordain\Delegation\Domain\ValueObjects\DelegationScope;
use Spatie\Permission\Models\Role;

class DelegationEventsTest extends TestCase
{
    public function test_role_delegated_event_is_dispatched(): void
    {
        Event::fake([RoleDelegated::class]);

        $manager = $this->createAuthorizedManager();
        $employee = User::factory()->create(['created_by_user_id' => $manager->id]);
        $role = Role::create(['name' => 'editor']);

        Delegation::delegateRole($manager, $employee, $role);

        Event::assertDispatched(RoleDelegated::class, function ($event) use ($employee, $role) {
            return $event->target->id === $employee->id
                && $event->role->getRoleName() === $role->name;
        });
    }

    public function test_unauthorized_attempt_event_is_dispatched(): void
    {
        Event::fake([UnauthorizedDelegationAttempted::class]);

        $user = User::factory()->create(['can_manage_users' => false]);
        $target = User::factory()->create();
        $role = Role::create(['name' => 'admin']);

        // Attempt unauthorized action
        Delegation::canAssignRole($user, $role, $target);

        Event::assertDispatched(UnauthorizedDelegationAttempted::class);
    }
}
```

## Testing Middleware

### Middleware Tests

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Ordain\Delegation\Domain\ValueObjects\DelegationScope;
use Ordain\Delegation\Facades\Delegation;
use Spatie\Permission\Models\Role;

class DelegationMiddlewareTest extends TestCase
{
    public function test_can_delegate_middleware_allows_authorized_users(): void
    {
        $manager = User::factory()->create(['can_manage_users' => true]);

        $response = $this->actingAs($manager)
            ->get('/team');

        $response->assertOk();
    }

    public function test_can_delegate_middleware_blocks_unauthorized_users(): void
    {
        $user = User::factory()->create(['can_manage_users' => false]);

        $response = $this->actingAs($user)
            ->get('/team');

        $response->assertForbidden();
    }

    public function test_can_assign_role_middleware_checks_scope(): void
    {
        $manager = User::factory()->create(['can_manage_users' => true]);
        $employee = User::factory()->create(['created_by_user_id' => $manager->id]);
        $role = Role::create(['name' => 'editor']);

        Delegation::setDelegationScope($manager, new DelegationScope(
            canManageUsers: true,
            assignableRoleIds: [$role->id],
        ));

        $response = $this->actingAs($manager)
            ->post("/users/{$employee->id}/roles", ['role' => 'editor']);

        $response->assertOk();
    }

    public function test_can_manage_user_middleware_validates_hierarchy(): void
    {
        $manager1 = User::factory()->create(['can_manage_users' => true]);
        $manager2 = User::factory()->create(['can_manage_users' => true]);
        $employee = User::factory()->create(['created_by_user_id' => $manager2->id]);

        // Manager1 tries to access employee created by Manager2
        $response = $this->actingAs($manager1)
            ->get("/users/{$employee->id}/edit");

        $response->assertForbidden();
    }

    protected function defineRoutes($router): void
    {
        $router->middleware(['auth', 'can.delegate'])
            ->get('/team', fn () => response('OK'));

        $router->middleware(['auth', 'can.assign.role:editor'])
            ->post('/users/{user}/roles', fn () => response('OK'));

        $router->middleware(['auth', 'can.manage.user'])
            ->get('/users/{user}/edit', fn () => response('OK'));
    }
}
```

## Testing Blade Directives

### Blade Directive Tests

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Ordain\Delegation\Domain\ValueObjects\DelegationScope;
use Ordain\Delegation\Facades\Delegation;
use Spatie\Permission\Models\Role;

class BladeDirectivesTest extends TestCase
{
    public function test_can_delegate_directive_shows_content_for_managers(): void
    {
        $manager = User::factory()->create(['can_manage_users' => true]);

        $this->actingAs($manager);

        $output = Blade::render('@canDelegate Show @endCanDelegate');

        $this->assertStringContainsString('Show', $output);
    }

    public function test_can_delegate_directive_hides_content_for_non_managers(): void
    {
        $user = User::factory()->create(['can_manage_users' => false]);

        $this->actingAs($user);

        $output = Blade::render('@canDelegate Show @endCanDelegate');

        $this->assertStringNotContainsString('Show', $output);
    }

    public function test_can_assign_role_directive_checks_scope(): void
    {
        $manager = User::factory()->create(['can_manage_users' => true]);
        $role = Role::create(['name' => 'editor']);

        Delegation::setDelegationScope($manager, new DelegationScope(
            canManageUsers: true,
            assignableRoleIds: [$role->id],
        ));

        $this->actingAs($manager);

        $output = Blade::render("@canAssignRole('editor') Show @endCanAssignRole");

        $this->assertStringContainsString('Show', $output);
    }
}
```

## Testing with Factories

### User Factory

```php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'can_manage_users' => false,
            'max_manageable_users' => null,
            'created_by_user_id' => null,
        ];
    }

    public function manager(int $quota = null): static
    {
        return $this->state(fn () => [
            'can_manage_users' => true,
            'max_manageable_users' => $quota,
        ]);
    }

    public function createdBy(User $creator): static
    {
        return $this->state(fn () => [
            'created_by_user_id' => $creator->id,
        ]);
    }

    public function rootAdmin(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->assignRole('root-admin');
        });
    }
}
```

### Usage in Tests

```php
// Create a manager with quota
$manager = User::factory()->manager(quota: 10)->create();

// Create employee under manager
$employee = User::factory()->createdBy($manager)->create();

// Create root admin
$admin = User::factory()->rootAdmin()->create();
```

## Mocking the Delegation Service

### Mocking for Unit Tests

```php
<?php

namespace Tests\Unit;

use App\Services\TeamService;
use Mockery;
use Ordain\Delegation\Contracts\DelegationServiceInterface;
use Tests\TestCase;

class TeamServiceTest extends TestCase
{
    public function test_team_service_checks_delegation(): void
    {
        $delegationService = Mockery::mock(DelegationServiceInterface::class);
        $delegationService->shouldReceive('canCreateUsers')
            ->once()
            ->andReturn(true);

        $teamService = new TeamService($delegationService);

        $result = $teamService->canAddMember($this->user);

        $this->assertTrue($result);
    }
}
```

## Database Assertions

```php
public function test_audit_log_is_created(): void
{
    $manager = $this->createAuthorizedManager();
    $employee = User::factory()->create(['created_by_user_id' => $manager->id]);
    $role = Role::create(['name' => 'editor']);

    Delegation::delegateRole($manager, $employee, $role);

    $this->assertDatabaseHas('delegation_audit_logs', [
        'action' => 'role_assigned',
        'performed_by_id' => $manager->id,
        'target_user_id' => $employee->id,
    ]);
}
```

## Next Steps

- [Troubleshooting](troubleshooting.md) - Common issues and solutions
- [API Reference](api-reference.md) - Complete method reference