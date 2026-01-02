# Permission Delegation for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ordain/delegation.svg?style=flat-square)](https://packagist.org/packages/ordain/delegation)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/rickeysxhemz/ordain-delegation/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/rickeysxhemz/ordain-delegation/actions?query=workflow%3Atests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/rickeysxhemz/ordain-delegation/code-style.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/rickeysxhemz/ordain-delegation/actions?query=workflow%3Acode-style+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/ordain/delegation.svg?style=flat-square)](https://packagist.org/packages/ordain/delegation)
[![License](https://img.shields.io/packagist/l/ordain/delegation.svg?style=flat-square)](https://packagist.org/packages/ordain/delegation)

**Scoped authority delegation for Laravel.** Enforce hierarchical permission boundaries where authority flows downward—users delegate subsets of their own grants, never more. Native escalation prevention with [spatie/laravel-permission](https://github.com/spatie/laravel-permission) integration.

## The Problem

Traditional RBAC answers: *"What can this user do?"*

This package answers: *"What can this user **grant to others**?"*

Without delegation control, a team lead could assign admin roles, create unlimited users, or manage users outside their hierarchy. This package prevents that.

## Features

- **Hierarchical user management** - Users only manage users they created
- **Role & permission delegation** - Control which roles/permissions users can assign
- **User creation quotas** - Limit how many users each manager can create
- **Native escalation prevention** - Cannot grant more than you have
- **Root admin bypass** - Configurable super-user override
- **Comprehensive audit logging** - Track all delegation actions
- **Domain events** - React to delegation changes
- **Built-in caching** - Optimized for performance
- **Blade directives & route macros** - Convenient view and routing helpers
- **Artisan commands** - CLI tools for management
- **Octane compatible** - Ready for high-performance deployments

## Requirements

- PHP 8.2+
- Laravel 11.x or 12.x
- [spatie/laravel-permission](https://github.com/spatie/laravel-permission) ^6.0

## Installation

Install the package via Composer:

```bash
composer require ordain/delegation
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag=delegation-migrations
php artisan migrate
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=delegation-config
```

Add the trait to your User model:

```php
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Traits\HasDelegation;

class User extends Authenticatable implements DelegatableUserInterface
{
    use HasDelegation;

    protected $fillable = [
        // ... your fields
        'can_manage_users',
        'max_manageable_users',
        'created_by_user_id',
    ];
}
```

## Quick Start

### Check Authorization

```php
use Ordain\Delegation\Facades\Delegation;

// Can this user assign a role to another user?
if (Delegation::canAssignRole($delegator, $role, $target)) {
    Delegation::delegateRole($delegator, $target, $role);
}

// Can this user create new users?
if (Delegation::canCreateUsers($user)) {
    // Create user...
}

// What roles can this user assign?
$assignableRoles = Delegation::getAssignableRoles($user);
```

### Set Delegation Scope

```php
use Ordain\Delegation\Domain\ValueObjects\DelegationScope;

// Define what a manager can delegate
$scope = new DelegationScope(
    canManageUsers: true,
    maxManageableUsers: 10,
    assignableRoleIds: [1, 2, 3],
    assignablePermissionIds: [4, 5],
);

Delegation::setDelegationScope($manager, $scope);
```

### Protect Routes

```php
// Using middleware
Route::middleware('can.delegate')->group(function () {
    Route::post('/users', [UserController::class, 'store']);
});

Route::middleware('can.assign.role:editor,moderator')
    ->post('/users/{user}/roles', [RoleController::class, 'store']);

// Using route macros
Route::post('/users', [UserController::class, 'store'])
    ->canDelegate();

Route::post('/users/{user}/roles', [RoleController::class, 'store'])
    ->canAssignRole(['editor', 'moderator']);
```

### Blade Directives

```blade
@canDelegate
    <a href="{{ route('users.create') }}">Create User</a>
@endCanDelegate

@canAssignRole('admin')
    <option value="admin">Administrator</option>
@endCanAssignRole
```

## Documentation

| Documentation | Description |
|---------------|-------------|
| [Installation](docs/installation.md) | Detailed installation and setup guide |
| [Configuration](docs/configuration.md) | All configuration options explained |
| [Core Concepts](docs/concepts.md) | Understanding hierarchical delegation |
| [Basic Usage](docs/basic-usage.md) | Common usage patterns |
| [Advanced Usage](docs/advanced-usage.md) | Batch operations, validation, caching |
| [Middleware](docs/middleware.md) | Route protection middleware |
| [Blade & Routes](docs/blade-and-routes.md) | Blade directives and route macros |
| [Events](docs/events.md) | Domain events and listeners |
| [Commands](docs/commands.md) | Artisan console commands |
| [Customization](docs/customization.md) | Extending the package |
| [API Reference](docs/api-reference.md) | Complete method reference |
| [Testing](docs/testing.md) | Testing your implementation |
| [Troubleshooting](docs/troubleshooting.md) | Common issues and solutions |

## Artisan Commands

```bash
# Interactive installation wizard
php artisan delegation:install

# Display user's delegation scope
php artisan delegation:show {user}

# Assign role via CLI
php artisan delegation:assign {delegator} {target} {role}

# Clear delegation cache
php artisan delegation:cache-reset {user?}

# Health check
php artisan delegation:health
```

## Testing

```bash
composer test
```

With coverage:

```bash
composer test-coverage
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Waqas Majeed](https://github.com/rickeysxhemz)
- [All Contributors](https://github.com/rickeysxhemz/ordain-delegation/graphs/contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.