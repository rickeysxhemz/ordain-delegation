# Installation

## Requirements

- PHP 8.2+
- Laravel 11.x or 12.x
- spatie/laravel-permission ^6.0

## Install via Composer

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

## Publish Configuration

```bash
php artisan vendor:publish --tag=delegation-config
```

## Publish and Run Migrations

```bash
php artisan vendor:publish --tag=delegation-migrations
php artisan migrate
```

## User Model Setup

Add the `HasDelegation` trait and implement `DelegatableUserInterface`:

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Traits\HasDelegation;

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

## Database Schema

The migrations create the following:

### Columns added to `users` table

| Column | Type | Description |
|--------|------|-------------|
| `can_manage_users` | boolean | Whether user can create/manage other users |
| `max_manageable_users` | integer (nullable) | Maximum users this user can create |
| `created_by_user_id` | foreign key (nullable) | Reference to creator user |

### New tables

| Table | Purpose |
|-------|---------|
| `user_assignable_roles` | Roles a user can assign |
| `user_assignable_permissions` | Permissions a user can grant |
| `delegation_audit_logs` | Audit trail of delegation actions |