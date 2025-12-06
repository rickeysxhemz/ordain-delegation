# Permission Delegation Package

A decoupled, framework-agnostic permission delegation system for Laravel 11/12 applications. This package enables hierarchical user management where administrators can delegate specific roles and permissions to other users within defined boundaries.

## Features

- **User Creation Limits**: Define how many users each manager can create
- **Role Delegation**: Control which roles a user can assign to others
- **Permission Delegation**: Control which permissions a user can grant to others
- **Hierarchical Management**: Users can only manage users they created
- **Super Admin Bypass**: Configurable super admin role that bypasses all restrictions
- **Audit Logging**: Multiple logging backends (database, log, null)
- **Caching**: Built-in caching for delegation checks
- **Framework Agnostic**: Works with any role/permission system via contracts

## Requirements

- PHP 8.2+
- Laravel 11.x or 12.x
- spatie/laravel-permission ^6.0

## Installation

```bash
composer require ordain/delegation
```

The service provider will be auto-discovered. If not, register it manually in `bootstrap/providers.php`:

```php
return [
    // ...
    Ordain\Delegation\Providers\DelegationServiceProvider::class,
];
```

### Publish the configuration:

```bash
php artisan vendor:publish --tag=permission-delegation-config
```

### Publish and run migrations:

```bash
php artisan vendor:publish --tag=permission-delegation-migrations
php artisan migrate
```

## Configuration

After publishing, edit `config/permission-delegation.php`:

```php
return [
    // User model class
    'user_model' => App\Models\User::class,

    // Role model (defaults to Spatie)
    'role_model' => Spatie\Permission\Models\Role::class,

    // Permission model (defaults to Spatie)
    'permission_model' => Spatie\Permission\Models\Permission::class,

    // Table names
    'tables' => [
        'user_assignable_roles' => 'user_assignable_roles',
        'user_assignable_permissions' => 'user_assignable_permissions',
        'delegation_audit_logs' => 'delegation_audit_logs',
    ],

    // Super admin configuration
    'super_admin' => [
        'enabled' => true,
        'role' => 'super-admin',
    ],

    // Audit logging
    'audit' => [
        'enabled' => true,
        'driver' => 'database', // 'database', 'log', 'null', or custom class
        'log_channel' => 'stack',
    ],

    // Caching
    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
        'prefix' => 'delegation_',
    ],

    // Validation rules
    'validation' => [
        'require_own_access' => false,
        'prevent_privilege_escalation' => true,
    ],
];
```

## User Model Setup

Add the `HasDelegation` trait to your User model:

```php
<?php

namespace App\Models;

use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Traits\HasDelegation;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements DelegatableUserInterface
{
    use HasDelegation;

    protected $fillable = [
        'name',
        'email',
        'password',
        'can_manage_users',
        'max_manageable_users',
        'created_by_user_id',
    ];

    protected $casts = [
        'can_manage_users' => 'boolean',
        'max_manageable_users' => 'integer',
    ];
}
```

## Usage

### Basic Usage

```php
use Ordain\Delegation\Contracts\DelegationServiceInterface;

class UserController extends Controller
{
    public function __construct(
        private readonly DelegationServiceInterface $delegation
    ) {}

    public function assignRole(Request $request, User $target)
    {
        $delegator = $request->user();
        $role = Role::find($request->role_id);

        // Check if delegation is allowed
        if (!$this->delegation->canAssignRole($delegator, $role, $target)) {
            abort(403, 'You cannot assign this role.');
        }

        // Assign the role with audit logging
        $this->delegation->delegateRole($delegator, $target, $role);

        return response()->json(['message' => 'Role assigned successfully.']);
    }
}
```

### Using the Facade

```php
use Ordain\Delegation\Facades\Delegation;

// Check if user can assign a role
if (Delegation::canAssignRole($delegator, $role, $target)) {
    Delegation::delegateRole($delegator, $target, $role);
}

// Get all assignable roles for a user
$roles = Delegation::getAssignableRoles($delegator);
```

### Setting Delegation Scope

```php
use Ordain\Delegation\Domain\ValueObjects\DelegationScope;

// Create a scope with specific limits
$scope = new DelegationScope(
    canManageUsers: true,
    maxManageableUsers: 10,
    assignableRoleIds: [1, 2, 3],      // IDs of roles this user can assign
    assignablePermissionIds: [4, 5, 6]  // IDs of permissions this user can grant
);

// Apply the scope to a user
$delegation->setDelegationScope($user, $scope);

// Or use factory methods
$scope = DelegationScope::none();           // No delegation abilities
$scope = DelegationScope::unlimited([1, 2]); // Unlimited users, specific roles
```

### Using the Trait Methods

```php
// Enable user management
$user->enableUserManagement(maxUsers: 10);

// Disable user management
$user->disableUserManagement();

// Check abilities
$user->canManageUsers();           // bool
$user->getMaxManageableUsers();    // int|null
$user->canAssignRole($role);       // bool
$user->canAssignPermission($perm); // bool

// Get created users
$createdUsers = $user->createdUsers;

// Get assignable roles/permissions
$roles = $user->assignableRoles;
$permissions = $user->assignablePermissions;
```

### Checking Permissions

```php
// Check if user can assign a specific role
$canAssign = $delegation->canAssignRole($delegator, $role, $target);

// Check if user can create new users
$canCreate = $delegation->canCreateUsers($delegator);

// Check if user has reached their limit
$limitReached = $delegation->hasReachedUserLimit($delegator);

// Get remaining quota
$remaining = $delegation->getRemainingUserQuota($delegator); // null = unlimited

// Get all assignable roles for a user
$roles = $delegation->getAssignableRoles($delegator);

// Validate a full delegation operation
$errors = $delegation->validateDelegation(
    $delegator,
    $target,
    roles: [1, 2],
    permissions: [3, 4]
);

if (empty($errors)) {
    // Proceed with delegation
}
```

### Delegation Operations

```php
// All operations include validation and audit logging

// Assign a role
$delegation->delegateRole($delegator, $target, $role);

// Grant a permission
$delegation->delegatePermission($delegator, $target, $permission);

// Revoke a role
$delegation->revokeRole($delegator, $target, $role);

// Revoke a permission
$delegation->revokePermission($delegator, $target, $permission);
```

### Middleware

The package provides middleware for route protection:

```php
// In routes
Route::middleware('can-delegate')->group(function () {
    // Routes requiring delegation permission
});

Route::middleware('can-assign-role:editor')->group(function () {
    // Routes requiring ability to assign 'editor' role
});

Route::middleware('can-manage-user')->group(function () {
    // Routes requiring user management permission
});
```

Register middleware in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'can-delegate' => \Ordain\Delegation\Http\Middleware\CanDelegateMiddleware::class,
        'can-assign-role' => \Ordain\Delegation\Http\Middleware\CanAssignRoleMiddleware::class,
        'can-manage-user' => \Ordain\Delegation\Http\Middleware\CanManageUserMiddleware::class,
    ]);
})
```

### Exception Handling

```php
use Ordain\Delegation\Exceptions\UnauthorizedDelegationException;

try {
    $delegation->delegateRole($delegator, $target, $role);
} catch (UnauthorizedDelegationException $e) {
    // Handle unauthorized attempt
    $message = $e->getMessage();
    $action = $e->getAttemptedAction();
    $context = $e->getContext();
}
```

## Custom Implementations

### Custom Role Repository

Implement `RoleRepositoryInterface` to integrate with your role system:

```php
use Ordain\Delegation\Contracts\Repositories\RoleRepositoryInterface;

class CustomRoleRepository implements RoleRepositoryInterface
{
    public function findById(int|string $id): ?RoleInterface
    {
        // Your implementation
    }

    // ... implement other methods
}
```

Register in a service provider:

```php
$this->app->bind(
    RoleRepositoryInterface::class,
    CustomRoleRepository::class
);
```

### Custom Audit Logger

Implement `DelegationAuditInterface`:

```php
use Ordain\Delegation\Contracts\DelegationAuditInterface;

class SlackDelegationAudit implements DelegationAuditInterface
{
    public function logRoleAssigned(
        DelegatableUserInterface $delegator,
        DelegatableUserInterface $target,
        RoleInterface $role
    ): void {
        // Send to Slack
    }

    // ... implement other methods
}
```

## Database Schema

The package creates the following tables:

### Columns added to `users` table:
- `can_manage_users` (boolean): Whether user can create/manage other users
- `max_manageable_users` (integer, nullable): Maximum users this user can create
- `created_by_user_id` (foreign key, nullable): Reference to creator user

### `user_assignable_roles` table:
- `user_id`: The delegator user
- `role_id`: Role they can assign

### `user_assignable_permissions` table:
- `user_id`: The delegator user
- `permission_id`: Permission they can grant

### `delegation_audit_logs` table:
- `action`: Type of action performed
- `performed_by_id`: User who performed the action
- `target_user_id`: Target user of the action
- `metadata`: JSON data with additional context
- `ip_address`: Client IP address
- `user_agent`: Client user agent
- `created_at`: Timestamp

## Environment Variables

```env
DELEGATION_USER_MODEL=App\Models\User
DELEGATION_ROLE_MODEL=Spatie\Permission\Models\Role
DELEGATION_PERMISSION_MODEL=Spatie\Permission\Models\Permission
DELEGATION_SUPER_ADMIN_BYPASS=true
DELEGATION_SUPER_ADMIN_ROLE=super-admin
DELEGATION_AUDIT_ENABLED=true
DELEGATION_AUDIT_DRIVER=database
DELEGATION_AUDIT_LOG_CHANNEL=stack
DELEGATION_CACHE_ENABLED=true
DELEGATION_CACHE_TTL=3600
DELEGATION_REQUIRE_OWN_ACCESS=false
DELEGATION_PREVENT_ESCALATION=true
```

## Testing

```bash
composer test
```

With coverage:

```bash
composer test-coverage
```

## Architecture

The package follows SOLID principles with:

- **Contracts**: All dependencies are abstracted behind interfaces
- **Value Objects**: Immutable `DelegationScope` and `DelegationResult`
- **Repository Pattern**: Data access abstracted for flexibility
- **Adapter Pattern**: Integration with external packages (Spatie)
- **Dependency Injection**: All services injected via constructor
- **Single Responsibility**: Each class has one clear purpose

## License

MIT License