# Permission Delegation

A hierarchical permission delegation system for Laravel 11/12 applications.

## Features

- User creation limits and quotas
- Role and permission delegation
- Hierarchical user management
- Super admin bypass
- Audit logging
- Built-in caching
- Octane compatible

## Requirements

- PHP 8.2+
- Laravel 11.x or 12.x
- spatie/laravel-permission ^6.0

## Quick Start

```bash
composer require ordain/delegation
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

## Basic Usage

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

## License

MIT License