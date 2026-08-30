# Changelog

All notable changes to `ordain/delegation` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.0.0] - 2026-08-31

### Added
- Laravel 13.x support (`illuminate/*` constraints are now `^12.0|^13.0`)
- `orchestra/testbench` `^11.0` for testing against Laravel 13
- CI matrix coverage for Laravel 13 and PHP 8.5
- `composer-runtime-api` `^2.2` requirement, for resolving the installed package version at runtime
- Octane compatibility tests that simulate a worker request boundary (`forgetScopedInstances()`), covering
  directive resolution and per-request audit context

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
- **BREAKING:** `BladeDirectives::__construct()` no longer takes `DelegationServiceInterface` and
  `RoleRepositoryInterface`; both are resolved per evaluation through the protected `delegationService()`
  and `roleRepository()` methods, which subclasses may override. Container-resolved usage — which is how
  both service providers register it — is unaffected.

### Removed
- **BREAKING:** Laravel 11.x support. `illuminate/*` is now `^12.0|^13.0` and `orchestra/testbench` is
  `^10.0|^11.0`; the CI matrix drops its `11.*` rows.

  Laravel 11 is no longer installable. Composer 2.9+ enables `policy.advisories.block` by default, and
  three unresolved advisories affect `laravel/framework` — PKSA-3r5d-mb8f-1qw9 (high, CRLF injection in
  the default email rule), PKSA-mdq4-51ck-6kdq (CVE-2026-48019, same issue) and PKSA-m5cs-t1y6-qpcs
  (medium, temporary signed URL path confusion). They affect every release from v11.0.0 through v11.56.1,
  so there is no patched 11.x to move to; Laravel 12 received the fixes, Laravel 11 did not. Keeping the
  constraint would advertise a version that a default Composer install refuses to resolve, and the only
  workaround is disabling advisory blocking — opting into a knowingly vulnerable framework.

  The package code itself remains compatible with Laravel 11: the suite passes 540 tests and PHPStan is
  clean against v11.56.1. The removal is about installability, not source compatibility.

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
- Blade directives served results from a stale delegation service under Octane. `Blade::if()` closures are
  registered once per worker and closed over the `DelegationServiceInterface` and `RoleRepositoryInterface`
  injected at boot. Both are scoped bindings that Octane flushes at every request boundary, and Octane serves
  each request from a *clone* of the booted application — so the directives evaluated against services
  belonging to worker boot rather than to the request. They now resolve through `app()` on each evaluation,
  which reads the container Octane makes current per request; caching a container reference would have kept
  the base application and reintroduced the same defect.

  The authenticated user was already resolved per evaluation, so this did not leak one user's permissions to
  another. Every other binding in the package is `scoped`, and Octane's `FlushTemporaryContainerInstances`
  listener clears those at `RequestReceived`, so the rest of the package was already correct — including the
  audit context, which is rebuilt per request.
- Stale `phpstan.neon.dist` ignore pattern for `Mockery\ExpectationInterface|Mockery\HigherOrderMessage` no longer
  fails static analysis under larastan 3.10 / mockery 1.6.15 via `reportUnmatchedIgnoredErrors`
- Removed no-op global-namespace `use` statements from Pest test files that emitted warnings on PHP 8.5

## [1.1.2] - 2026-03-22

### Added
- Support for multiple root admin roles — `root_admin.role` accepts an array as well as a string
- `guard` recorded on audit entries: a new `AuditContext::$guard` property, written to a new nullable
  `guard` column on `delegation_audit_logs`

### Changed
- **BREAKING:** `RootAdminResolver::__construct()` takes `array $roleIdentifiers` in place of
  `?string $roleIdentifier`. Container-resolved usage is unaffected; manual construction must pass an array.
- **BREAKING:** `DelegationScope` rejects a `maxManageableUsers` below `1`. A quota of `0` previously
  validated and now throws — use `null` for unlimited.
- `delegation_audit_logs` gains a nullable `guard` column, so the migrations must be re-published and run
  when upgrading
- `CanDelegateMiddleware`, `CanAssignRoleMiddleware`, `CanManageUserMiddleware` and
  `RateLimitDelegationMiddleware` resolve the user through the configured guard instead of the default one
- `AuditContext::fromRequest()` accepts the guard as a second argument
- `CachedDelegationService` defaults `$guardName` to `''` rather than `'web'`, so cache keys reflect the
  guard that is actually configured
- `BladeDirectives` is no longer `final` and its methods are `protected`, allowing the directives to be
  extended

### Fixed
- Blade directives return `false` instead of surfacing a `Throwable` when evaluated outside a resolvable
  auth context

## [1.1.1] - 2026-03-22

### Added
- `root_admin.guard` configuration option (`DELEGATION_ROOT_ADMIN_GUARD`), passed through to the role
  lookup so root admin detection can target a specific guard
- `docs/customization.md` covering the package's extension points, plus expanded `docs/api-reference.md`,
  `docs/blade-and-routes.md`, `docs/configuration.md`, `docs/events.md` and `docs/middleware.md`
- Changelog entry for 1.1.0, which was tagged without one

### Changed
- `RootAdminResolver::__construct()` accepts an optional `?string $guard`

### Removed
- Infection mutation testing — the `mutation-testing.yml` workflow and the
  `infection/extension-installer` plugin allowance in `composer.json`

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