# Middleware

Route protection middleware for delegation authorization.

## Available Middleware

| Class | Alias | Purpose |
|-------|-------|---------|
| `CanDelegateMiddleware` | `can.delegate` | User can create/manage users |
| `CanAssignRoleMiddleware` | `can.assign.role` | User can assign specific roles |
| `CanManageUserMiddleware` | `can.manage.user` | User can manage target user |
| `RateLimitDelegationMiddleware` | `delegation.throttle` | Rate limit delegation operations |

## Automatic Registration

Middleware aliases are registered automatically by the service provider. No manual registration is required in most cases.

### Manual Registration (if needed)

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'can.delegate' => \Ordain\Delegation\Http\Middleware\CanDelegateMiddleware::class,
        'can.assign.role' => \Ordain\Delegation\Http\Middleware\CanAssignRoleMiddleware::class,
        'can.manage.user' => \Ordain\Delegation\Http\Middleware\CanManageUserMiddleware::class,
        'delegation.throttle' => \Ordain\Delegation\Http\Middleware\RateLimitDelegationMiddleware::class,
    ]);
})
```

## CanDelegateMiddleware

Checks if the authenticated user has the ability to create and manage users.

### Basic Usage

```php
Route::middleware('can.delegate')->group(function () {
    Route::get('/team', [TeamController::class, 'index']);
    Route::post('/team', [TeamController::class, 'store']);
});
```

### Using Class Reference

```php
use Ordain\Delegation\Http\Middleware\CanDelegateMiddleware;

Route::middleware(CanDelegateMiddleware::class)->group(function () {
    Route::resource('users', UserController::class);
});
```

### What It Checks

1. User is authenticated
2. User model implements `DelegatableUserInterface`
3. User has `can_manage_users = true`

## CanAssignRoleMiddleware

Checks if the user can assign specific roles.

### Single Role

```php
Route::middleware('can.assign.role:editor')
    ->post('/users/{user}/roles', [RoleController::class, 'store']);
```

### Multiple Roles

User must be able to assign **all** specified roles:

```php
Route::middleware('can.assign.role:admin,manager,editor')
    ->post('/users/{user}/promote', [PromotionController::class, 'store']);
```

### Using Class Reference

```php
use Ordain\Delegation\Http\Middleware\CanAssignRoleMiddleware;

Route::post('/users/{user}/roles', [RoleController::class, 'store'])
    ->middleware(CanAssignRoleMiddleware::class . ':editor,moderator');
```

### What It Checks

1. User is authenticated
2. User model implements `DelegatableUserInterface`
3. All specified roles exist
4. All specified roles are in user's `assignableRoles`

## CanManageUserMiddleware

Checks if the user can manage a specific target user from route parameters.

### Default Parameter

By default, looks for `user` route parameter:

```php
Route::middleware('can.manage.user')->group(function () {
    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);
});
```

### Custom Parameter Name

Specify a different route parameter:

```php
Route::middleware('can.manage.user:member')
    ->put('/members/{member}', [MemberController::class, 'update']);

Route::middleware('can.manage.user:target')
    ->post('/transfer/{target}', [TransferController::class, 'store']);
```

### What It Checks

1. User is authenticated
2. Both user models implement `DelegatableUserInterface`
3. Target user exists (returns 404 if not)
4. Delegator created the target user OR is root admin

## RateLimitDelegationMiddleware

Rate limits delegation operations to prevent abuse.

### Basic Usage

```php
Route::middleware('delegation.throttle')->group(function () {
    Route::post('/users/{user}/roles', [RoleController::class, 'store']);
    Route::delete('/users/{user}/roles/{role}', [RoleController::class, 'destroy']);
});
```

### Configuration

Configure in `config/permission-delegation.php`:

```php
'rate_limiting' => [
    'enabled' => true,
    'max_attempts' => 60,    // Requests per period
    'decay_minutes' => 1,     // Period in minutes
],
```

### What It Does

- Tracks requests per user
- Returns `429 Too Many Requests` when limit exceeded
- Includes `Retry-After` header

## Combining Middleware

Stack multiple middleware for comprehensive protection:

```php
Route::middleware(['auth', 'can.delegate', 'can.assign.role:editor', 'delegation.throttle'])
    ->post('/users/{user}/roles', [RoleController::class, 'store']);
```

### Recommended Combinations

```php
// User management routes
Route::middleware(['auth', 'can.delegate'])->group(function () {
    Route::get('/team', [TeamController::class, 'index']);
    Route::post('/team', [TeamController::class, 'store']);
});

// Role assignment routes
Route::middleware(['auth', 'can.delegate', 'can.manage.user'])->group(function () {
    Route::post('/users/{user}/roles', [RoleController::class, 'store']);
    Route::delete('/users/{user}/roles/{role}', [RoleController::class, 'destroy']);
});

// Specific role assignment
Route::middleware(['auth', 'can.assign.role:admin'])
    ->post('/users/{user}/make-admin', [AdminController::class, 'promote']);
```

## Error Responses

### HTTP Status Codes

| Code | Condition |
|------|-----------|
| 401 | User not authenticated |
| 403 | User model doesn't implement `DelegatableUserInterface` |
| 403 | User not authorized for the operation |
| 404 | Role not found (`CanAssignRoleMiddleware`) |
| 404 | Target user not found (`CanManageUserMiddleware`) |
| 429 | Rate limit exceeded (`RateLimitDelegationMiddleware`) |

### JSON Response Format

For API requests (expecting JSON):

```json
{
    "message": "You are not authorized to assign this role."
}
```

### Customizing Responses

Create custom middleware that extends the package middleware:

```php
<?php

namespace App\Http\Middleware;

use Ordain\Delegation\Http\Middleware\CanDelegateMiddleware as BaseMiddleware;
use Illuminate\Http\Request;

class CanDelegateMiddleware extends BaseMiddleware
{
    protected function unauthorized(Request $request): never
    {
        if ($request->expectsJson()) {
            abort(response()->json([
                'error' => 'delegation_required',
                'message' => 'You need delegation privileges to access this resource.',
            ], 403));
        }

        abort(403, 'Delegation privileges required.');
    }
}
```

## Controller-Based Authorization

Alternative to middleware - authorize in controllers:

```php
use Ordain\Delegation\Facades\Delegation;

class RoleController extends Controller
{
    public function store(Request $request, User $user)
    {
        $delegator = $request->user();

        // Manual authorization check
        if (! Delegation::canManageUser($delegator, $user)) {
            abort(403, 'You cannot manage this user.');
        }

        $role = Role::findByName($request->role);

        if (! Delegation::canAssignRole($delegator, $role, $user)) {
            abort(403, 'You cannot assign this role.');
        }

        Delegation::delegateRole($delegator, $user, $role);

        return response()->json(['message' => 'Role assigned.']);
    }
}
```

## Policy Integration

Combine with Laravel policies:

```php
// app/Policies/UserPolicy.php
class UserPolicy
{
    public function manageRoles(User $delegator, User $target): bool
    {
        return Delegation::canManageUser($delegator, $target);
    }

    public function assignRole(User $delegator, User $target, Role $role): bool
    {
        return Delegation::canAssignRole($delegator, $role, $target);
    }
}

// In controller
public function store(Request $request, User $user)
{
    $this->authorize('manageRoles', $user);

    $role = Role::findByName($request->role);
    $this->authorize('assignRole', [$user, $role]);

    // Proceed with assignment
}
```

## Next Steps

- [Blade & Routes](blade-and-routes.md) - View helpers and route macros
- [Events](events.md) - React to delegation actions
- [API Reference](api-reference.md) - Complete method reference