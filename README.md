# Permission Delegation

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ordain/delegation.svg?style=flat-square)](https://packagist.org/packages/ordain/delegation)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/rickeysxhemz/ordain-delegation/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/rickeysxhemz/ordain-delegation/actions?query=workflow%3Atests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/rickeysxhemz/ordain-delegation/code-style.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/rickeysxhemz/ordain-delegation/actions?query=workflow%3Acode-style+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/ordain/delegation.svg?style=flat-square)](https://packagist.org/packages/ordain/delegation)
[![License](https://img.shields.io/packagist/l/ordain/delegation.svg?style=flat-square)](https://packagist.org/packages/ordain/delegation)

A hierarchical permission delegation system for Laravel 11/12 applications. Control who can assign roles and permissions to whom.

## Features

- User creation limits and quotas
- Role and permission delegation
- Hierarchical user management
- Super admin bypass
- Audit logging
- Built-in caching
- Domain events
- Artisan commands
- Route middleware
- Octane compatible

## Requirements

- PHP 8.2, 8.3, or 8.4
- Laravel 11.x or 12.x
- spatie/laravel-permission ^6.0

## Installation

```bash
composer require ordain/delegation
```

Publish the configuration and migrations:

```bash
php artisan vendor:publish --tag=delegation-config
php artisan vendor:publish --tag=delegation-migrations
php artisan migrate
```

Add the trait to your User model:

```php
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Traits\HasDelegation;

class User extends Authenticatable implements DelegatableUserInterface
{
    use HasDelegation;
}
```

## Usage

### Using Dependency Injection

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
        $role = Role::find($request->role_id);

        if (! $this->delegation->canAssignRole($delegator, $role, $target)) {
            abort(403);
        }

        $this->delegation->delegateRole($delegator, $target, $role);

        return response()->json(['message' => 'Role assigned.']);
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

// Check if user can create other users
if (Delegation::canCreateUsers($delegator)) {
    // Create user...
}

// Get assignable roles for a user
$roles = Delegation::getAssignableRoles($delegator);

// Get assignable permissions
$permissions = Delegation::getAssignablePermissions($delegator);

// Check user creation limits
$remaining = Delegation::getRemainingUserQuota($delegator);
$hasReachedLimit = Delegation::hasReachedUserLimit($delegator);
```

## Middleware

The package provides three middleware for protecting routes:

```php
use Ordain\Delegation\Http\Middleware\CanDelegateMiddleware;
use Ordain\Delegation\Http\Middleware\CanAssignRoleMiddleware;
use Ordain\Delegation\Http\Middleware\CanManageUserMiddleware;

// In your route file
Route::middleware(CanDelegateMiddleware::class)->group(function () {
    Route::post('/users/{user}/roles', [UserController::class, 'assignRole']);
});

// Or register as route middleware aliases in bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'can.delegate' => CanDelegateMiddleware::class,
        'can.assign-role' => CanAssignRoleMiddleware::class,
        'can.manage-user' => CanManageUserMiddleware::class,
    ]);
})

// Then use in routes
Route::middleware('can.delegate')->group(function () {
    // Protected routes...
});
```

## Artisan Commands

```bash
# Show delegation scope for a user
php artisan delegation:show {user_id}

# Assign a role to a user
php artisan delegation:assign-role {user_id} {role_name}

# Clear delegation cache
php artisan delegation:cache-reset {user_id?}
```

## Events

The package dispatches events for all delegation actions:

| Event | Description |
|-------|-------------|
| `RoleDelegated` | Fired when a role is assigned to a user |
| `RoleRevoked` | Fired when a role is revoked from a user |
| `PermissionGranted` | Fired when a permission is granted to a user |
| `PermissionRevoked` | Fired when a permission is revoked from a user |
| `DelegationScopeUpdated` | Fired when a user's delegation scope changes |
| `UnauthorizedDelegationAttempted` | Fired when an unauthorized delegation is attempted |

### Listening to Events

```php
use Ordain\Delegation\Events\RoleDelegated;

class SendRoleAssignmentNotification
{
    public function handle(RoleDelegated $event): void
    {
        $event->delegator;  // User who assigned the role
        $event->target;     // User who received the role
        $event->role;       // The role that was assigned
    }
}
```

Register in `EventServiceProvider`:

```php
protected $listen = [
    \Ordain\Delegation\Events\RoleDelegated::class => [
        \App\Listeners\SendRoleAssignmentNotification::class,
    ],
];
```

## Documentation

- [Installation](docs/installation.md)
- [Configuration](docs/configuration.md)
- [Usage](docs/usage.md)
- [Middleware](docs/middleware.md)
- [Customization](docs/customization.md)

## Testing

```bash
composer test
composer test-coverage
```

## Architecture

The package follows SOLID principles:

- **Contracts** - All dependencies abstracted behind interfaces
- **Value Objects** - Immutable `DelegationScope` and `DelegationResult`
- **Repository Pattern** - Data access abstraction
- **Adapter Pattern** - Integration with external packages
- **Dependency Injection** - Constructor injection throughout

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Contributions are welcome! Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email dev@ordain.dev instead of using the issue tracker.

## Credits

- [Waqas Majeed](https://github.com/rickeysxhemz)
- [All Contributors](https://github.com/rickeysxhemz/ordain-delegation/contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.