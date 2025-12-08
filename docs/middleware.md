# Middleware

The package provides three middleware for route protection.

## Available Middleware

| Alias | Class | Purpose |
|-------|-------|---------|
| `can.delegate` | `CanDelegateMiddleware` | Check if user can manage other users |
| `can.assign.role` | `CanAssignRoleMiddleware` | Check if user can assign specific roles |
| `can.manage.user` | `CanManageUserMiddleware` | Check if user can manage a target user |

## Registration

Middleware aliases are registered automatically by the service provider. If you need to register them manually:

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'can.delegate' => \Ordain\Delegation\Http\Middleware\CanDelegateMiddleware::class,
        'can.assign.role' => \Ordain\Delegation\Http\Middleware\CanAssignRoleMiddleware::class,
        'can.manage.user' => \Ordain\Delegation\Http\Middleware\CanManageUserMiddleware::class,
    ]);
})
```

## CanDelegateMiddleware

Checks if the authenticated user has delegation abilities.

```php
Route::middleware('can.delegate')->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
});
```

## CanAssignRoleMiddleware

Checks if the user can assign specific roles. Accepts role names as parameters.

```php
// Single role
Route::middleware('can.assign.role:editor')
    ->post('/users/{user}/roles', [RoleController::class, 'store']);

// Multiple roles (user must be able to assign ALL specified roles)
Route::middleware('can.assign.role:admin,manager')
    ->post('/users/{user}/promote', [RoleController::class, 'promote']);
```

## CanManageUserMiddleware

Checks if the user can manage a target user resolved from route parameters.

```php
// Default: resolves user from 'user' route parameter
Route::middleware('can.manage.user')->group(function () {
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);
});

// Custom parameter name
Route::middleware('can.manage.user:target')
    ->put('/transfer/{target}', [TransferController::class, 'store']);
```

## Error Responses

All middleware abort with appropriate HTTP status codes:

| Code | Condition |
|------|-----------|
| 403 | User model doesn't support delegation |
| 403 | User not authorized for the action |
| 404 | Role not found (CanAssignRoleMiddleware) |
| 404 | Target user not found (CanManageUserMiddleware) |