<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Run Migrations
    |--------------------------------------------------------------------------
    |
    | When enabled, the package will automatically load its migrations.
    | Disable this if you want to publish and customize migrations.
    |
    */
    'run_migrations' => env('DELEGATION_RUN_MIGRATIONS', true),

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The fully qualified class name of your User model that implements
    | DelegatableUserInterface and uses the HasDelegation trait.
    |
    */
    'user_model' => env('DELEGATION_USER_MODEL', 'App\\Models\\User'),

    /*
    |--------------------------------------------------------------------------
    | Role Model
    |--------------------------------------------------------------------------
    |
    | The fully qualified class name of your Role model. Defaults to Spatie's
    | Role model. Your role model should implement RoleInterface.
    |
    */
    'role_model' => env('DELEGATION_ROLE_MODEL', 'Spatie\\Permission\\Models\\Role'),

    /*
    |--------------------------------------------------------------------------
    | Permission Model
    |--------------------------------------------------------------------------
    |
    | The fully qualified class name of your Permission model. Defaults to
    | Spatie's Permission model. Your permission model should implement
    | PermissionInterface.
    |
    */
    'permission_model' => env('DELEGATION_PERMISSION_MODEL', 'Spatie\\Permission\\Models\\Permission'),

    /*
    |--------------------------------------------------------------------------
    | Database Tables
    |--------------------------------------------------------------------------
    |
    | The names of the database tables used by this package.
    |
    */
    'tables' => [
        'user_assignable_roles' => 'user_assignable_roles',
        'user_assignable_permissions' => 'user_assignable_permissions',
        'delegation_audit_logs' => 'delegation_audit_logs',
    ],

    /*
    |--------------------------------------------------------------------------
    | Root Administrator
    |--------------------------------------------------------------------------
    |
    | Users with this role bypass all delegation checks.
    |
    */
    'root_admin' => [
        'enabled' => env('DELEGATION_ROOT_ADMIN_BYPASS', true),
        'role' => env('DELEGATION_ROOT_ADMIN_ROLE', 'root-admin'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Logging
    |--------------------------------------------------------------------------
    |
    | Configure audit logging for delegation operations.
    |
    */
    'audit' => [
        /*
        | Enable/disable audit logging
        */
        'enabled' => env('DELEGATION_AUDIT_ENABLED', true),

        /*
        | The driver to use for audit logging.
        | Supported: "database", "log", "null", or a custom class
        */
        'driver' => env('DELEGATION_AUDIT_DRIVER', 'database'),

        /*
        | Log channel to use when driver is "log"
        */
        'log_channel' => env('DELEGATION_AUDIT_LOG_CHANNEL', 'stack'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    |
    | Configure domain event dispatching for delegation operations.
    |
    */
    'events' => [
        /*
        | Enable/disable event dispatching for delegation operations.
        | When enabled, events like RoleDelegated, PermissionGranted, etc.
        | will be dispatched.
        */
        'enabled' => env('DELEGATION_EVENTS_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Configure caching for delegation checks.
    |
    */
    'cache' => [
        /*
        | Enable/disable caching of delegation scopes
        */
        'enabled' => env('DELEGATION_CACHE_ENABLED', true),

        /*
        | Cache TTL in seconds
        */
        'ttl' => env('DELEGATION_CACHE_TTL', 3600),

        /*
        | Cache key prefix
        */
        'prefix' => 'delegation_',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Delegation Settings
    |--------------------------------------------------------------------------
    |
    | Default settings for new users.
    |
    */
    'defaults' => [
        /*
        | Whether new users can manage other users by default
        */
        'can_manage_users' => false,

        /*
        | Default max manageable users (null = unlimited when enabled)
        */
        'max_manageable_users' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting for delegation operations.
    |
    */
    'rate_limiting' => [
        /*
        | Enable/disable rate limiting for delegation operations
        */
        'enabled' => env('DELEGATION_RATE_LIMIT_ENABLED', true),

        /*
        | Maximum attempts per minute
        */
        'max_attempts' => env('DELEGATION_RATE_LIMIT_MAX', 60),

        /*
        | Decay time in minutes
        */
        'decay_minutes' => env('DELEGATION_RATE_LIMIT_DECAY', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    |
    | Additional validation rules for delegation operations.
    |
    */
    'validation' => [
        /*
        | Prevent users from assigning roles/permissions they don't have
        */
        'require_own_access' => env('DELEGATION_REQUIRE_OWN_ACCESS', false),

        /*
        | Prevent users from managing users with higher-level roles
        */
        'prevent_privilege_escalation' => env('DELEGATION_PREVENT_ESCALATION', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Toggles
    |--------------------------------------------------------------------------
    |
    | Enable or disable optional features of the delegation package.
    |
    */
    'features' => [
        /*
        | Enable Blade directives (@canDelegate, @canAssignRole, etc.)
        */
        'blade_directives' => env('DELEGATION_BLADE_DIRECTIVES', true),

        /*
        | Enable route middleware (can.delegate, can.assign.role, etc.)
        */
        'route_middleware' => env('DELEGATION_ROUTE_MIDDLEWARE', true),

        /*
        | Enable route macros (->canDelegate(), ->canAssignRole(), etc.)
        */
        'route_macros' => env('DELEGATION_ROUTE_MACROS', true),
    ],
];
