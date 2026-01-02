# Blade Directives & Route Macros

Convenient helpers for views and routing.

## Blade Directives

### Enabling Directives

Blade directives are enabled by default. To disable:

```php
// config/permission-delegation.php
'features' => [
    'blade_directives' => false,
],
```

### @canDelegate

Check if the current user can create and manage users:

```blade
@canDelegate
    <a href="{{ route('users.create') }}" class="btn btn-primary">
        Create User
    </a>
@endCanDelegate
```

With else clause:

```blade
@canDelegate
    <a href="{{ route('team.index') }}">Manage Team</a>
@else
    <span class="text-muted">Team management not available</span>
@endCanDelegate
```

### @canAssignRole

Check if the current user can assign a specific role:

```blade
<select name="role">
    @canAssignRole('admin')
        <option value="admin">Administrator</option>
    @endCanAssignRole

    @canAssignRole('manager')
        <option value="manager">Manager</option>
    @endCanAssignRole

    @canAssignRole('editor')
        <option value="editor">Editor</option>
    @endCanAssignRole
</select>
```

Dynamic role check:

```blade
@foreach($roles as $role)
    @canAssignRole($role->name)
        <option value="{{ $role->id }}">{{ $role->name }}</option>
    @endCanAssignRole
@endforeach
```

### @canManageUser

Check if the current user can manage a specific target user:

```blade
@canManageUser($user)
    <div class="dropdown">
        <button class="btn btn-secondary dropdown-toggle" type="button">
            Actions
        </button>
        <ul class="dropdown-menu">
            <li>
                <a href="{{ route('users.edit', $user) }}">Edit</a>
            </li>
            <li>
                <a href="{{ route('users.roles', $user) }}">Manage Roles</a>
            </li>
            <li>
                <form action="{{ route('users.destroy', $user) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit">Delete</button>
                </form>
            </li>
        </ul>
    </div>
@endCanManageUser
```

### Combining Directives

```blade
@canDelegate
    <div class="team-management">
        <h2>Your Team</h2>

        @foreach($teamMembers as $member)
            <div class="team-member">
                <span>{{ $member->name }}</span>

                @canManageUser($member)
                    <div class="actions">
                        <a href="{{ route('users.edit', $member) }}">Edit</a>

                        @canAssignRole('admin')
                            <button onclick="promoteToAdmin({{ $member->id }})">
                                Make Admin
                            </button>
                        @endCanAssignRole
                    </div>
                @endCanManageUser
            </div>
        @endforeach
    </div>
@endCanDelegate
```

### Usage in Components

```blade
{{-- resources/views/components/user-actions.blade.php --}}
@props(['user'])

@canManageUser($user)
    <x-dropdown>
        <x-slot name="trigger">
            <x-button>Actions</x-button>
        </x-slot>

        <x-dropdown-link :href="route('users.edit', $user)">
            Edit
        </x-dropdown-link>

        @canAssignRole('admin')
            <x-dropdown-link :href="route('users.promote', $user)">
                Promote to Admin
            </x-dropdown-link>
        @endCanAssignRole
    </x-dropdown>
@endCanManageUser
```

## Route Macros

### Enabling Macros

Route macros are enabled by default. To disable:

```php
// config/permission-delegation.php
'features' => [
    'route_macros' => false,
],
```

### canDelegate()

Apply `CanDelegateMiddleware` to a route:

```php
// Single route
Route::post('/users', [UserController::class, 'store'])
    ->canDelegate();

// Route group
Route::canDelegate()->group(function () {
    Route::get('/team', [TeamController::class, 'index']);
    Route::post('/team', [TeamController::class, 'store']);
    Route::get('/team/stats', [TeamController::class, 'stats']);
});
```

### canAssignRole()

Apply `CanAssignRoleMiddleware` to a route:

```php
// Single role
Route::post('/users/{user}/roles', [RoleController::class, 'store'])
    ->canAssignRole('editor');

// Multiple roles (user must be able to assign ALL)
Route::post('/users/{user}/roles', [RoleController::class, 'store'])
    ->canAssignRole(['editor', 'moderator']);

// Different roles for different routes
Route::post('/users/{user}/make-editor', [RoleController::class, 'makeEditor'])
    ->canAssignRole('editor');

Route::post('/users/{user}/make-admin', [RoleController::class, 'makeAdmin'])
    ->canAssignRole('admin');
```

### canManageUser()

Apply `CanManageUserMiddleware` to a route:

```php
// Default parameter name: 'user'
Route::put('/users/{user}', [UserController::class, 'update'])
    ->canManageUser();

Route::delete('/users/{user}', [UserController::class, 'destroy'])
    ->canManageUser();

// Custom parameter name
Route::put('/members/{member}', [MemberController::class, 'update'])
    ->canManageUser('member');

Route::post('/transfer/{target}', [TransferController::class, 'store'])
    ->canManageUser('target');
```

### Chaining Macros

```php
Route::post('/users/{user}/roles', [RoleController::class, 'store'])
    ->canDelegate()
    ->canManageUser()
    ->canAssignRole(['editor', 'moderator']);
```

### With Other Middleware

```php
Route::middleware(['auth', 'verified'])
    ->canDelegate()
    ->group(function () {
        Route::resource('users', UserController::class);
    });
```

## Complete Examples

### User Management Routes

```php
// routes/web.php

use App\Http\Controllers\TeamController;
use App\Http\Controllers\RoleController;

// Team management (requires delegation capability)
Route::middleware('auth')->canDelegate()->group(function () {
    Route::get('/team', [TeamController::class, 'index'])->name('team.index');
    Route::get('/team/create', [TeamController::class, 'create'])->name('team.create');
    Route::post('/team', [TeamController::class, 'store'])->name('team.store');
});

// Individual user management
Route::middleware('auth')->group(function () {
    Route::get('/team/{user}', [TeamController::class, 'show'])
        ->canManageUser()
        ->name('team.show');

    Route::get('/team/{user}/edit', [TeamController::class, 'edit'])
        ->canManageUser()
        ->name('team.edit');

    Route::put('/team/{user}', [TeamController::class, 'update'])
        ->canManageUser()
        ->name('team.update');

    Route::delete('/team/{user}', [TeamController::class, 'destroy'])
        ->canManageUser()
        ->name('team.destroy');
});

// Role management
Route::middleware('auth')->group(function () {
    Route::get('/team/{user}/roles', [RoleController::class, 'index'])
        ->canManageUser()
        ->name('roles.index');

    Route::post('/team/{user}/roles', [RoleController::class, 'store'])
        ->canManageUser()
        ->name('roles.store');

    Route::delete('/team/{user}/roles/{role}', [RoleController::class, 'destroy'])
        ->canManageUser()
        ->name('roles.destroy');
});
```

### Team Dashboard View

```blade
{{-- resources/views/team/index.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Team Management</h1>

        @canDelegate
            <a href="{{ route('team.create') }}" class="btn btn-primary">
                Add Team Member
            </a>
        @endCanDelegate
    </div>

    @if($remainingQuota !== null)
        <div class="alert alert-info">
            You can create {{ $remainingQuota }} more team members.
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Roles</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($teamMembers as $member)
                        <tr>
                            <td>{{ $member->name }}</td>
                            <td>{{ $member->email }}</td>
                            <td>
                                @foreach($member->roles as $role)
                                    <span class="badge bg-secondary">{{ $role->name }}</span>
                                @endforeach
                            </td>
                            <td>
                                @canManageUser($member)
                                    <div class="btn-group">
                                        <a href="{{ route('team.edit', $member) }}"
                                           class="btn btn-sm btn-outline-primary">
                                            Edit
                                        </a>
                                        <a href="{{ route('roles.index', $member) }}"
                                           class="btn btn-sm btn-outline-secondary">
                                            Roles
                                        </a>
                                        <form action="{{ route('team.destroy', $member) }}"
                                              method="POST"
                                              class="d-inline"
                                              onsubmit="return confirm('Are you sure?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                @else
                                    <span class="text-muted">No actions available</span>
                                @endCanManageUser
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
```

### Role Assignment Form

```blade
{{-- resources/views/roles/create.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Assign Role to {{ $user->name }}</h1>

    <form action="{{ route('roles.store', $user) }}" method="POST">
        @csrf

        <div class="mb-3">
            <label for="role" class="form-label">Select Role</label>
            <select name="role" id="role" class="form-select" required>
                <option value="">Choose a role...</option>

                @foreach($availableRoles as $role)
                    @canAssignRole($role->name)
                        <option value="{{ $role->name }}">
                            {{ $role->name }}
                        </option>
                    @endCanAssignRole
                @endforeach
            </select>
        </div>

        <button type="submit" class="btn btn-primary">Assign Role</button>
        <a href="{{ route('team.show', $user) }}" class="btn btn-secondary">Cancel</a>
    </form>
</div>
@endsection
```

## Next Steps

- [Events](events.md) - React to delegation actions
- [Commands](commands.md) - Artisan console commands
- [API Reference](api-reference.md) - Complete method reference