# Changelog

All notable changes to `ordain/delegation` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - YYYY-MM-DD

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