# Configuration

After publishing, edit `config/permission-delegation.php` to customize the package behavior.

## Models

Configure the model classes used by the package:

```php
'models' => [
    'user' => env('DELEGATION_USER_MODEL', App\Models\User::class),
    'role' => env('DELEGATION_ROLE_MODEL', Spatie\Permission\Models\Role::class),
    'permission' => env('DELEGATION_PERMISSION_MODEL', Spatie\Permission\Models\Permission::class),
],
```

## Table Names

Customize the database table names if needed:

```php
'tables' => [
    'user_assignable_roles' => 'user_assignable_roles',
    'user_assignable_permissions' => 'user_assignable_permissions',
    'delegation_audit_logs' => 'delegation_audit_logs',
],
```

> **Note:** If you change table names, update them before running migrations.

## Root Admin

Configure the root admin bypass:

```php
'root_admin' => [
    'enabled' => env('DELEGATION_ROOT_ADMIN_ENABLED', true),
    'role' => env('DELEGATION_ROOT_ADMIN_ROLE', 'root-admin'),
],
```

| Option | Description |
|--------|-------------|
| `enabled` | When `true`, users with the specified role bypass all delegation checks |
| `role` | The role name that grants root admin privileges |

Root admins can:
- Assign any role to any user
- Grant any permission to any user
- Manage any user regardless of hierarchy
- Bypass user creation quotas

## Audit Logging

Configure how delegation actions are logged:

```php
'audit' => [
    'enabled' => env('DELEGATION_AUDIT_ENABLED', true),
    'driver' => env('DELEGATION_AUDIT_DRIVER', 'database'),
    'log_channel' => env('DELEGATION_AUDIT_LOG_CHANNEL', 'stack'),
],
```

### Available Drivers

| Driver | Description |
|--------|-------------|
| `database` | Stores logs in the `delegation_audit_logs` table |
| `log` | Uses Laravel's logging system with the specified channel |
| `null` | Disables audit logging entirely |
| `App\Custom\AuditClass` | Use a custom class implementing `DelegationAuditInterface` |

### Database Driver

When using the `database` driver, logs are stored with:
- Action type (role_assigned, permission_granted, etc.)
- Delegator and target user IDs
- Metadata (role/permission details)
- IP address and user agent
- Timestamp

### Log Driver

When using the `log` driver, specify the Laravel log channel:

```php
'audit' => [
    'driver' => 'log',
    'log_channel' => 'delegation', // Custom channel
],
```

Configure the channel in `config/logging.php`:

```php
'channels' => [
    'delegation' => [
        'driver' => 'daily',
        'path' => storage_path('logs/delegation.log'),
        'days' => 30,
    ],
],
```

## Caching

Configure delegation data caching:

```php
'cache' => [
    'enabled' => env('DELEGATION_CACHE_ENABLED', true),
    'ttl' => env('DELEGATION_CACHE_TTL', 3600),
    'prefix' => 'delegation_',
],
```

| Option | Description |
|--------|-------------|
| `enabled` | Enable/disable caching |
| `ttl` | Cache time-to-live in seconds (default: 1 hour) |
| `prefix` | Prefix for cache keys |

### Cached Data

When enabled, the following is cached per user:
- Delegation scope
- Assignable roles
- Assignable permissions
- Authorization check results

### Clearing Cache

```bash
# Clear cache for a specific user
php artisan delegation:cache-reset 123

# Clear cache for all users
php artisan delegation:cache-reset
```

Or programmatically:

```php
use Ordain\Delegation\Facades\Delegation;

Delegation::forgetCache($user);
```

## Rate Limiting

Configure rate limiting for delegation operations:

```php
'rate_limiting' => [
    'enabled' => env('DELEGATION_RATE_LIMIT_ENABLED', true),
    'max_attempts' => env('DELEGATION_RATE_LIMIT_ATTEMPTS', 60),
    'decay_minutes' => env('DELEGATION_RATE_LIMIT_DECAY', 1),
],
```

| Option | Description |
|--------|-------------|
| `enabled` | Enable/disable rate limiting |
| `max_attempts` | Maximum attempts per decay period |
| `decay_minutes` | Period in minutes before attempts reset |

## Events

Configure event dispatching:

```php
'events' => [
    'enabled' => env('DELEGATION_EVENTS_ENABLED', true),
],
```

When enabled, the package dispatches events for all delegation actions. See [Events](events.md) for details.

## Features

Toggle optional features:

```php
'features' => [
    'blade_directives' => true,
    'route_macros' => true,
],
```

| Feature | Description |
|---------|-------------|
| `blade_directives` | Register `@canDelegate`, `@canAssignRole`, `@canManageUser` |
| `route_macros` | Register `->canDelegate()`, `->canAssignRole()`, `->canManageUser()` |

## Validation

Configure delegation validation rules:

```php
'validation' => [
    'require_own_access' => env('DELEGATION_REQUIRE_OWN_ACCESS', false),
    'prevent_privilege_escalation' => env('DELEGATION_PREVENT_ESCALATION', true),
],
```

| Option | Description |
|--------|-------------|
| `require_own_access` | Delegator must have the role/permission themselves to assign it |
| `prevent_privilege_escalation` | Prevent assigning roles/permissions with higher privileges |

## Environment Variables

All configuration options can be set via environment variables:

```env
# Models
DELEGATION_USER_MODEL=App\Models\User
DELEGATION_ROLE_MODEL=Spatie\Permission\Models\Role
DELEGATION_PERMISSION_MODEL=Spatie\Permission\Models\Permission

# Root Admin
DELEGATION_ROOT_ADMIN_ENABLED=true
DELEGATION_ROOT_ADMIN_ROLE=root-admin

# Audit
DELEGATION_AUDIT_ENABLED=true
DELEGATION_AUDIT_DRIVER=database
DELEGATION_AUDIT_LOG_CHANNEL=stack

# Cache
DELEGATION_CACHE_ENABLED=true
DELEGATION_CACHE_TTL=3600

# Rate Limiting
DELEGATION_RATE_LIMIT_ENABLED=true
DELEGATION_RATE_LIMIT_ATTEMPTS=60
DELEGATION_RATE_LIMIT_DECAY=1

# Events
DELEGATION_EVENTS_ENABLED=true

# Validation
DELEGATION_REQUIRE_OWN_ACCESS=false
DELEGATION_PREVENT_ESCALATION=true
```

## Next Steps

- [Core Concepts](concepts.md) - Understand hierarchical delegation
- [Basic Usage](basic-usage.md) - Start using the package