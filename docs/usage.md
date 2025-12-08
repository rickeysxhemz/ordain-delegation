# Usage

## Dependency Injection (Recommended)

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
            abort(403, 'You cannot assign this role.');
        }

        $this->delegation->delegateRole($delegator, $target, $role);

        return response()->json(['message' => 'Role assigned successfully.']);
    }
}
```

## Using the Facade

```php
use Ordain\Delegation\Facades\Delegation;

if (Delegation::canAssignRole($delegator, $role, $target)) {
    Delegation::delegateRole($delegator, $target, $role);
}

$roles = Delegation::getAssignableRoles($delegator);
```

## Delegation Scope

The `DelegationScope` value object defines what a user can delegate.

### Creating a Scope

```php
use Ordain\Delegation\Domain\ValueObjects\DelegationScope;

$scope = new DelegationScope(
    canManageUsers: true,
    maxManageableUsers: 10,
    assignableRoleIds: [1, 2, 3],
    assignablePermissionIds: [4, 5, 6],
);

$delegation->setDelegationScope($user, $scope);
```

### Factory Methods

```php
$scope = DelegationScope::none();
$scope = DelegationScope::unlimited([1, 2]);
```

### Immutable Modifications

```php
$newScope = $scope->withMaxUsers(20);
```

## Checking Permissions

```php
$delegation->canAssignRole($delegator, $role, $target);
$delegation->canAssignPermission($delegator, $permission, $target);
$delegation->canRevokeRole($delegator, $role, $target);
$delegation->canRevokePermission($delegator, $permission, $target);
$delegation->canCreateUsers($delegator);
$delegation->canManageUser($delegator, $target);
$delegation->hasReachedUserLimit($delegator);
```

## Quota Management

```php
$count = $delegation->getCreatedUsersCount($delegator);
$remaining = $delegation->getRemainingUserQuota($delegator);
```

## Getting Assignable Items

```php
$roles = $delegation->getAssignableRoles($delegator);
$permissions = $delegation->getAssignablePermissions($delegator);
```

## Delegation Operations

All operations include validation and audit logging.

```php
$delegation->delegateRole($delegator, $target, $role);
$delegation->delegatePermission($delegator, $target, $permission);
$delegation->revokeRole($delegator, $target, $role);
$delegation->revokePermission($delegator, $target, $permission);
```

## Validation

Validate before executing multiple operations:

```php
$errors = $delegation->validateDelegation(
    $delegator,
    $target,
    roles: [1, 2],
    permissions: [3, 4],
);

if (empty($errors)) {
    // Proceed with delegation
}
```

## Trait Methods

The `HasDelegation` trait provides convenient methods on the User model:

```php
$user->enableUserManagement(maxUsers: 10);
$user->disableUserManagement();

$user->canManageUsers();
$user->getMaxManageableUsers();
$user->canAssignRole($role);
$user->canAssignPermission($permission);

$user->createdUsers;
$user->assignableRoles;
$user->assignablePermissions;
```

## Blade Directives

```blade
@canDelegate
    <a href="/users/create">Create User</a>
@endcanDelegate

@canAssignRole('editor')
    <button>Assign Editor Role</button>
@endcanAssignRole

@canManageUser($targetUser)
    <a href="/users/{{ $targetUser->id }}/edit">Edit</a>
@endcanManageUser
```