# Permission Delegation Package

A decoupled, framework-agnostic permission delegation system for Laravel 11/12 applications. This package enables hierarchical user management where administrators can delegate specific roles and permissions to other users within defined boundaries.

## Features

- **User Creation Limits**: Define how many users each manager can create
- **Role Delegation**: Control which roles a user can assign to others
- **Permission Delegation**: Control which permissions a user can grant to others
- **Hierarchical Management**: Users can only manage users they created
- **Super Admin Bypass**: Configurable super admin role that bypasses all restrictions
- **Audit Logging**: Multiple logging backends (database, file, null)
- **Framework Agnostic**: Works with any role/permission system via contracts

## Requirements

- PHP 8.2+
- Laravel 11.x or 12.x

## Installation

### 1. Add the package autoload to your `composer.json`:

```json
{
    "autoload": {
        "psr-4": {
            "Ewaa\\PermissionDelegation\\": "packages/permission-delegation/src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Ewaa\\PermissionDelegation\\Tests\\": "packages/permission-delegation/tests/"
        }
    }
}
```

### 2. Regenerate autoload files:

```bash
composer dump-autoload
```

### 3. Register the service provider in `bootstrap/providers.php`:

```php
return [
    // ...
    Ewaa\PermissionDelegation\Providers\PermissionDelegationServiceProvider::class,
];
```

### 4. Publish the configuration:

```bash
php artisan vendor:publish --tag=permission-delegation-config
```

### 5. Publish and run migrations:

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

    // Super admin role that bypasses all delegation checks
    'super_admin_role' => 'super-admin',

    // Enable super admin bypass
    'super_admin_bypass' => true,

    // Audit logging driver: 'database', 'log', or 'null'
    'audit_driver' => 'database',

    // Log channel for 'log' driver
    'audit_log_channel' => 'stack',

    // Table names
    'tables' => [
        'user_assignable_roles' => 'user_assignable_roles',
        'user_assignable_permissions' => 'user_assignable_permissions',
        'delegation_audit_logs' => 'delegation_audit_logs',
    ],
];
```

## User Model Setup

Add the `HasDelegation` trait to your User model:

```php
<?php

namespace App\Models;

use Ewaa\PermissionDelegation\Contracts\DelegatableUserInterface;
use Ewaa\PermissionDelegation\Traits\HasDelegation;
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
use Ewaa\PermissionDelegation\Contracts\DelegationServiceInterface;

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

### Setting Delegation Scope

```php
use Ewaa\PermissionDelegation\Domain\ValueObjects\DelegationScope;

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
$scope = DelegationScope::limited(5, [1, 2]); // Max 5 users, specific roles
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

### Exception Handling

```php
use Ewaa\PermissionDelegation\Exceptions\UnauthorizedDelegationException;

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
use Ewaa\PermissionDelegation\Contracts\Repositories\RoleRepositoryInterface;

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
use Ewaa\PermissionDelegation\Contracts\DelegationAuditInterface;

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

## Testing

```bash
php artisan test packages/permission-delegation/tests/Unit
```

## Architecture

The package follows SOLID principles with:

- **Contracts**: All dependencies are abstracted behind interfaces
- **Value Objects**: Immutable `DelegationScope` and `DelegationResult`
- **Repository Pattern**: Data access abstracted for flexibility
- **Dependency Injection**: All services injected via constructor
- **Single Responsibility**: Each class has one clear purpose

## License

MIT License