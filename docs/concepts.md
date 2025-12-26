# Core Concepts

## What is Authority Delegation?

Authority delegation is a pattern where users can grant others a **subset** of their own permissions—never more. This creates a natural hierarchy where authority flows downward, and each level can only pass on what it already possesses.

## The Problem This Solves

Traditional RBAC packages (like spatie/laravel-permission) answer: *"What can this user do?"*

This package answers: *"What can this user grant to others?"*

### Example Scenario

Consider a SaaS platform with regional managers:

```
Super Admin (all permissions)
    └── Regional Manager A (can manage users in Region A)
            └── Team Lead (can manage 5 users, assign 'editor' role only)
                    └── Editor (no delegation rights)
```

Without delegation control:
- Team Lead could assign 'admin' role (privilege escalation)
- Team Lead could create unlimited users (quota bypass)
- Team Lead could manage users outside their hierarchy (boundary violation)

With this package:
- Team Lead can only assign roles they've been granted to delegate
- Team Lead is limited to their user creation quota
- Team Lead can only manage users they created

## Core Principles

### 1. Downward Authority Flow

Authority only flows downward through the hierarchy. A user cannot:
- Grant permissions they don't have
- Assign roles they aren't authorized to delegate
- Manage users outside their subtree

```php
// Regional Manager can only delegate what they're allowed to
$manager->assignableRoles;      // ['team-lead', 'editor']
$manager->assignablePermissions; // ['view-reports', 'edit-content']

// They CANNOT delegate 'admin' role even if they have it
```

### 2. Native Escalation Prevention

The package prevents privilege escalation at the service level:

```php
// This will fail if delegator doesn't have 'admin' in assignableRoles
$delegation->canAssignRole($delegator, $adminRole, $target); // false

// Even if delegator has the role themselves
$delegator->hasRole('admin'); // true
$delegation->canAssignRole($delegator, $adminRole, $target); // still false
```

### 3. Hierarchical User Management

Users form a tree based on who created whom:

```php
// creator relationship
$user->creator;        // User who created this user
$user->createdUsers;   // Users created by this user

// Management is scoped to the subtree
$delegation->canManageUser($manager, $targetUser);
// true only if $targetUser is in $manager's subtree
```

### 4. Quota Enforcement

Limit how many users each manager can create:

```php
$scope = new DelegationScope(
    canManageUsers: true,
    maxManageableUsers: 10,  // null = unlimited
);

$delegation->hasReachedUserLimit($user);     // true if at limit
$delegation->getRemainingUserQuota($user);   // remaining slots
```

## Key Terms

| Term | Definition |
|------|------------|
| **Delegator** | User performing the delegation (granting roles/permissions) |
| **Target** | User receiving the delegation |
| **Delegation Scope** | The boundaries of what a user can delegate |
| **Assignable Roles** | Roles a user is authorized to assign to others |
| **Assignable Permissions** | Permissions a user is authorized to grant |
| **User Quota** | Maximum users a delegator can create |

## How It Integrates with Spatie

This package works **alongside** spatie/laravel-permission, not replacing it:

```
spatie/laravel-permission     ordain/delegation
         │                           │
         ▼                           ▼
   "What can I do?"          "What can I grant?"
         │                           │
         └───────────┬───────────────┘
                     ▼
            Complete authorization
```

Spatie handles:
- Role/permission definitions
- User-role assignments
- Permission checks (`$user->can()`)

This package handles:
- Who can assign which roles
- Who can grant which permissions
- User creation hierarchies
- Delegation quotas

## Delegation Scope

The `DelegationScope` value object encapsulates delegation boundaries:

```php
$scope = new DelegationScope(
    canManageUsers: true,           // Can create/manage users
    maxManageableUsers: 10,         // User creation limit
    assignableRoleIds: [1, 2, 3],   // Role IDs they can assign
    assignablePermissionIds: [4, 5], // Permission IDs they can grant
);
```

### Factory Methods

```php
DelegationScope::none();           // No delegation rights
DelegationScope::unlimited([1,2]); // Full rights with specified roles
```

### Immutable Updates

```php
$newScope = $scope->withMaxUsers(20);
$newScope = $scope->withRoles([1, 2, 3, 4]);
```

## Super Admin Bypass

Configurable super admin role bypasses all delegation checks:

```php
// config/permission-delegation.php
'super_admin' => [
    'enabled' => true,
    'role' => 'super-admin',
],
```

Super admins can:
- Assign any role
- Grant any permission
- Manage any user
- Bypass quotas

## Audit Trail

All delegation actions are logged:

```php
// delegation_audit_logs table
[
    'action' => 'role_delegated',
    'performed_by_id' => 1,
    'target_user_id' => 5,
    'metadata' => ['role_id' => 3, 'role_name' => 'editor'],
    'ip_address' => '192.168.1.1',
    'created_at' => '2025-01-15 10:30:00',
]
```

## Visual Flow

```
┌─────────────────────────────────────────────────────────┐
│                    Super Admin                          │
│  Scope: unlimited roles, unlimited users                │
└─────────────────────┬───────────────────────────────────┘
                      │ creates
                      ▼
┌─────────────────────────────────────────────────────────┐
│                Regional Manager                         │
│  Scope: ['team-lead', 'editor'], max 20 users          │
└─────────────────────┬───────────────────────────────────┘
                      │ creates
                      ▼
┌─────────────────────────────────────────────────────────┐
│                   Team Lead                             │
│  Scope: ['editor'], max 5 users                        │
└─────────────────────┬───────────────────────────────────┘
                      │ creates
                      ▼
┌─────────────────────────────────────────────────────────┐
│                     Editor                              │
│  Scope: none (cannot delegate)                         │
└─────────────────────────────────────────────────────────┘
```

Each level can only pass down a subset of what they received.