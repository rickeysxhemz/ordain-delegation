# Changelog

All notable changes to `ordain/delegation` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Laravel 13.x support (`illuminate/*` constraints widened to `^11.0|^12.0|^13.0`)
- `orchestra/testbench` `^11.0` for testing against Laravel 13
- CI matrix coverage for Laravel 13 and PHP 8.5
- `composer-runtime-api` `^2.2` requirement, for resolving the installed package version at runtime

### Changed
- **BREAKING (internal):** `CachedDelegationService::__construct()` now requires `RoleRepositoryInterface $roleRepository`
  and `PermissionRepositoryInterface $permissionRepository`, injected before `$ttl`. Code that resolves the service
  from the container is unaffected; code that constructs it manually must pass the two new arguments.
- `CachedDelegationService` stores only scalars and arrays in the cache — role/permission identifiers instead of
  adapter objects, and `DelegationScope::toArray()` instead of the value object. Cached entries are rehydrated via
  the repositories and `DelegationScope::fromArray()`. This keeps cached values readable under Laravel 13's
  `cache.serializable_classes` hardening, which blocks unserializing arbitrary PHP objects by default.
- Cache entries written by earlier versions are ignored rather than trusted; the affected key is recomputed and
  rewritten in the new format on first read, so no cache flush is required when upgrading.

### Fixed
- `delegation:cache-reset --all` cleared nothing. It built cache keys by hand using the long type names
  (`assignable_roles`) and omitted the guard segment, while `CachedDelegationService` writes short names
  (`aroles`) prefixed with the guard — so no key ever matched. The taggable-store fast path was equally
  inert, flushing a `delegation` tag namespace the service never wrote into. Both paths now delegate to
  `CacheInvalidatorInterface::forgetUserCache()`, the single source of truth for key layout.
  `delegation:cache-reset {user}` was unaffected, having already called that method, but reported a
  misleading `Keys cleared: 0`.
- `php artisan about` reported a hardcoded `Version` of `1.0.0`. It now resolves the installed version
  from Composer's runtime metadata via `Composer\InstalledVersions`.
- Stale `phpstan.neon.dist` ignore pattern for `Mockery\ExpectationInterface|Mockery\HigherOrderMessage` no longer
  fails static analysis under larastan 3.10 / mockery 1.6.15 via `reportUnmatchedIgnoredErrors`
- Removed no-op global-namespace `use` statements from Pest test files that emitted warnings on PHP 8.5

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