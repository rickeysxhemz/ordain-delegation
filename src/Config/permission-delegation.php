<?php

declare(strict_types=1);

return [
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
    | Super Admin Configuration
    |--------------------------------------------------------------------------
    |
    | Configure super admin bypass behavior. Super admins can bypass all
    | delegation restrictions.
    |
    */
    'super_admin' => [
        /*
        | Enable/disable super admin bypass
        */
        'enabled' => env('DELEGATION_SUPER_ADMIN_BYPASS', true),

        /*
        | The role name/identifier that grants super admin privileges.
        | Users with this role bypass all delegation checks.
        */
        'role' => env('DELEGATION_SUPER_ADMIN_ROLE', 'super-admin'),
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
];
