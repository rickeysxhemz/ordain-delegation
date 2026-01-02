# Advanced Usage

Advanced features including batch operations, validation, caching strategies, and more.

## Batch Operations

### Batch Role Assignment

Assign multiple roles in a single operation:

```php
use Ordain\Delegation\Facades\Delegation;

// Assign multiple roles to a user
Delegation::batchDelegateRoles($delegator, $target, [$role1, $role2, $role3]);
```

### Batch Role Revocation

```php
Delegation::batchRevokeRoles($delegator, $target, [$role1, $role2]);
```

### Sync Roles

Replace all of target's roles with a new set (within delegator's scope):

```php
// Target will have ONLY these roles after sync
Delegation::syncRoles($delegator, $target, [$editorRole, $moderatorRole]);
```

### Batch Permission Operations

```php
// Grant multiple permissions
Delegation::batchDelegatePermissions($delegator, $target, [$perm1, $perm2]);

// Revoke multiple permissions
Delegation::batchRevokePermissions($delegator, $target, [$perm1]);

// Sync permissions
Delegation::syncPermissions($delegator, $target, [$perm1, $perm2, $perm3]);
```

## Validation

### Pre-Validation

Validate multiple operations before executing:

```php
$errors = Delegation::validateDelegation(
    delegator: $manager,
    target: $user,
    roles: [1, 2, 3],        // Role IDs to assign
    permissions: [4, 5],     // Permission IDs to grant
);

if (empty($errors)) {
    // All operations are valid, proceed
    Delegation::batchDelegateRoles($manager, $user, $roles);
    Delegation::batchDelegatePermissions($manager, $user, $permissions);
} else {
    // Handle validation errors
    foreach ($errors as $error) {
        // $error contains details about what failed
    }
}
```

### Validation Result Structure

```php
$errors = [
    [
        'type' => 'role',
        'id' => 3,
        'reason' => 'Role not in delegator scope',
    ],
    [
        'type' => 'permission',
        'id' => 5,
        'reason' => 'Permission not found',
    ],
];
```

## Working with Delegation Scope

### Modifying Scope Immutably

```php
use Ordain\Delegation\Domain\ValueObjects\DelegationScope;

$scope = Delegation::getDelegationScope($user);

// Add roles to existing scope
$newScope = $scope->withRoles(array_merge(
    $scope->assignableRoleIds,
    [4, 5] // Add new role IDs
));

// Change quota
$newScope = $scope->withMaxUsers(20);

// Remove user management
$newScope = $scope->withoutUserManagement();

// Apply the new scope
Delegation::setDelegationScope($user, $newScope);
```

### Using the Builder for Complex Scopes

```php
use Ordain\Delegation\Domain\Builders\DelegationScopeBuilder;

$scope = DelegationScopeBuilder::create()
    ->allowUserManagement()
    ->maxUsers(10)
    ->withRoles(
        Role::whereIn('name', ['editor', 'moderator'])->pluck('id')->toArray()
    )
    ->withPermissions(
        Permission::where('name', 'like', 'posts.%')->pluck('id')->toArray()
    )
    ->build();

Delegation::setDelegationScope($manager, $scope);
```

### Building from Existing Scope

```php
$existingScope = Delegation::getDelegationScope($user);

$newScope = DelegationScopeBuilder::from($existingScope)
    ->maxUsers(50)
    ->withRoles([...$existingScope->assignableRoleIds, 6, 7])
    ->build();
```

## Caching Strategies

### Manual Cache Control

```php
// Clear cache for a specific user
Delegation::forgetCache($user);

// Clear all delegation caches (if using tags)
Cache::tags(['delegation'])->flush();
```

### Cache Warming

Pre-populate cache for frequently accessed users:

```php
// In a scheduled job or after deployment
User::where('can_manage_users', true)->each(function ($user) {
    // These calls will populate the cache
    Delegation::getDelegationScope($user);
    Delegation::getAssignableRoles($user);
    Delegation::getAssignablePermissions($user);
});
```

### Disabling Cache Temporarily

```php
// For one-off operations where fresh data is critical
config(['permission-delegation.cache.enabled' => false]);

$scope = Delegation::getDelegationScope($user); // Fresh from DB

config(['permission-delegation.cache.enabled' => true]);
```

## Transaction Management

### Atomic Operations

Delegation operations are automatically wrapped in transactions:

```php
// This is atomic - if audit logging fails, the role assignment is rolled back
Delegation::delegateRole($delegator, $target, $role);
```

### Custom Transactions

For complex multi-step operations:

```php
use Illuminate\Support\Facades\DB;

DB::transaction(function () use ($delegator, $target, $roles, $permissions) {
    // All operations in a single transaction
    Delegation::batchDelegateRoles($delegator, $target, $roles);
    Delegation::batchDelegatePermissions($delegator, $target, $permissions);

    // Update user record
    $target->update(['status' => 'active']);
});
```

### Pessimistic Locking

For quota-sensitive operations:

```php
use Ordain\Delegation\Contracts\QuotaManagerInterface;

$quotaManager = app(QuotaManagerInterface::class);

// Lock user record during quota check and user creation
$quotaManager->withLock($delegator, function () use ($delegator, $userData) {
    if (Delegation::hasReachedUserLimit($delegator)) {
        throw new QuotaExceededException();
    }

    return User::create([
        ...$userData,
        'created_by_user_id' => $delegator->id,
    ]);
});
```

## Using Specifications

The package uses the Specification pattern for complex authorization logic:

```php
use Ordain\Delegation\Domain\Specifications\CanAssignRolesSpecification;
use Ordain\Delegation\Domain\Specifications\CanManageUserSpecification;
use Ordain\Delegation\Domain\Specifications\DelegationContext;

// Create a context
$context = new DelegationContext(
    delegator: $manager,
    target: $user,
    role: $role,
);

// Use individual specifications
$canManage = new CanManageUserSpecification($rootAdminResolver);
$canAssign = new CanAssignRolesSpecification($delegationRepository);

// Combine specifications
$canDelegateRole = $canManage->and($canAssign);

if ($canDelegateRole->isSatisfiedBy($context)) {
    // Authorization granted
}
```

### Custom Specifications

Create your own specifications:

```php
use Ordain\Delegation\Domain\Specifications\AbstractSpecification;
use Ordain\Delegation\Domain\Specifications\DelegationContext;

class RoleNotDeprecatedSpecification extends AbstractSpecification
{
    public function isSatisfiedBy(DelegationContext $context): bool
    {
        $role = $context->role;

        return $role && ! $role->deprecated;
    }
}

// Use in combination
$spec = $canAssignSpec->and(new RoleNotDeprecatedSpecification());
```

## Event-Driven Workflows

### Queued Event Listeners

Handle delegation events asynchronously:

```php
// app/Listeners/NotifyRoleAssignment.php
use Ordain\Delegation\Events\RoleDelegated;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyRoleAssignment implements ShouldQueue
{
    public $queue = 'notifications';

    public function handle(RoleDelegated $event): void
    {
        $event->target->notify(new RoleAssignedNotification(
            $event->role,
            $event->delegator
        ));
    }
}
```

### Event Subscribers

Group related listeners:

```php
use Ordain\Delegation\Events\RoleDelegated;
use Ordain\Delegation\Events\RoleRevoked;
use Ordain\Delegation\Events\UnauthorizedDelegationAttempted;

class DelegationEventSubscriber
{
    public function handleRoleDelegated(RoleDelegated $event): void
    {
        // Log, notify, etc.
    }

    public function handleUnauthorizedAttempt(UnauthorizedDelegationAttempted $event): void
    {
        // Security alerting
        Log::channel('security')->warning('Unauthorized delegation attempt', [
            'delegator' => $event->delegator->id,
            'action' => $event->action,
            'reason' => $event->reason,
        ]);
    }

    public function subscribe($events): array
    {
        return [
            RoleDelegated::class => 'handleRoleDelegated',
            RoleRevoked::class => 'handleRoleRevoked',
            UnauthorizedDelegationAttempted::class => 'handleUnauthorizedAttempt',
        ];
    }
}
```

## Soft Deletes and User Management

When using soft deletes, consider how it affects delegation:

```php
// Include soft-deleted users in hierarchy check
$allCreatedUsers = $manager->createdUsers()->withTrashed()->get();

// Check quota including soft-deleted users
$totalCreated = $manager->createdUsers()->withTrashed()->count();
```

### Handling User Deletion

```php
// Before deleting a user, handle their created users
public function destroy(User $user): void
{
    DB::transaction(function () use ($user) {
        // Option 1: Reassign created users to delegator's creator
        $user->createdUsers()->update([
            'created_by_user_id' => $user->created_by_user_id,
        ]);

        // Option 2: Clear the creator reference
        // $user->createdUsers()->update(['created_by_user_id' => null]);

        $user->delete();
    });
}
```

## Multi-Tenancy Support

### Scoping by Tenant

```php
// In your User model
public function scopeForTenant($query, $tenantId)
{
    return $query->where('tenant_id', $tenantId);
}

// When querying assignable roles
$roles = Role::forTenant($tenant->id)
    ->whereIn('id', $scope->assignableRoleIds)
    ->get();
```

### Tenant-Aware Repositories

```php
class TenantAwareRoleRepository implements RoleRepositoryInterface
{
    public function __construct(
        private readonly TenantManager $tenantManager,
    ) {}

    public function all(?string $guard = null): Collection
    {
        return Role::query()
            ->where('tenant_id', $this->tenantManager->current()->id)
            ->when($guard, fn ($q) => $q->where('guard_name', $guard))
            ->get()
            ->map(fn ($role) => SpatieRoleAdapter::fromModel($role));
    }
}
```

## Performance Optimization

### Eager Loading

```php
// When loading users with delegation data
$users = User::with([
    'assignableRoles',
    'assignablePermissions',
    'creator',
    'createdUsers',
])->paginate(20);
```

### Lazy Collection for Large Datasets

```php
// Process large number of users efficiently
User::where('can_manage_users', true)
    ->cursor()
    ->each(function ($user) {
        // Process each user without loading all into memory
    });
```

### Query Optimization

```php
// Instead of checking each role individually
$roles = Role::whereIn('id', $roleIds)->get();
$assignableIds = $scope->assignableRoleIds;

$validRoles = $roles->filter(
    fn ($role) => in_array($role->id, $assignableIds)
);
```

## Next Steps

- [Middleware](middleware.md) - Protect routes
- [Events](events.md) - React to delegation actions
- [Customization](customization.md) - Extend the package
- [API Reference](api-reference.md) - Complete method reference