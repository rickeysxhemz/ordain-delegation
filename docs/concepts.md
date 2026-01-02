# Core Concepts

Understanding the principles behind hierarchical permission delegation.

## What Problem Does This Solve?

Traditional RBAC packages like [spatie/laravel-permission](https://github.com/spatie/laravel-permission) answer: *"What can this user do?"*

This package answers a different question: *"What can this user **grant to others**?"*

### The Privilege Escalation Problem

Consider a SaaS platform with team management:

```
Super Admin (all permissions)
├── Regional Manager A (manages Region A)
│   ├── Team Lead 1 (manages 5 users)
│   │   ├── Editor 1
│   │   └── Editor 2
│   └── Team Lead 2 (manages 3 users)
│       └── Editor 3
└── Regional Manager B (manages Region B)
    └── ...
```

**Without delegation control**, Team Lead 1 could:
- Assign themselves the "admin" role (privilege escalation)
- Create unlimited users (quota bypass)
- Manage Editor 3 who belongs to Team Lead 2 (boundary violation)
- Grant permissions they don't have authority over

**With this package**, Team Lead 1 can only:
- Assign roles they've been explicitly authorized to delegate
- Create users up to their quota limit
- Manage only users they created
- Grant permissions within their delegation scope

## Core Principles

### 1. Downward Authority Flow

Authority flows downward through the hierarchy. Users can only delegate a **subset** of their own delegation scope—never more.

```php
// Regional Manager's scope
$manager->assignableRoles;      // ['team-lead', 'editor']
$manager->assignablePermissions; // ['view-reports', 'edit-content']

// Even if they HAVE the admin role, they cannot delegate it
$manager->hasRole('admin');  // true
$manager->assignableRoles;   // Does NOT include 'admin'
```

### 2. Creator-Based Hierarchy

Users form a tree structure based on who created whom:

```php
// Relationships
$user->creator;       // The user who created this user
$user->createdUsers;  // Users created by this user

// Management is scoped to the creator's subtree
$delegation->canManageUser($teamLead, $editor1); // true (created by team lead)
$delegation->canManageUser($teamLead, $editor3); // false (created by different team lead)
```

### 3. Native Escalation Prevention

The package prevents privilege escalation at the service level:

```php
// This check considers the delegator's scope, not their roles
$delegation->canAssignRole($delegator, $adminRole, $target);

// Even if the delegator has admin role, they can only assign
// roles that are in their assignableRoles list
```

### 4. Quota Enforcement

Limit how many users each manager can create:

```php
$scope = new DelegationScope(
    canManageUsers: true,
    maxManageableUsers: 10,  // null = unlimited
);

$delegation->hasReachedUserLimit($user);   // true if at limit
$delegation->getRemainingQuota($user);     // remaining slots
$delegation->getCreatedUsersCount($user);  // current count
```

## Key Terms

| Term | Definition |
|------|------------|
| **Delegator** | The user performing the delegation (assigning roles/permissions) |
| **Target** | The user receiving the delegation (being assigned roles/permissions) |
| **Delegation Scope** | The boundaries of what a user can delegate to others |
| **Assignable Roles** | Roles a user is authorized to assign to users they manage |
| **Assignable Permissions** | Permissions a user is authorized to grant |
| **User Quota** | Maximum number of users a delegator can create |
| **Root Admin** | A user who bypasses all delegation checks |

## The Delegation Scope

A `DelegationScope` value object encapsulates what a user can delegate:

```php
use Ordain\Delegation\Domain\ValueObjects\DelegationScope;

$scope = new DelegationScope(
    canManageUsers: true,           // Can create and manage users
    maxManageableUsers: 10,         // Can create up to 10 users
    assignableRoleIds: [1, 2, 3],   // Can assign these role IDs
    assignablePermissionIds: [4, 5], // Can grant these permission IDs
);
```

### Factory Methods

```php
// No delegation rights
$scope = DelegationScope::none();

// Full delegation rights with specific roles
$scope = DelegationScope::unlimited([1, 2, 3]);

// Limited quota with roles
$scope = DelegationScope::limited(maxUsers: 10, roleIds: [1, 2]);

// Using the builder
$scope = DelegationScope::builder()
    ->allowUserManagement()
    ->maxUsers(10)
    ->withRoles([1, 2, 3])
    ->withPermissions([4, 5])
    ->build();
```

### Immutability

Scopes are immutable. Modifications return new instances:

```php
$newScope = $scope->withMaxUsers(20);
$newScope = $scope->withRoles([1, 2, 3, 4]);
$newScope = $scope->withoutUserManagement();

// Original scope unchanged
$scope->maxManageableUsers; // Still 10
```

## How It Works With Spatie

This package works **alongside** spatie/laravel-permission:

```
┌─────────────────────────────────┐
│     spatie/laravel-permission   │
│                                 │
│  "What can this user DO?"       │
│  - Define roles/permissions     │
│  - Assign to users              │
│  - Check with $user->can()      │
└───────────────┬─────────────────┘
                │
                │ integrates with
                │
┌───────────────▼─────────────────┐
│       ordain/delegation         │
│                                 │
│  "What can this user GRANT?"    │
│  - Who can assign which roles   │
│  - Who can manage whom          │
│  - Delegation quotas            │
│  - Audit trail                  │
└─────────────────────────────────┘
```

**Spatie handles:**
- Role and permission definitions
- User-role/permission assignments
- Permission checks (`$user->can('edit articles')`)

**This package handles:**
- Who can assign which roles to whom
- Who can grant which permissions
- User creation hierarchies and quotas
- Delegation audit logging

## Visual Flow

```
┌─────────────────────────────────────────────────────────────┐
│                       ROOT ADMIN                            │
│  Scope: Any role, unlimited users                           │
│  Can manage: Anyone                                         │
└─────────────────────────┬───────────────────────────────────┘
                          │ creates & configures
                          ▼
┌─────────────────────────────────────────────────────────────┐
│                   REGIONAL MANAGER                          │
│  Scope: ['team-lead', 'editor'], max 20 users              │
│  Can manage: Only users they created                        │
└─────────────────────────┬───────────────────────────────────┘
                          │ creates & configures
                          ▼
┌─────────────────────────────────────────────────────────────┐
│                      TEAM LEAD                              │
│  Scope: ['editor'], max 5 users                            │
│  Can manage: Only users they created                        │
└─────────────────────────┬───────────────────────────────────┘
                          │ creates
                          ▼
┌─────────────────────────────────────────────────────────────┐
│                        EDITOR                               │
│  Scope: none (cannot delegate)                              │
│  Can manage: No one                                         │
└─────────────────────────────────────────────────────────────┘
```

Each level can only pass down a **subset** of what they received.

## Root Admin Bypass

Users with the configured root admin role bypass all delegation checks:

```php
// config/permission-delegation.php
'root_admin' => [
    'enabled' => true,
    'role' => 'root-admin',
],
```

Root admins can:
- Assign any role to any user
- Grant any permission to any user
- Manage any user regardless of who created them
- Create unlimited users (bypasses quotas)

## Authorization Pipeline

When checking authorization, the package runs through a pipeline of checks:

1. **CheckRootAdminPipe** - If delegator is root admin, grant access
2. **CheckUserManagementPipe** - Verify delegator has `can_manage_users = true`
3. **CheckHierarchyPipe** - Verify delegator created the target user
4. **CheckRoleInScopePipe** - Verify role/permission is in delegator's scope

If any check fails, the authorization is denied.

## Audit Trail

All delegation actions are automatically logged:

```php
// Example audit log entry
[
    'action' => 'role_assigned',
    'performed_by_id' => 1,
    'target_user_id' => 5,
    'metadata' => [
        'role_id' => 3,
        'role_name' => 'editor',
    ],
    'ip_address' => '192.168.1.1',
    'user_agent' => 'Mozilla/5.0...',
    'created_at' => '2025-01-15 10:30:00',
]
```

## Next Steps

- [Basic Usage](basic-usage.md) - Start using the package
- [Advanced Usage](advanced-usage.md) - Batch operations and validation
- [API Reference](api-reference.md) - Complete method reference