# Configuration

After publishing, edit `config/permission-delegation.php`.

## Models

```php
'user_model' => App\Models\User::class,
'role_model' => Spatie\Permission\Models\Role::class,
'permission_model' => Spatie\Permission\Models\Permission::class,
```

## Table Names

```php
'tables' => [
    'user_assignable_roles' => 'user_assignable_roles',
    'user_assignable_permissions' => 'user_assignable_permissions',
    'delegation_audit_logs' => 'delegation_audit_logs',
],
```

## Super Admin

```php
'super_admin' => [
    'enabled' => true,
    'role' => 'super-admin',
],
```

When enabled, users with the specified role bypass all delegation checks.

## Audit Logging

```php
'audit' => [
    'enabled' => true,
    'driver' => 'database',
    'log_channel' => 'stack',
],
```

Available drivers:
- `database` - Stores logs in `delegation_audit_logs` table
- `log` - Uses Laravel's logging system
- `null` - Disables audit logging
- Custom class implementing `DelegationAuditInterface`

## Caching

```php
'cache' => [
    'enabled' => true,
    'ttl' => 3600,
    'prefix' => 'delegation_',
],
```

## Validation Rules

```php
'validation' => [
    'require_own_access' => false,
    'prevent_privilege_escalation' => true,
],
```

| Option | Description |
|--------|-------------|
| `require_own_access` | Delegator must have the role/permission themselves |
| `prevent_privilege_escalation` | Prevents assigning higher privileges than delegator has |

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