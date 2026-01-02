# Installation

This guide covers installing and configuring the Permission Delegation package for Laravel.

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP | 8.2, 8.3, or 8.4 |
| Laravel | 11.x or 12.x |
| spatie/laravel-permission | ^6.0 |

## Install via Composer

```bash
composer require ordain/delegation
```

The service provider is auto-discovered. If auto-discovery is disabled, register it manually:

```php
// bootstrap/providers.php
return [
    // ...
    Ordain\Delegation\Providers\DelegationServiceProvider::class,
];
```

## Publish Configuration

```bash
php artisan vendor:publish --tag=delegation-config
```

This creates `config/permission-delegation.php`. See [Configuration](configuration.md) for all options.

## Publish and Run Migrations

```bash
php artisan vendor:publish --tag=delegation-migrations
php artisan migrate
```

## User Model Setup

Your User model must implement `DelegatableUserInterface`. The easiest way is using the `HasDelegation` trait:

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Ordain\Delegation\Contracts\DelegatableUserInterface;
use Ordain\Delegation\Traits\HasDelegation;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements DelegatableUserInterface
{
    use HasRoles;
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
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'can_manage_users' => 'boolean',
        'max_manageable_users' => 'integer',
    ];
}
```

> **Important:** You must add `can_manage_users`, `max_manageable_users`, and `created_by_user_id` to your `$fillable` array if you want to mass-assign these fields.

## Database Schema

The migrations create the following:

### Columns Added to `users` Table

| Column | Type | Default | Description |
|--------|------|---------|-------------|
| `can_manage_users` | boolean | `false` | Whether user can create/manage other users |
| `max_manageable_users` | integer | `null` | Maximum users this user can create (`null` = unlimited) |
| `created_by_user_id` | foreign key | `null` | Reference to the user who created this user |

### New Tables

| Table | Purpose |
|-------|---------|
| `user_assignable_roles` | Pivot table linking users to roles they can assign |
| `user_assignable_permissions` | Pivot table linking users to permissions they can grant |
| `delegation_audit_logs` | Audit trail of all delegation actions |

## Installation Command

For a guided installation, use the install command:

```bash
php artisan delegation:install
```

This interactive wizard will:
1. Publish the configuration file
2. Publish the migrations
3. Run the migrations
4. Guide you through initial setup

## Verify Installation

Run the health check command to verify everything is configured correctly:

```bash
php artisan delegation:health
```

## Next Steps

- [Configuration](configuration.md) - Configure the package for your needs
- [Core Concepts](concepts.md) - Understand how hierarchical delegation works
- [Basic Usage](basic-usage.md) - Start using the package