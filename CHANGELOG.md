# Changelog

All notable changes to `ordain/delegation` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.0] - 2026-03-22

### Added
- Multi-guard support via `guard` configuration option
- `syncRoles()` and `syncPermissions()` methods on `DelegationServiceInterface`
- Dedicated `canRevokeRole()` and `canRevokePermission()` authorization pipeline methods
- `RoleAdapterFactoryInterface` and `PermissionAdapterFactoryInterface` for decoupled adapter creation
- `DelegationEventFactoryInterface` for customizable event creation
- `CacheInvalidatorInterface` for abstracted cache invalidation
- `RateLimiterInterface` for customizable rate limiting
- `SpatieRoleAdapterFactory` and `SpatiePermissionAdapterFactory` concrete implementations
- `DelegationEventFactory` concrete implementation
- `LaravelRateLimiterAdapter` concrete implementation

### Changed
- `AuthorizationPipeline` now accepts injectable pipes array instead of hardcoding pipe instantiation
- `TransactionManager` uses injected `ConnectionInterface` instead of `DB` facade
- `DatabaseDelegationAudit` uses injected `ConnectionInterface` instead of `DB` facade
- `LogDelegationAudit` uses injected `LoggerInterface` instead of `Log` facade
- `DelegationService` uses `DelegationEventFactoryInterface` instead of direct event instantiation
- `BladeDirectives` uses constructor-injected services instead of `app()` service locator
- `RateLimitDelegationMiddleware` uses `RateLimiterInterface` instead of concrete `RateLimiter`
- `CachedDelegationService` now implements `CacheInvalidatorInterface`
- Removed `DelegationBladeServiceProvider` from auto-discovery (handled by main provider)

### Fixed
- Blade directives now respect configured auth guard (multi-guard support)
- Revocation methods use dedicated pipeline path instead of reusing assignment pipeline

## [1.0.0] - 2026-03-22

### Added
- Initial release
- Hierarchical permission delegation system
- User creation limits and quotas
- Role and permission delegation
- Super admin bypass functionality
- Audit logging with multiple drivers (database, log, null)
- Built-in caching with cache invalidation
- Domain events for all delegation actions
- Artisan commands (`delegation:show`, `delegation:assign-role`, `delegation:cache-reset`)
- Route middleware (`CanDelegateMiddleware`, `CanAssignRoleMiddleware`, `CanManageUserMiddleware`)
- Facade for easy access
- Full test coverage
- Laravel 11.x and 12.x support
- PHP 8.2, 8.3, and 8.4 support
- Octane compatibility