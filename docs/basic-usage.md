# Basic Usage

Learn how to use the Permission Delegation package in your Laravel application.

## Accessing the Service

### Via Dependency Injection (Recommended)

```php
use Ordain\Delegation\Contracts\DelegationServiceInterface;

class UserController extends Controller
{
    public function __construct(
        private readonly DelegationServiceInterface $delegation,
    ) {}

    public function assignRole(Request $request, User $target): JsonResponse
    {
        $delegator = $request->user();
        $role = Role::findByName($request->role_name);

        if (! $this->delegation->canAssignRole($delegator, $role, $target)) {
            abort(403, 'You cannot assign this role.');
        }

        $this->delegation->delegateRole($delegator, $target, $role);

        return response()->json(['message' => 'Role assigned successfully.']);
    }
}
```

### Via Facade

```php
use Ordain\Delegation\Facades\Delegation;

if (Delegation::canAssignRole($delegator, $role, $target)) {
    Delegation::delegateRole($delegator, $target, $role);
}
```

### Via Helper

```php
$delegation = app('delegation');
$delegation->canAssignRole($delegator, $role, $target);
```

## Setting Up a User's Delegation Scope

Before a user can delegate, you must define their scope:

```php
use Ordain\Delegation\Domain\ValueObjects\DelegationScope;
use Ordain\Delegation\Facades\Delegation;

// Create a scope
$scope = new DelegationScope(
    canManageUsers: true,
    maxManageableUsers: 10,
    assignableRoleIds: [1, 2, 3],        // Role IDs
    assignablePermissionIds: [4, 5, 6],   // Permission IDs
);

// Apply to user
Delegation::setDelegationScope($manager, $scope);
```

### Using Factory Methods

```php
// No delegation rights
$scope = DelegationScope::none();

// Unlimited users, specific roles
$scope = DelegationScope::unlimited(roleIds: [1, 2, 3]);

// Limited quota
$scope = DelegationScope::limited(maxUsers: 5, roleIds: [1, 2]);
```

### Using the Builder

```php
$scope = DelegationScope::builder()
    ->allowUserManagement()
    ->maxUsers(10)
    ->withRoles([1, 2, 3])
    ->withPermissions([4, 5])
    ->build();
```

## Checking Authorization

### Can Assign Role?

```php
// Check if delegator can assign a specific role to target
$canAssign = Delegation::canAssignRole($delegator, $role, $target);

// The check verifies:
// 1. Delegator is root admin OR
// 2. Delegator has can_manage_users = true
// 3. Delegator created the target user
// 4. Role is in delegator's assignableRoles
```

### Can Assign Permission?

```php
$canGrant = Delegation::canAssignPermission($delegator, $permission, $target);
```

### Can Manage User?

```php
// Check if delegator can manage target (edit, delete, etc.)
$canManage = Delegation::canManageUser($delegator, $target);
```

### Can Create Users?

```php
// Check if user has delegation capabilities
$canCreate = Delegation::canCreateUsers($user);
```

### Has Reached User Limit?

```php
$atLimit = Delegation::hasReachedUserLimit($user);
$remaining = Delegation::getRemainingQuota($user);
$created = Delegation::getCreatedUsersCount($user);
```

## Delegation Operations

### Assign a Role

```php
// Assigns role to target, logs the action, dispatches event
Delegation::delegateRole($delegator, $target, $role);
```

### Revoke a Role

```php
Delegation::revokeRole($delegator, $target, $role);
```

### Grant a Permission

```php
Delegation::delegatePermission($delegator, $target, $permission);
```

### Revoke a Permission

```php
Delegation::revokePermission($delegator, $target, $permission);
```

## Querying Assignable Items

### Get Assignable Roles

```php
// Returns Collection of roles the user can assign
$roles = Delegation::getAssignableRoles($delegator);

foreach ($roles as $role) {
    echo $role->getRoleName();
}
```

### Get Assignable Permissions

```php
$permissions = Delegation::getAssignablePermissions($delegator);
```

### Get User's Delegation Scope

```php
$scope = Delegation::getDelegationScope($user);

$scope->canManageUsers;         // bool
$scope->maxManageableUsers;     // int|null
$scope->assignableRoleIds;      // array
$scope->assignablePermissionIds; // array
```

## Using the Trait Methods

The `HasDelegation` trait adds convenient methods to your User model:

```php
// Enable/disable user management
$user->enableUserManagement(maxUsers: 10);
$user->disableUserManagement();

// Check capabilities
$user->canManageUsers();        // bool
$user->getMaxManageableUsers(); // int|null

// Relationships
$user->creator;                 // User who created this user
$user->createdUsers;            // Users created by this user
$user->assignableRoles;         // Roles this user can assign
$user->assignablePermissions;   // Permissions this user can grant
```

## Creating Users with Hierarchy

When creating users that should be in the delegation hierarchy:

```php
public function store(Request $request): JsonResponse
{
    $creator = $request->user();

    // Check if creator can create users
    if (! Delegation::canCreateUsers($creator)) {
        abort(403, 'You cannot create users.');
    }

    // Check quota
    if (Delegation::hasReachedUserLimit($creator)) {
        abort(403, 'You have reached your user creation limit.');
    }

    // Create user with creator reference
    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => Hash::make($request->password),
        'created_by_user_id' => $creator->id,  // Important!
    ]);

    return response()->json($user, 201);
}
```

## Complete Example

Here's a complete controller example:

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Ordain\Delegation\Contracts\DelegationServiceInterface;
use Ordain\Delegation\Domain\ValueObjects\DelegationScope;
use Spatie\Permission\Models\Role;

class TeamController extends Controller
{
    public function __construct(
        private readonly DelegationServiceInterface $delegation,
    ) {}

    /**
     * Create a new team member.
     */
    public function createMember(Request $request): JsonResponse
    {
        $manager = $request->user();

        // Verify manager can create users
        if (! $this->delegation->canCreateUsers($manager)) {
            return response()->json(['error' => 'Cannot create users'], 403);
        }

        // Check quota
        if ($this->delegation->hasReachedUserLimit($manager)) {
            return response()->json([
                'error' => 'User limit reached',
                'remaining' => $this->delegation->getRemainingQuota($manager),
            ], 403);
        }

        // Create the user
        $member = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'created_by_user_id' => $manager->id,
        ]);

        return response()->json($member, 201);
    }

    /**
     * Assign a role to a team member.
     */
    public function assignRole(Request $request, User $member): JsonResponse
    {
        $manager = $request->user();
        $role = Role::findByName($request->role);

        // Verify authorization
        if (! $this->delegation->canAssignRole($manager, $role, $member)) {
            return response()->json(['error' => 'Cannot assign this role'], 403);
        }

        // Perform assignment
        $this->delegation->delegateRole($manager, $member, $role);

        return response()->json(['message' => 'Role assigned']);
    }

    /**
     * Get roles available for assignment.
     */
    public function availableRoles(Request $request): JsonResponse
    {
        $roles = $this->delegation->getAssignableRoles($request->user());

        return response()->json($roles->map(fn ($role) => [
            'id' => $role->getRoleIdentifier(),
            'name' => $role->getRoleName(),
        ]));
    }

    /**
     * Configure a manager's delegation scope.
     */
    public function configureManager(Request $request, User $manager): JsonResponse
    {
        $admin = $request->user();

        // Only admins can configure managers
        if (! $this->delegation->canManageUser($admin, $manager)) {
            return response()->json(['error' => 'Cannot manage this user'], 403);
        }

        $scope = DelegationScope::builder()
            ->allowUserManagement()
            ->maxUsers($request->max_users)
            ->withRoles($request->role_ids)
            ->withPermissions($request->permission_ids ?? [])
            ->build();

        $this->delegation->setDelegationScope($manager, $scope);

        return response()->json(['message' => 'Scope configured']);
    }
}
```

## Next Steps

- [Advanced Usage](advanced-usage.md) - Batch operations, validation, caching
- [Middleware](middleware.md) - Protect routes with middleware
- [Blade & Routes](blade-and-routes.md) - View helpers and route macros