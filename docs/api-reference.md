# API Reference

Complete reference for all public methods and interfaces.

## DelegationServiceInterface

The main service interface for all delegation operations.

### Authorization Methods

#### canAssignRole

Check if delegator can assign a role to target.

```php
public function canAssignRole(
    DelegatableUserInterface $delegator,
    RoleInterface $role,
    ?DelegatableUserInterface $target = null,
): bool;
```

**Parameters:**
- `$delegator` - User attempting to assign the role
- `$role` - Role to assign
- `$target` - User to receive the role (optional for general check)

**Returns:** `bool` - Whether assignment is allowed

---

#### canAssignPermission

Check if delegator can grant a permission to target.

```php
public function canAssignPermission(
    DelegatableUserInterface $delegator,
    PermissionInterface $permission,
    ?DelegatableUserInterface $target = null,
): bool;
```

---

#### canRevokeRole

Check if delegator can revoke a role from target.

```php
public function canRevokeRole(
    DelegatableUserInterface $delegator,
    RoleInterface $role,
    DelegatableUserInterface $target,
): bool;
```

---

#### canRevokePermission

Check if delegator can revoke a permission from target.

```php
public function canRevokePermission(
    DelegatableUserInterface $delegator,
    PermissionInterface $permission,
    DelegatableUserInterface $target,
): bool;
```

---

#### canManageUser

Check if delegator can manage target user.

```php
public function canManageUser(
    DelegatableUserInterface $delegator,
    DelegatableUserInterface $target,
): bool;
```

---

#### canCreateUsers

Check if user has delegation capabilities.

```php
public function canCreateUsers(DelegatableUserInterface $user): bool;
```

### Quota Methods

#### hasReachedUserLimit

Check if user has reached their creation quota.

```php
public function hasReachedUserLimit(DelegatableUserInterface $user): bool;
```

---

#### getRemainingQuota

Get remaining user creation slots.

```php
public function getRemainingQuota(DelegatableUserInterface $user): ?int;
```

**Returns:** `int` remaining slots, or `null` if unlimited

---

#### getCreatedUsersCount

Get count of users created by this user.

```php
public function getCreatedUsersCount(DelegatableUserInterface $user): int;
```

### Retrieval Methods

#### getAssignableRoles

Get roles the user can assign.

```php
public function getAssignableRoles(DelegatableUserInterface $user): Collection;
```

**Returns:** `Collection<RoleInterface>`

---

#### getAssignablePermissions

Get permissions the user can grant.

```php
public function getAssignablePermissions(DelegatableUserInterface $user): Collection;
```

**Returns:** `Collection<PermissionInterface>`

---

#### getDelegationScope

Get user's complete delegation scope.

```php
public function getDelegationScope(DelegatableUserInterface $user): DelegationScope;
```

### Delegation Operations

#### delegateRole

Assign a role to target user.

```php
public function delegateRole(
    DelegatableUserInterface $delegator,
    DelegatableUserInterface $target,
    RoleInterface $role,
): void;
```

**Throws:** `UnauthorizedDelegationException` if not authorized

---

#### revokeRole

Remove a role from target user.

```php
public function revokeRole(
    DelegatableUserInterface $delegator,
    DelegatableUserInterface $target,
    RoleInterface $role,
): void;
```

---

#### delegatePermission

Grant a permission to target user.

```php
public function delegatePermission(
    DelegatableUserInterface $delegator,
    DelegatableUserInterface $target,
    PermissionInterface $permission,
): void;
```

---

#### revokePermission

Remove a permission from target user.

```php
public function revokePermission(
    DelegatableUserInterface $delegator,
    DelegatableUserInterface $target,
    PermissionInterface $permission,
): void;
```

### Batch Operations

#### batchDelegateRoles

Assign multiple roles at once.

```php
public function batchDelegateRoles(
    DelegatableUserInterface $delegator,
    DelegatableUserInterface $target,
    array $roles,
): void;
```

**Parameters:**
- `$roles` - Array of `RoleInterface` instances

---

#### batchRevokeRoles

Remove multiple roles at once.

```php
public function batchRevokeRoles(
    DelegatableUserInterface $delegator,
    DelegatableUserInterface $target,
    array $roles,
): void;
```

---

#### syncRoles

Replace target's roles with new set.

```php
public function syncRoles(
    DelegatableUserInterface $delegator,
    DelegatableUserInterface $target,
    array $roles,
): void;
```

---

#### batchDelegatePermissions

Grant multiple permissions at once.

```php
public function batchDelegatePermissions(
    DelegatableUserInterface $delegator,
    DelegatableUserInterface $target,
    array $permissions,
): void;
```

---

#### batchRevokePermissions

Remove multiple permissions at once.

```php
public function batchRevokePermissions(
    DelegatableUserInterface $delegator,
    DelegatableUserInterface $target,
    array $permissions,
): void;
```

---

#### syncPermissions

Replace target's permissions with new set.

```php
public function syncPermissions(
    DelegatableUserInterface $delegator,
    DelegatableUserInterface $target,
    array $permissions,
): void;
```

### Scope Management

#### setDelegationScope

Configure a user's delegation scope.

```php
public function setDelegationScope(
    DelegatableUserInterface $user,
    DelegationScope $scope,
): void;
```

### Validation

#### validateDelegation

Validate multiple operations before executing.

```php
public function validateDelegation(
    DelegatableUserInterface $delegator,
    DelegatableUserInterface $target,
    array $roles = [],
    array $permissions = [],
): array;
```

**Parameters:**
- `$roles` - Array of role IDs to validate
- `$permissions` - Array of permission IDs to validate

**Returns:** Array of validation errors (empty if valid)

### Cache Management

#### forgetCache

Clear cached delegation data for a user.

```php
public function forgetCache(DelegatableUserInterface $user): void;
```

---

## DelegationScope

Immutable value object representing delegation boundaries.

### Constructor

```php
public function __construct(
    public bool $canManageUsers = false,
    public ?int $maxManageableUsers = null,
    public array $assignableRoleIds = [],
    public array $assignablePermissionIds = [],
);
```

### Factory Methods

#### none

Create scope with no delegation rights.

```php
public static function none(): self;
```

---

#### unlimited

Create scope with unlimited capabilities.

```php
public static function unlimited(array $roleIds = [], array $permissionIds = []): self;
```

---

#### limited

Create scope with quota limit.

```php
public static function limited(int $maxUsers, array $roleIds = [], array $permissionIds = []): self;
```

---

#### builder

Get a fluent builder instance.

```php
public static function builder(): DelegationScopeBuilder;
```

### Query Methods

#### allowsUserManagement

Check if user management is enabled.

```php
public function allowsUserManagement(): bool;
```

---

#### hasUnlimitedUsers

Check if quota is unlimited.

```php
public function hasUnlimitedUsers(): bool;
```

---

#### canAssignRoleId

Check if role ID is assignable.

```php
public function canAssignRoleId(int|string $roleId): bool;
```

---

#### canAssignPermissionId

Check if permission ID is grantable.

```php
public function canAssignPermissionId(int|string $permissionId): bool;
```

### Immutable Modifiers

#### withMaxUsers

Return new scope with different quota.

```php
public function withMaxUsers(?int $max): self;
```

---

#### withRoles

Return new scope with different roles.

```php
public function withRoles(array $roleIds): self;
```

---

#### withPermissions

Return new scope with different permissions.

```php
public function withPermissions(array $permissionIds): self;
```

---

#### withoutUserManagement

Return new scope without user management.

```php
public function withoutUserManagement(): self;
```

### Serialization

#### toArray

Convert to array.

```php
public function toArray(): array;
```

---

#### fromArray

Create from array.

```php
public static function fromArray(array $data): self;
```

### Comparison

#### equals

Check equality with another scope.

```php
public function equals(self $other): bool;
```

---

## DelegationScopeBuilder

Fluent builder for constructing DelegationScope.

```php
DelegationScope::builder()
    ->allowUserManagement()
    ->maxUsers(10)
    ->withRoles([1, 2, 3])
    ->withPermissions([4, 5])
    ->build();
```

### Methods

| Method | Description |
|--------|-------------|
| `create()` | Static constructor |
| `from(DelegationScope $scope)` | Create from existing scope |
| `allowUserManagement()` | Enable user management |
| `denyUserManagement()` | Disable user management |
| `maxUsers(?int $max)` | Set quota |
| `unlimited()` | Remove quota limit |
| `withRoles(array $ids)` | Set assignable roles |
| `withPermissions(array $ids)` | Set assignable permissions |
| `build()` | Create the DelegationScope |

---

## DelegationResult

Immutable result object for delegation operations.

### Factory Methods

```php
DelegationResult::success(string $message);
DelegationResult::failure(string $message, array $errors = []);
DelegationResult::roleAssigned($target, $delegator, $role);
DelegationResult::roleRevoked($target, $delegator, $role);
DelegationResult::permissionGranted($target, $delegator, $permission);
DelegationResult::permissionRevoked($target, $delegator, $permission);
DelegationResult::validationFailed(array $errors);
```

### Query Methods

```php
$result->isSuccess(): bool;
$result->isFailure(): bool;
$result->hasErrors(): bool;
$result->getError(): ?string;
$result->getMessage(): string;
$result->toArray(): array;
```

---

## DelegatableUserInterface

Interface your User model must implement.

```php
interface DelegatableUserInterface
{
    public function getDelegatableIdentifier(): int|string;
    public function canManageUsers(): bool;
    public function getMaxManageableUsers(): ?int;
    public function creator(): BelongsTo;
    public function createdUsers(): HasMany;
    public function assignableRoles(): BelongsToMany;
    public function assignablePermissions(): BelongsToMany;
}
```

---

## RoleInterface

Interface for role entities.

```php
interface RoleInterface
{
    public function getRoleIdentifier(): int|string;
    public function getRoleName(): string;
    public function getRoleGuard(): string;
}
```

---

## PermissionInterface

Interface for permission entities.

```php
interface PermissionInterface
{
    public function getPermissionIdentifier(): int|string;
    public function getPermissionName(): string;
    public function getPermissionGuard(): string;
}
```

---

## Facade Methods

All `DelegationServiceInterface` methods are available via the `Delegation` facade:

```php
use Ordain\Delegation\Facades\Delegation;

Delegation::canAssignRole($delegator, $role, $target);
Delegation::delegateRole($delegator, $target, $role);
Delegation::getAssignableRoles($user);
// ... etc
```

---

## HasDelegation Trait

Methods added to your User model.

### Relationship Methods

```php
$user->creator();              // BelongsTo: who created this user
$user->createdUsers();         // HasMany: users created by this user
$user->assignableRoles();      // BelongsToMany: roles user can assign
$user->assignablePermissions(); // BelongsToMany: permissions user can grant
```

### Helper Methods

```php
$user->canManageUsers(): bool;
$user->getMaxManageableUsers(): ?int;
$user->getDelegatableIdentifier(): int|string;
$user->enableUserManagement(?int $maxUsers = null): void;
$user->disableUserManagement(): void;
```

---

## Exceptions

### DelegationException

Base exception class.

```php
DelegationException::create(string $message, array $context = []);
$exception->getContext(): array;
```

### UnauthorizedDelegationException

Thrown on authorization failures.

```php
UnauthorizedDelegationException::cannotAssignRole(string $roleName);
UnauthorizedDelegationException::cannotManageUser(int|string $userId);
UnauthorizedDelegationException::quotaExceeded(int $limit);

$exception->getAttemptedAction(): ?string;
$exception->getContext(): array;
```

## Next Steps

- [Testing](testing.md) - Test your implementation
- [Troubleshooting](troubleshooting.md) - Common issues