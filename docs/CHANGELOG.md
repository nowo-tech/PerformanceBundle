# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **Role-based access control for performance dashboard** - Added `roles` configuration option to restrict access to the performance dashboard. Users must have at least one of the configured roles to access the dashboard. If no roles are configured (empty array), access is unrestricted.
  - New configuration option: `nowo_performance.dashboard.roles` (array, default: `[]`)
  - Access control is enforced in `PerformanceController::index()` method
  - Supports multiple roles with OR logic (user needs at least one role)
- **QueryTrackingMiddleware for DBAL 3.x compatibility** - Implemented a custom DBAL middleware (`QueryTrackingMiddleware`) to intercept and track database queries. This middleware is compatible with DBAL 3.x (which removed `SQLLogger`) and provides reliable query tracking.
  - Automatic query interception via DBAL middleware
  - Static methods for query count and execution time tracking
  - Multiple fallback strategies for query metrics collection
  - Support for Doctrine DataCollector as fallback

### Changed
- **Improved query metrics collection** - Enhanced `getQueryMetrics()` method with multiple strategies:
  1. QueryTrackingMiddleware (primary method)
  2. Doctrine DataCollector from profiler (fallback)
  3. Request attributes (fallback)
  4. Stopwatch (time only, fallback)
- **Command option shortcut fix** - Removed conflicting shortcut `-q` from `queries` option in `nowo:performance:set-route` command to avoid conflicts

### Deprecated
- None

### Removed
- None

### Fixed
- **Query tracking compatibility** - Fixed query tracking to work correctly with DBAL 3.x by implementing a custom middleware instead of relying on deprecated `SQLLogger`
- **Connection interface compatibility** - Fixed `QueryTrackingConnection` to properly implement all required methods from `Doctrine\DBAL\Driver\Connection` interface
- **Server version method signature** - Fixed `getServerVersion()` return type to match `ServerVersionProvider` interface requirements

### Security
- **Dashboard access control** - The performance dashboard now supports role-based access control to restrict access to authorized users only.

## [0.0.1] - 2025-01-XX

### Added
- Initial release
- Automatic route performance tracking via event subscribers
- Database query counting and execution time tracking
- Request execution time measurement
- Route data persistence in database
- Environment-specific metrics (dev, test, prod)
- Configurable route ignore list
- Command to manually set/update route metrics (`nowo:performance:set-route`)
- Support for multiple Doctrine connections
- Configurable table name for storing metrics
- Performance dashboard with filtering and sorting capabilities
- WebProfiler integration with PerformanceDataCollector
- Symfony 6.1+, 7.x, and 8.x compatibility
- Comprehensive test coverage
- Full documentation
