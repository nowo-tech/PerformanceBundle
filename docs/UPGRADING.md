# Upgrading Guide

This guide helps you upgrade between versions of the Performance Bundle.

## Upgrading to next release (Unreleased)

_No changes yet._

## Upgrading to 2.0.7 (2026-01-30)

Access records now store route params and path. Schema change required.

**New:**
- **Route params and path in access records** – Each `RouteDataRecord` stores `route_params` (JSON) and `route_path` (VARCHAR 2048). The Access Records UI shows a Path column with a link to the exact URL; CSV/JSON exports include both fields.

**Schema:**
- **`routes_data_records`** – New columns `route_params` (JSON, nullable) and `route_path` (VARCHAR 2048, nullable).

Run schema update after upgrading:

```bash
composer update nowo-tech/performance-bundle
php bin/console cache:clear
php bin/console nowo:performance:create-records-table --update
# or: php bin/console nowo:performance:sync-schema
```

## Upgrading to 2.0.6 (2026-01-30)

Cache improvements and dedicated pool. No schema or breaking changes.

**New:**
- **Dedicated cache pool** – The bundle registers `nowo_performance.cache` (filesystem, 1h TTL) by default. `PerformanceCacheService` and `TableStatusChecker` use this pool. Cache is isolated from `cache.app`.
- **Cache config** – Override with `nowo_performance.cache.pool` if you prefer to use `cache.app` or another pool.
- **TableStatusChecker** – `getMissingColumns()` results are cached (5 min) to reduce database introspection queries.

**Fixed:**
- **symfony/yaml** – Added as explicit dependency. Fixes "Unable to load YAML config files as the Symfony Yaml Component is not installed" when the component was not pulled transitively.

No upgrade steps required. Clear cache after updating:

```bash
composer update nowo-tech/performance-bundle
php bin/console cache:clear
```

## Upgrading to 2.0.5 (2026-01-28)

New feature: store logged-in user on access records when `track_user` is enabled. Fixes: Security autowiring on Symfony 7/8; translation validation no longer reports false positives for the same key in different YAML blocks. See [CHANGELOG](CHANGELOG.md#205---2026-01-28) for details.

**New:**
- **Access records: logged-in user** – When access records and `track_user` are enabled, each `RouteDataRecord` can store the logged-in user: `user_identifier` (e.g. username, email from `UserInterface::getUserIdentifier()`) and `user_id` (stringified ID from `User::getId()` if present). The Access Records UI shows a User column; CSV and JSON exports include both fields.
- **Makefile** – `make validate-translations` runs translation validation in the PHP container.
- **Translation validation** – Duplicate-key check is now block-aware (same key in different parent blocks is allowed).

**Fixed:**
- **Security autowiring** – The bundle no longer requires `Symfony\Component\Security\Core\Security`; the security helper is injected optionally. Fixes "class was not found" on Symfony 7/8.

**Configuration:**
- **`nowo_performance.track_user`** – New option (boolean, default `false`). When `true` and access records are enabled, the bundle stores user identifier and user ID on each record. Disabled by default for privacy. Requires Symfony Security when enabled.

**Schema:**
- **`routes_data_records`** – New columns `user_identifier` (VARCHAR 255, nullable) and `user_id` (VARCHAR 64, nullable).

Run schema update after upgrading:

```bash
composer update nowo-tech/performance-bundle
php bin/console cache:clear
php bin/console nowo:performance:create-table --update
# or: php bin/console nowo:performance:sync-schema
```

To enable user tracking, set in your config:

```yaml
nowo_performance:
    enable_access_records: true
    track_user: true
```

## Upgrading to 2.0.4 (2026-01-28)

New features: HTTP Referer on access records and per-route option to disable saving access records. See [CHANGELOG](CHANGELOG.md#204---2026-01-28) for details.

**New:**
- **Access records: HTTP Referer** – Each `RouteDataRecord` now stores the HTTP `Referer` header when present. The Access Records UI shows a Referer column (with link); CSV and JSON exports include the referer.
- **Per-route: disable saving access records** – When access records are enabled, you can turn off saving access records for individual routes. In the review/config modal for each route (same form as "Mark as reviewed"), a checkbox **"Save access records for this route"** appears. If unchecked, the bundle still updates aggregate metrics (RouteData) for that route but does not create new `RouteDataRecord` rows.

**Schema:**
- **`routes_data`** – New column `save_access_records` (boolean, default true).
- **`routes_data_records`** – New column `referer` (VARCHAR 2048, nullable).

Run schema update after upgrading:

```bash
composer update nowo-tech/performance-bundle
php bin/console cache:clear
php bin/console nowo:performance:create-table --update
# or: php bin/console nowo:performance:sync-schema
```

## Upgrading to 2.0.3 (2026-01-29)

Bugfix release. No schema or configuration changes.

**Fixed:**
- **Request ID on sub-requests** – Resolves "undefined method getMainRequest of Request" in environments where `Request::getMainRequest()` is not available. The bundle now uses `RequestStack` to obtain the main request when sharing the request ID with sub-requests.

```bash
composer update nowo-tech/performance-bundle
php bin/console cache:clear
```

## Upgrading to 2.0.2 (2026-01-29)

Deduplication of access records, translation validation, schema/collector/diagnose improvements. See [CHANGELOG](CHANGELOG.md#202---2026-01-29) for details.

**New:**
- **Request ID deduplication** – The bundle assigns a unique `request_id` per HTTP request (shared between main and sub-requests). When access records are enabled, at most one `RouteDataRecord` is created per logical request, avoiding duplicate entries when multiple `TERMINATE` events fire (e.g. main request + fragment).
- **Translation YAML validation** – Run `composer validate-translations` (or `php scripts/validate-translations-yaml.php src/Resources/translations`) to check translation files for syntax and duplicate keys. CI runs this automatically.
- **Collector & diagnose** – Web Profiler and CLI diagnose now report when the access records table is incomplete (e.g. missing `request_id`) and suggest running `sync-schema` or `create-records-table --update`.

**Schema:**
- **`routes_data_records`** – New optional column `request_id` (VARCHAR 64, nullable, unique). Existing records keep `request_id = NULL`. Run `php bin/console nowo:performance:sync-schema` or your Doctrine migrations after updating.

No configuration changes. Clear cache after updating:

```bash
composer update nowo-tech/performance-bundle
php bin/console cache:clear
php bin/console nowo:performance:sync-schema
```

## Upgrading to 2.0.1 (2026-01-28)

Fixes, UI improvements, and review system enhancement; no breaking changes or configuration updates.

**New:**
- **Review system: edit existing review** – Routes already marked as reviewed now show an "Edit review" button. The same modal opens with the form pre-filled; you can change "Queries improved" and "Time improved" and save. Modal title and submit label differ when editing; flash message "Review updated" on save.

**Fixes and changes:**
- **Routes table** – Sort by Memory usage column now orders by the numeric value (bytes).
- **Charts** – Chart.js initialization runs after the DOM is ready; no console errors from missing `Chart` or canvas.
- **Routes table** – Status Codes column no longer shows "Total responses" (see Access Count); Status Codes and Access Count columns are placed next to each other.
- **Routes table** – "View access records" link uses Symfony UX Icons (`bi:eye`) instead of an emoji.

No upgrade steps required. Clear cache after updating:

```bash
composer update nowo-tech/performance-bundle
php bin/console cache:clear
```

## Upgrading to 2.0.0 (2026-01-28)

Version **2.0.0** introduces **breaking changes** due to entity normalization: `RouteData` no longer stores aggregate metrics (requestTime, totalQueries, queryTime, memoryUsage, accessCount, statusCodes, updatedAt); those are derived from `RouteDataRecord` or an aggregates layer. `RouteDataRecord` gains totalQueries, queryTime, memoryUsage per record.

**Before upgrading:**

- Read **[V2_MIGRATION.md](V2_MIGRATION.md)** for the full list of breaking changes and migration steps.
- Read **[ENTITY_NORMALIZATION_PLAN.md](ENTITY_NORMALIZATION_PLAN.md)** for the target data model and implementation phases.

**Summary:**

- Update code that uses `RouteData::getRequestTime()`, `getTotalQueries()`, `getQueryTime()`, `getMemoryUsage()`, `getAccessCount()`, `getStatusCodes()`, `getUpdatedAt()` — in 2.0 these will not exist; use aggregates or records instead.
- Run schema sync (e.g. `nowo:performance:sync-schema --drop-obsolete`) or Doctrine migrations after updating entities.
- Adjust dashboard views, notifications, and exports to use the new data source.

The 1.x branch remains supported without these breaking changes.

### Also in 2.0.0: Schema sync, drop-obsolete, collector diagnostics

2.0.0 adds the sync-schema command, drop-obsolete option, and improves Web Profiler diagnostics when tracking is disabled.

#### Changes

- **`nowo:performance:sync-schema`** – Syncs both `routes_data` and `routes_data_records` with entity metadata (add missing, alter differing, optional drop with `--drop-obsolete`).
- **`--drop-obsolete`** – Available for `create-table`, `create-records-table`, and `sync-schema`. Drops columns that exist in DB but not in the entity; `id` is never dropped.
- **Collector diagnostics when disabled** – The Performance panel in the Web Profiler always shows route, environment, table status, and dependency info when tracking is disabled, so you can see why it's disabled (e.g. `_profiler` in `ignore_routes`).

#### Migration Steps (2.0.0)

1. **Update the bundle**:
   ```bash
   composer require nowo-tech/performance-bundle:^2.0
   ```

2. **Clear cache** (recommended):
   ```bash
   php bin/console cache:clear
   ```

3. **Optional**: After entity mapping changes, run `php bin/console nowo:performance:sync-schema` (or add `--drop-obsolete` to remove obsolete columns). See [COMMANDS.md](COMMANDS.md#nowoperformancesync-schema).

No configuration changes required.

## Upgrading to 1.0.8 (2026-01-27)

### Default Environments Now Include Production

This version changes the default value for the `environments` configuration to include production, making the bundle more suitable for production monitoring out of the box.

#### Changes

- **Default environments updated**: Changed from `['dev', 'test']` to `['prod', 'dev', 'test']`
  - The bundle now tracks performance in production by default
  - This is more appropriate for a performance monitoring bundle
  - Only affects new installations or configurations that don't explicitly set `environments`
  - Existing configurations are not affected

#### What This Means

- **Production tracking by default**: New installations will track production environments automatically
- **No breaking changes**: Existing configurations continue to work as before
- **Better defaults**: More sensible default for a performance monitoring tool
- **Easy to override**: Can still be customized via configuration

#### Migration Steps

1. **Update the bundle**:
   ```bash
   composer update nowo-tech/performance-bundle
   ```

2. **Review your configuration** (if you have one):
   - If you have `environments: ['dev', 'test']` explicitly set, it will continue to work
   - If you want to include production, update to: `environments: ['prod', 'dev', 'test']`
   - If you don't have `environments` configured, it will now default to including `prod`

3. **Clear cache** (recommended):
   ```bash
   php bin/console cache:clear
   ```

4. **Verify tracking**: Check that performance data is being recorded in your production environment

#### Configuration Example

If you want to explicitly configure environments (recommended for clarity):

```yaml
nowo_performance:
    environments: ['prod', 'dev', 'test', 'stage']  # Add your custom environments
```

#### Troubleshooting

**Q: I don't want to track production by default**  
A: Explicitly set `environments` in your configuration:
   ```yaml
   nowo_performance:
       environments: ['dev', 'test']  # Only dev and test
   ```

**Q: My production data is not being tracked**  
A: Check that:
   - Your `APP_ENV` is set to `prod`
   - The `environments` configuration includes `prod` (or is not set to use the new default)
   - The bundle is enabled: `enabled: true`
   - Clear the cache: `php bin/console cache:clear`

**Q: I see "env=prod not in allowed environments" in logs**  
A: This means your configuration explicitly excludes `prod`. Either:
   - Remove the `environments` configuration to use the new default
   - Or add `prod` to your `environments` array

## Upgrading to 1.0.7 (2026-01-27)

### Enhanced Debug Logging and Test Coverage

This version adds comprehensive debug logging throughout the performance tracking flow and extensive test coverage to help diagnose issues.

#### Changes

- **Enhanced debug logging**: Added detailed logging at every step of the performance tracking process
  - Logs show collector state, route checks, environment validation, metric calculations, and save attempts
  - All logs include route name and environment for easy filtering
  - Helps diagnose why data might not be saved in production
  - Logs are controlled by `enable_logging` configuration (default: true)
- **Comprehensive test coverage**: Added 33 new tests for logging and edge cases
  - Tests verify all debug logs are generated correctly
  - Tests cover edge cases that could prevent data from being saved
  - Improves reliability and maintainability

#### What This Means

- **Better debugging**: Detailed logs help identify why data might not be saved
- **Better reliability**: More tests mean fewer bugs and better edge case handling
- **No breaking changes**: All changes are backward compatible
- **Production ready**: Logs can be disabled via configuration for production environments

#### Migration Steps

1. **Update the bundle**:
   ```bash
   composer update nowo-tech/performance-bundle
   ```

2. **Clear cache** (recommended):
   ```bash
   php bin/console cache:clear
   ```

3. **No configuration changes required**: The enhanced logging is enabled by default but respects the `enable_logging` configuration

#### Troubleshooting

**Q: I see too many logs in production**  
A: Disable logging in production by setting `enable_logging: false` in your configuration:
   ```yaml
   nowo_performance:
       enable_logging: false
   ```

**Q: How do I filter logs by route or environment?**  
A: All logs include `[PerformanceBundle]` prefix and include route name and environment. You can filter using:
   ```bash
   grep "[PerformanceBundle]" /var/log/app.log | grep "route=app_home"
   grep "[PerformanceBundle]" /var/log/app.log | grep "env=prod"
   ```

**Q: What do the logs tell me?**  
A: The logs show:
   - When tracking is enabled/disabled and why
   - Route and environment checks
   - Metric calculations (request time, query count, memory usage)
   - Save attempts with all details
   - Success/failure of save operations
   - Any errors during the save process

## Upgrading to 1.0.6 (2026-01-27)

### Symfony 7 Compatibility and Test Coverage

This version fixes compatibility with Symfony 7.x and adds comprehensive test coverage.

#### Changes

- **Symfony 7 compatibility fix**: Fixed compatibility issue with Symfony 7.x commands
  - Moved `help` parameter from `#[AsCommand]` attribute to `configure()` method
  - All commands now use `setHelp()` in `configure()` method
  - Compatible with both Symfony 6.x and 7.x
  - Fixes "Unknown named parameter $help" error
- **Test coverage**: Added 60+ new tests for improved reliability
  - Tests for chart data generation
  - Tests for access statistics
  - Tests for subscriber detection
  - Tests for environment filtering
  - Tests for export functionality
  - Tests for filter building

#### What This Means

- **Symfony 7 support**: All commands now work correctly with Symfony 7.x
- **Better reliability**: Comprehensive test coverage ensures edge cases are handled
- **No breaking changes**: All changes are backward compatible with Symfony 6.x
- **Improved code quality**: More tests mean fewer bugs

#### Migration Steps

1. **Update the bundle**:
   ```bash
   composer update nowo-tech/performance-bundle
   ```

2. **Clear cache** (recommended):
   ```bash
   php bin/console cache:clear
   ```

3. **No configuration changes required**: The fix is automatic and backward compatible

#### Troubleshooting

**Q: I still see "Unknown named parameter $help" error**  
A: Make sure you're using v1.0.6 or higher. Clear cache and verify:
   ```bash
   composer show nowo-tech/performance-bundle
   php bin/console cache:clear
   ```

**Q: Commands work but help text is missing**  
A: This should be fixed in v1.0.6. The help text is now set in the `configure()` method. Clear cache and try again.

## Upgrading to 1.0.5 (2026-01-27)

### Bug Fix

This version fixes an issue where the diagnose page incorrectly showed the subscriber as not registered.

#### Changes

- **Subscriber detection fix**: Improved subscriber detection in diagnose page
  - Uses multiple detection methods with fallbacks
  - Checks event dispatcher listeners for REQUEST and TERMINATE events
  - Falls back to container service lookup
  - Final fallback to class existence and interface verification
  - Now correctly detects subscriber in all scenarios
  - Shows detection method in diagnostic output

#### What This Means

- **Better diagnostics**: Diagnose page now correctly shows subscriber status
- **More reliable**: Multiple detection methods ensure accurate reporting
- **Better debugging**: Detection method is shown to help understand how subscriber was detected
- **No breaking changes**: All changes are internal and backward compatible

#### Migration Steps

1. **Update the bundle**:
   ```bash
   composer update nowo-tech/performance-bundle
   ```

2. **Clear cache** (recommended):
   ```bash
   php bin/console cache:clear
   ```

3. **Verify**:
   - Visit `/performance/diagnose` (or your configured path)
   - Check that "Registered Subscriber" shows "✓ Yes"
   - Verify that detection method is shown

#### Troubleshooting

**Q: Diagnose still shows subscriber as not registered**  
A: Clear cache and verify the bundle is properly installed:
   ```bash
   php bin/console cache:clear
   php bin/console debug:container PerformanceMetricsSubscriber
   ```
   The subscriber should be listed as a service.

**Q: I see "detection_method" in the diagnostic output**  
A: This is normal. It shows how the subscriber was detected (via event dispatcher, container, or class existence check).

## Upgrading to 1.0.4 (2026-01-27)

### Bug Fix and Test Coverage

This version fixes an issue where the environment filter was empty when no data was recorded, and adds comprehensive test coverage.

#### Changes

- **Environment filter fix**: Fixed issue where environment filter dropdown was empty when no data was recorded
  - Filter now uses `allowedEnvironments` from configuration as fallback
  - Ensures filter always has options even when database is empty
  - Falls back gracefully through multiple levels (database → allowedEnvironments → current env → defaults)
- **Test coverage**: Added 41 new tests for improved reliability
  - Tests for `getAvailableEnvironments()` method
  - Tests for export functionality (CSV/JSON)
  - Tests for filter building from request parameters

#### What This Means

- **Better UX**: Environment filter now always shows options, even when no data is recorded
- **Improved reliability**: Comprehensive test coverage ensures edge cases are handled
- **No breaking changes**: All changes are backward compatible

#### Migration Steps

1. **Update the bundle**:
   ```bash
   composer update nowo-tech/performance-bundle
   ```

2. **Clear cache** (recommended):
   ```bash
   php bin/console cache:clear
   ```

3. **No configuration changes required**: The fix is automatic and uses your existing `allowedEnvironments` configuration

#### Troubleshooting

**Q: Environment filter is still empty**  
A: Make sure you have `allowedEnvironments` configured in your `nowo_performance.yaml`:
   ```yaml
   nowo_performance:
       environments: ['dev', 'test', 'prod', 'stage']
   ```
   The filter will use these environments as fallback when no data is recorded.

**Q: I want to see only environments that have data**  
A: This is the default behavior when data exists. The fallback to `allowedEnvironments` only occurs when the database is empty.

## Upgrading to 1.0.3 (2026-01-27)

### Bug Fix

This version fixes a critical error that occurred when trying to get the driver name from a connection wrapped with middleware.

#### Changes

- **Driver name detection fix**: Fixed error when getting driver name from wrapped drivers
  - Resolves "Attempted to call an undefined method named 'getName' of class 'AbstractDriverMiddleware'" error
  - Created `getDriverName()` helper method that handles drivers wrapped with middleware
  - Uses reflection to access the underlying driver when wrapped
  - Falls back to platform class name inference when direct methods are unavailable
  - Fixes error in `nowo:performance:diagnose` command when using query tracking middleware

#### What This Means

- **Bug fix**: Diagnose command now works correctly with query tracking middleware enabled
- **Better compatibility**: Works with all DBAL versions and middleware configurations
- **No breaking changes**: All changes are internal and backward compatible

#### Migration Steps

1. **Update the bundle**:
   ```bash
   composer update nowo-tech/performance-bundle
   ```

2. **Clear cache** (recommended):
   ```bash
   php bin/console cache:clear
   ```

3. **Verify installation**:
   ```bash
   php bin/console nowo:performance:diagnose
   ```
   The diagnose command should now work without errors, even when query tracking middleware is enabled.

#### Troubleshooting

**Q: I still see the "getName()" error in diagnose command**  
A: Make sure you're using v1.0.3 or higher. Clear cache and try again:
   ```bash
   php bin/console cache:clear
   php bin/console nowo:performance:diagnose
   ```

**Q: The diagnose command shows "unknown" for driver name**  
A: This is normal if the driver cannot be detected. The bundle will try multiple methods to detect the driver name, and if all fail, it will show "unknown" instead of crashing.

## Upgrading to 1.0.2 (2026-01-27)

### Bug Fixes and Improvements

This version fixes several bugs and improves the reliability of the bundle.

#### Changes

- **Modal dependency display fix**: Fixed issue where Tailwind modal appeared in Bootstrap projects
  - Tailwind modal is now only included when using the Tailwind template
  - Bootstrap modal works correctly in Bootstrap projects
  - Improved automatic detection of Bootstrap vs Tailwind
- **Division by zero fix**: Fixed `DivisionByZeroError` in correlation analysis
  - Improved variance checks before computing correlations
  - Prevents errors when data has zero variance
  - Returns `null` safely when correlation cannot be computed
- **Data Collector status fix**: Fixed "Unknown" status in Web Profiler
  - The collector now correctly shows whether a new record was created or an existing one was updated
  - Improves the accuracy of the status shown in the Web Profiler toolbar and panel

#### What This Means

- **Better UX**: Modals now display correctly according to the active template
- **More reliable**: Correlation analysis no longer crashes with division by zero errors
- **Better diagnostics**: Web Profiler now shows accurate record operation status
- **No breaking changes**: All changes are backward compatible

#### Migration Steps

1. **Update the bundle**:
   ```bash
   composer update nowo-tech/performance-bundle
   ```

2. **Clear cache** (recommended):
   ```bash
   php bin/console cache:clear
   ```

3. **No configuration changes required**: All fixes are automatic and backward compatible

#### Troubleshooting

**Q: I still see the Tailwind modal in my Bootstrap project**  
A: Clear your cache and ensure you're using v1.0.2 or higher. The modal should only appear if you're using Tailwind template.

**Q: I see "Division by zero" errors in statistics page**  
A: This should be fixed in v1.0.2. Update the bundle and clear cache. If the error persists, check that you have enough data points (at least 2 different values) for correlation analysis.

**Q: Web Profiler still shows "Unknown" status**  
A: This should be fixed in v1.0.2. Update the bundle and clear cache. The status should now show correctly whether a new record was created or an existing one was updated.

## Upgrading to 1.0.1 (2026-01-27)

### Performance Optimizations and Dependency Management

This version adds significant performance optimizations, dependency detection, and fixes a critical bug in the table update command.

#### Changes

- **Performance optimizations**: Reduced database queries by ~90-95%
  - Table status caching: Caches table existence and completeness checks (5 minutes TTL)
  - Optional ranking queries: Can be disabled to eliminate 3 queries per request
  - Overall reduction: From ~13 queries per request to 0-1 query per request
- **Dependency detection**: Extended DependencyChecker to detect optional dependencies
  - Detects Messenger, Mailer, and HttpClient availability
  - Provides modal information when dependencies are missing
  - Helps users understand what's needed for specific features
- **Automatic routes file**: Symfony Flex recipe now creates routes file automatically
  - `config/routes/nowo_performance.yaml` is created on installation
  - Routes are automatically imported with configured prefix/path
- **Bug fix**: Fixed DBAL 3.x compatibility issue in CreateTableCommand
  - Fixes error when running `nowo:performance:create-table --update`
  - Resolves "Undefined array key 'name'" error

#### What This Means

- **Better performance**: Significantly fewer database queries per request
- **Better UX**: Clear information about missing dependencies
- **Easier installation**: Routes are automatically configured
- **Bug fix**: Table update command now works correctly with DBAL 3.x
- **No breaking changes**: All changes are backward compatible
- **Optional optimizations**: Ranking queries can be disabled if not needed

#### Migration Steps

1. **Update the bundle**:
   ```bash
   composer update nowo-tech/performance-bundle
   ```

2. **Clear cache** (recommended):
   ```bash
   php bin/console cache:clear
   ```

3. **Verify routes file** (if upgrading from 1.0.0):
   - Check if `config/routes/nowo_performance.yaml` exists
   - If not, it will be created automatically on next Flex update
   - Or manually create it:
     ```yaml
     # config/routes/nowo_performance.yaml
     nowo_performance:
         resource: '@NowoPerformanceBundle/Resources/config/routes.yaml'
         prefix: '%nowo_performance.dashboard.prefix%%nowo_performance.dashboard.path%'
     ```

4. **Optional: Disable ranking queries** (to reduce database load):
   ```yaml
   # config/packages/nowo_performance.yaml
   nowo_performance:
       dashboard:
           enable_ranking_queries: false  # Disable ranking queries in WebProfiler
   ```

5. **Verify installation**:
   ```bash
   php bin/console nowo:performance:diagnose
   php bin/console nowo:performance:check-dependencies
   ```

#### Performance Impact

**Before (v1.0.0)**:
- ~10 queries to `information_schema` per request (table status checks)
- 3 queries for ranking information per request
- 1 query to get/update route data
- **Total: ~14 queries per request**

**After (v1.0.1)**:
- 0 queries to `information_schema` (cached for 5 minutes)
- 0–3 queries for ranking (optional; set `enable_ranking_queries: false` to disable)
- 1 query to get/update route data
- **Total: 1–4 queries per request (90–95% reduction)**

#### Troubleshooting

**Q: I see a modal about missing dependencies when clicking buttons**  
A: This is normal. The bundle detects optional dependencies (Messenger, Mailer, HttpClient) and shows information when they're missing. Install the dependencies if you need those features.

**Q: The routes file wasn't created automatically**  
A: This happens if you installed the bundle before v1.0.1. Create it manually (see Migration Steps above) or wait for the next Flex update.

**Q: I want to disable ranking queries but can't find the option**  
A: Add `enable_ranking_queries: false` to your `nowo_performance.dashboard` configuration. This is a new option in v1.0.1.

**Q: Table update command still fails with DBAL 3.x**  
A: Make sure you're using v1.0.1 or higher. Clear cache and try again:
   ```bash
   php bin/console cache:clear
   php bin/console nowo:performance:create-table --update
   ```

## Upgrading to 1.0.0 (2026-01-27)

### Sub-Request Tracking Support

This version adds support for tracking sub-requests in addition to main requests.

#### Changes

- **New configuration option**: `track_sub_requests`
  - Type: `boolean`
  - Default: `false` (maintains backward compatibility)
  - When enabled, tracks both main requests and sub-requests separately

#### What This Means

- **No breaking changes**: Default behavior unchanged (only main requests tracked)
- **Optional feature**: Enable only if you need to track sub-requests
- **Use cases**: ESI performance, fragment rendering, debugging sub-request bottlenecks
- **Database impact**: Enabling will increase database storage (subject to sampling_rate)

#### Migration Steps

1. **Update the bundle**:
   ```bash
   composer update nowo-tech/performance-bundle
   ```

2. **Clear cache** (recommended):
   ```bash
   php bin/console cache:clear
   ```

3. **Enable sub-request tracking** (optional):
   ```yaml
   # config/packages/nowo_performance.yaml
   nowo_performance:
       track_sub_requests: true  # Enable tracking of sub-requests
   ```

4. **Verify installation**:
   ```bash
   php bin/console nowo:performance:diagnose
   ```

#### Troubleshooting

**Q: I see deprecation warnings when running `nowo:performance:create-table --update`**  
A: These warnings have been fixed in this version. Update to the latest version to eliminate them.

**Q: Should I enable `track_sub_requests`?**  
A: Only if you need to monitor sub-request performance separately. By default, only main requests are tracked to avoid duplicate metrics and reduce database load.

**Q: Will enabling this affect performance?**  
A: There will be a small overhead for tracking sub-requests, and database storage will increase. Use `sampling_rate` to reduce load for high-traffic routes.

## Upgrading to 0.0.7 (2026-01-27)

### Enhanced Environment Detection and Collector Display

This version improves environment detection and adds environment information to the collector for better diagnostics.

#### Changes

- **Environment detection improvements**: Enhanced robustness of environment detection
  - Tries multiple methods to detect environment: kernel, request server, `$_SERVER`, `$_ENV`
  - Added debug logging to show which method was used
  - More reliable fallback chain ensures environment is always detected
- **Collector always visible**: Collector now displays even when disabled
  - Shows status information when tracking is disabled
  - Displays reason for being disabled
  - Helps diagnose configuration issues
- **Environment information in collector**: Added environment configuration display
  - Shows configured environments (where tracking is enabled)
  - Displays current system environment with status indicator
  - Visual indicators show if current environment is enabled or disabled

#### What This Means

- **Better diagnostics**: You can now see why tracking might be disabled
- **Environment visibility**: Clear indication of configured vs. current environment
- **No breaking changes**: All changes are additive and backward compatible
- **No configuration changes required**: No changes to bundle configuration needed
- **No database changes required**: No schema changes

#### Migration Steps

1. **Update the bundle**:
   ```bash
   composer update nowo-tech/performance-bundle
   ```

2. **Clear cache** (recommended):
   ```bash
   php bin/console cache:clear
   ```

3. **Verify installation**:
   ```bash
   php bin/console nowo:performance:diagnose
   ```

4. **Check collector in Web Profiler**:
   - Open any page in development environment
   - Check the Performance collector in the Web Profiler toolbar
   - You should now see:
     - Configured environments (e.g., `dev, test, stage`)
     - Current environment (e.g., `dev`)
     - Status indicator showing if current environment is enabled

#### Troubleshooting

**Issue**: Collector shows "Tracking Disabled" even though `APP_ENV=dev`

**Solution**: 
1. Check the collector panel to see:
   - Configured environments (should include your current environment)
   - Current environment (should match `APP_ENV`)
2. If current environment is not in the configured list, add it to `nowo_performance.environments` in your configuration
3. Check the debug logs for environment detection messages:
   ```
   [PerformanceBundle] Environment detection: kernel=dev, detected_env=dev, allowed=dev, test, stage
   ```

**Issue**: Environment detection shows "null" for kernel

**Solution**: This is normal if the kernel is not injected. The bundle will try other methods (`$_SERVER['APP_ENV']`, `$_ENV['APP_ENV']`) automatically.

## Upgrading to 0.0.6 (2026-01-27)

### Test Coverage Improvement

This version adds comprehensive test coverage for `QueryTrackingConnectionSubscriber`.

#### Changes

- **Test coverage**: Added 6 new tests for `QueryTrackingConnectionSubscriber`
- Tests cover all scenarios including enabled/disabled states, error handling, and query tracking reset
- Improves code quality and reliability

#### What This Means

- **No code changes**: This is a test-only release
- **No configuration changes required**: No changes to bundle configuration
- **No database changes required**: No schema changes
- **Improved reliability**: Better test coverage ensures the middleware application works correctly

#### Migration Steps

1. **Update the bundle**:
   ```bash
   composer update nowo-tech/performance-bundle
   ```

2. **Clear cache** (optional, but recommended):
   ```bash
   php bin/console cache:clear
   ```

3. **Verify installation**:
   ```bash
   php bin/console nowo:performance:diagnose
   ```

## Upgrading to 0.0.5 (2026-01-27)

### Compatibility Fix

This version removes YAML middleware configuration completely to fix compatibility issues across all DoctrineBundle versions.

#### Changes

- **DoctrineBundle middleware configuration**: Removed YAML middleware configuration (`middlewares` and `yamlMiddleware`)
- YAML middleware options are not reliably available across all DoctrineBundle versions
- Some versions (like 2.17.1) do not support these options, causing "Unrecognized option" errors
- Changed to use only reflection-based middleware application via `QueryTrackingConnectionSubscriber`
- This approach works consistently across all DoctrineBundle versions (2.x and 3.x)
- **QueryTrackingConnectionSubscriber**: Added required `getSubscribedEvents()` method for `EventSubscriberInterface` compliance
- Fixes "Class contains 1 abstract method" error

#### What This Means

- **No configuration changes required**: The bundle automatically uses reflection-based middleware application
- **No database changes required**: This is a code-only fix
- **Fixes installation errors**: Resolves "Unrecognized option 'middlewares'" errors when installing the bundle
- **Works across all versions**: No more compatibility issues with different DoctrineBundle versions

#### Migration Steps

1. **Update the bundle**:
   ```bash
   composer update nowo-tech/performance-bundle
   ```

2. **Clear cache**:
   ```bash
   php bin/console cache:clear
   ```

3. **Verify installation**:
   ```bash
   php bin/console nowo:performance:diagnose
   ```

#### Troubleshooting

**Issue: Still seeing "Unrecognized option 'middlewares'" error**

- **Solution**: Clear Symfony cache and verify the bundle version:
  ```bash
  php bin/console cache:clear
  composer show nowo-tech/performance-bundle
  ```
  Make sure you're using version 0.0.5 or higher.

**Issue: Middleware not being registered**

- **Solution**: Run the diagnose command to check middleware registration:
  ```bash
  php bin/console nowo:performance:diagnose
  ```
  The command will show that middleware is applied via Event Subscriber (Reflection).

**Issue: "Class contains 1 abstract method" error**

- **Solution**: This was fixed in version 0.0.5. Update to the latest version:
  ```bash
  composer update nowo-tech/performance-bundle
  php bin/console cache:clear
  ```

## Upgrading to 0.0.4 (2026-01-27)

### Bug Fix

This version fixes a fatal error that occurred during container compilation.

#### Changes

- **PerformanceDataCollector Throwable import**: Added missing `use Throwable;` import statement
- Fixes "Class 'Nowo\PerformanceBundle\DataCollector\Throwable' not found" error
- Resolves ReflectionException during container compilation

#### What This Means

- **No configuration changes required**: This is a code-only fix
- **No database changes required**: No schema changes
- **Fixes fatal error**: Resolves the error that prevented the application from starting

#### Migration Steps

1. **Update the bundle**:
   ```bash
   composer update nowo-tech/performance-bundle
   ```

2. **Clear cache**:
   ```bash
   php bin/console cache:clear
   ```

3. **Verify installation**:
   ```bash
   php bin/console nowo:performance:diagnose
   ```

#### Troubleshooting

**Issue: Still seeing the Throwable error**

- **Solution**: Clear Symfony cache and verify the bundle version:
  ```bash
  php bin/console cache:clear
  composer show nowo-tech/performance-bundle
  ```
  Make sure you're using version 0.0.4 or higher.

## Upgrading to 0.0.3 (2026-01-27)

### Compatibility Fix

This version fixes a compatibility issue with DoctrineBundle middleware configuration.

#### Changes

- **DoctrineBundle middleware configuration**: Changed from using `yamlMiddleware` to always using `middlewares` for DoctrineBundle 2.x
- The `yamlMiddleware` option is not reliably available across all DoctrineBundle 2.x versions, even when the version suggests it should be supported
- The `middlewares` option is more widely supported and works consistently across all DoctrineBundle 2.x versions

#### What This Means

- **No configuration changes required**: The bundle automatically uses the correct configuration method
- **No database changes required**: This is a configuration-only fix
- **Fixes installation errors**: Resolves "Unrecognized option 'yamlMiddleware'" errors when installing the bundle

#### Migration Steps

1. **Update the bundle**:
   ```bash
   composer update nowo-tech/performance-bundle
   ```

2. **Clear cache**:
   ```bash
   php bin/console cache:clear
   ```

3. **Verify installation**:
   ```bash
   php bin/console nowo:performance:diagnose
   ```

#### Troubleshooting

**Issue: Still seeing "Unrecognized option 'yamlMiddleware'" error**

- **Solution**: Clear Symfony cache and verify the bundle version:
  ```bash
  php bin/console cache:clear
  composer show nowo-tech/performance-bundle
  ```
  Make sure you're using version 0.0.3 or higher.

**Issue: Middleware not being registered**

- **Solution**: Run the diagnose command to check middleware registration:
  ```bash
  php bin/console nowo:performance:diagnose
  ```
  The command will show which method is being used to register the middleware.

## Upgrading to 0.0.2 (2026-01-27)

### New Features Overview

This version adds HTTP status code tracking, performance alert notifications, sampling for high-traffic routes, configurable query tracking threshold, auto-refresh dashboard, and comprehensive test coverage improvements.

### Database Schema Changes

**IMPORTANT**: You need to update your database schema to include the new `status_codes` field.

#### New Field Added

- `status_codes` (JSON, nullable) - HTTP status codes counts (e.g., {'200': 100, '404': 5, '500': 2})

#### Migration Steps

**Option 1: Using the Bundle Command (Recommended)**

```bash
# Add missing columns without losing data
# Also fixes AUTO_INCREMENT if missing (handles foreign keys automatically)
php bin/console nowo:performance:create-table --update
```

**Note**: This command also automatically fixes the `id` column AUTO_INCREMENT if it's missing, even if there are foreign key constraints. The command temporarily drops and restores foreign keys during the fix.

**Option 2: Using Doctrine Migrations (Recommended for Production)**

```bash
# Generate migration
php bin/console doctrine:migrations:diff

# Review the migration file
# Then apply it
php bin/console doctrine:migrations:migrate
```

**Option 3: Manual SQL**

```sql
ALTER TABLE routes_data 
  ADD COLUMN status_codes JSON NULL;
```

### New Configuration Options

#### HTTP Status Code Tracking

Configure which HTTP status codes to track and calculate ratios for:

```yaml
# config/packages/nowo_performance.yaml
nowo_performance:
    # Status codes to track (default: 200, 404, 500, 503)
    track_status_codes: [200, 404, 500, 503]
```

**Use cases:**
- Monitor success rates (200 vs errors)
- Track error rates (404, 500, 503)
- Identify problematic routes

**Example configuration:**
```yaml
nowo_performance:
    track_status_codes: [200, 201, 400, 404, 500, 503]
```

#### Performance Notifications

Enable automatic notifications when performance thresholds are exceeded:

```yaml
# config/packages/nowo_performance.yaml
nowo_performance:
    notifications:
        enabled: true
        email:
            enabled: true
            from: 'noreply@example.com'
            to: ['admin@example.com']
        slack:
            enabled: true
            webhook_url: 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL'
```

**See [NOTIFICATIONS.md](NOTIFICATIONS.md) for complete documentation.**

#### Sampling Rate

Reduce database load for frequently accessed routes by tracking only a percentage of requests:

```yaml
# config/packages/nowo_performance.yaml
nowo_performance:
    # Sampling rate: 0.0 to 1.0 (where 1.0 = 100% tracking)
    # Example: 0.1 = only track 10% of requests
    sampling_rate: 1.0  # Default: 1.0 (track all requests)
```

**Use cases:**
- High-traffic production environments
- Routes with thousands of requests per minute
- When database write performance is a concern

**Example configuration for production:**
```yaml
nowo_performance:
    sampling_rate: 0.1  # Track only 10% of requests
```

#### Query Tracking Threshold

Configure minimum query count to track query execution time:

```yaml
# config/packages/nowo_performance.yaml
nowo_performance:
    # Minimum query count to track query execution time
    # Queries below this threshold are counted but not timed individually
    query_tracking_threshold: 0  # Default: 0 (track all queries)
```

**Use cases:**
- Reduce overhead for routes with very few queries
- Focus timing on routes with significant query activity

**Example configuration:**
```yaml
nowo_performance:
    query_tracking_threshold: 5  # Only time queries if route has 5+ queries
```

#### Auto-Refresh Dashboard

Enable automatic dashboard refresh with visual countdown:

```yaml
# config/packages/nowo_performance.yaml
nowo_performance:
    dashboard:
        # Auto-refresh interval in seconds (0 = disabled)
        auto_refresh_interval: 30  # Default: 0 (disabled)
```

**Features:**
- Visual countdown indicator
- Automatically pauses when window loses focus
- Improves real-time monitoring experience

**Example configuration:**
```yaml
nowo_performance:
    dashboard:
        auto_refresh_interval: 30  # Refresh every 30 seconds
```

### Migration Steps

1. **Update database schema** (if upgrading from 0.0.1):
   ```bash
   php bin/console nowo:performance:create-table --update
   ```

2. **Update configuration** (optional):
   ```yaml
   # config/packages/nowo_performance.yaml
   nowo_performance:
       track_status_codes: [200, 404, 500, 503]
       sampling_rate: 1.0
       query_tracking_threshold: 0
       dashboard:
           auto_refresh_interval: 0
       notifications:
           enabled: false  # Enable if you want notifications
   ```

3. **Clear cache**:
   ```bash
   php bin/console cache:clear
   ```

### Breaking Changes

- **None** - All new features are backward compatible

### Testing Your Upgrade

1. **Verify database schema**:
   ```bash
   php bin/console nowo:performance:diagnose
   # Should show status_codes column exists
   ```

2. **Verify configuration**:
   ```bash
   php bin/console debug:container --parameter=nowo_performance.track_status_codes
   php bin/console debug:container --parameter=nowo_performance.sampling_rate
   php bin/console debug:container --parameter=nowo_performance.query_tracking_threshold
   php bin/console debug:container --parameter=nowo_performance.dashboard.auto_refresh_interval
   ```

3. **Test status code tracking**:
   - Make requests to routes with different status codes (200, 404, 500)
   - Check dashboard to see status code ratios displayed
   - Verify ratios are calculated correctly

4. **Test notifications** (if enabled):
   - Set low thresholds temporarily
   - Make a request that exceeds thresholds
   - Verify notifications are sent

5. **Test sampling**:
   - Set `sampling_rate: 0.5` (track 50% of requests)
   - Make multiple requests to the same route
   - Verify that approximately 50% are recorded

6. **Test auto-refresh**:
   - Set `auto_refresh_interval: 30`
   - Open dashboard and verify countdown appears
   - Verify dashboard refreshes after 30 seconds

### Troubleshooting

**Issue: Error "Field 'id' doesn't have a default value"**

- **Cause**: The `id` column in the `routes_data` table is missing AUTO_INCREMENT
- **Solution**: Run the update command to fix it automatically:
  ```bash
  php bin/console nowo:performance:create-table --update
  ```
  This command will:
  - Detect missing AUTO_INCREMENT on the `id` column
  - Temporarily drop foreign keys that reference the `id` column
  - Add AUTO_INCREMENT to the `id` column
  - Restore all foreign keys with their original rules
- **Note**: If you have foreign keys referencing the `id` column (e.g., from `routes_data_records` table), the command handles them automatically

**Issue: Status codes not being tracked**

- **Solution**: Verify the column exists and configuration is correct:
  ```bash
  php bin/console nowo:performance:create-table --update
  php bin/console debug:container --parameter=nowo_performance.track_status_codes
  ```

**Issue: Notifications not working**

- **Solution**: Verify configuration and dependencies:
  ```bash
  php bin/console debug:container --parameter=nowo_performance.notifications.enabled
  # For email: composer require symfony/mailer
  # For webhooks: composer require symfony/http-client
  ```

**Issue: Sampling not working**

- **Solution**: Clear cache and verify configuration is loaded:
  ```bash
  php bin/console cache:clear
  php bin/console debug:container --parameter=nowo_performance.sampling_rate
  ```

**Issue: Auto-refresh not working**

- **Solution**: Verify JavaScript is enabled and check browser console for errors

## Upgrading to 0.0.1 (2026-01-26)

### New Features Overview

This version adds significant new features including memory tracking, access frequency, record management, review system, data export, and enhanced Web Profiler integration.

### Database Schema Changes

**IMPORTANT**: You need to update your database schema to include the new fields.

#### New Fields Added

- `memory_usage` (BIGINT, nullable) - Peak memory usage in bytes
- `access_count` (INTEGER, default: 1) - Number of times route was accessed
- `last_accessed_at` (DATETIME_IMMUTABLE, nullable) - Last access timestamp
- `reviewed` (BOOLEAN, default: false) - Whether record has been reviewed
- `reviewed_at` (DATETIME_IMMUTABLE, nullable) - Review timestamp
- `queries_improved` (BOOLEAN, nullable) - Whether queries improved after review
- `time_improved` (BOOLEAN, nullable) - Whether time improved after review
- `reviewed_by` (STRING, nullable) - Username of reviewer

#### Migration Steps

**Option 1: Using the Bundle Command (Recommended)**

```bash
# Drop and recreate the table (WARNING: This will delete all data)
php bin/console nowo:performance:create-table --force
```

**Option 2: Using Doctrine Migrations (Recommended for Production)**

```bash
# Generate migration
php bin/console doctrine:migrations:diff

# Review the migration file
# Then apply it
php bin/console doctrine:migrations:migrate
```

**Option 3: Manual SQL**

If you prefer to manually update the schema:

```sql
ALTER TABLE routes_data 
  ADD COLUMN memory_usage BIGINT NULL,
  ADD COLUMN access_count INTEGER NOT NULL DEFAULT 1,
  ADD COLUMN last_accessed_at DATETIME_IMMUTABLE NULL,
  ADD COLUMN reviewed BOOLEAN NOT NULL DEFAULT FALSE,
  ADD COLUMN reviewed_at DATETIME_IMMUTABLE NULL,
  ADD COLUMN queries_improved BOOLEAN NULL,
  ADD COLUMN time_improved BOOLEAN NULL,
  ADD COLUMN reviewed_by VARCHAR(255) NULL;

-- Add indexes
CREATE INDEX idx_route_env_access_count ON routes_data(env, access_count);
CREATE INDEX idx_route_reviewed ON routes_data(reviewed);
CREATE INDEX idx_route_reviewed_at ON routes_data(reviewed_at);
```

### New Configuration Options

#### Record Management

Enable individual record deletion from the dashboard:

```yaml
# config/packages/nowo_performance.yaml
nowo_performance:
    dashboard:
        enable_record_management: true  # Default: false
```

#### Review System

Enable the record review system:

```yaml
# config/packages/nowo_performance.yaml
nowo_performance:
    dashboard:
        enable_review_system: true  # Default: false
```

**Note**: Both features are disabled by default for backward compatibility. Enable them explicitly if needed.

### New Commands

Three new commands are available:

1. **`nowo:performance:create-table`** - Create the database table
   ```bash
   php bin/console nowo:performance:create-table
   php bin/console nowo:performance:create-table --force  # Drop and recreate
   ```

2. **`nowo:performance:diagnose`** - Diagnostic information
   ```bash
   php bin/console nowo:performance:diagnose
   ```

3. **`nowo:performance:check-dependencies`** - Check optional dependencies
   ```bash
   php bin/console nowo:performance:check-dependencies
   ```

### Enhanced Command: `nowo:performance:set-route`

The `set-route` command now supports memory usage:

```bash
php bin/console nowo:performance:set-route app_home \
    --request-time=0.5 \
    --queries=10 \
    --memory=1048576  # New option: memory in bytes
```

### Dashboard Changes

#### New Features

- **Export buttons** - CSV and JSON export in dashboard header
- **Clear All Records** - Button to clear all records (with referer redirect)
- **Access Count column** - Shows how many times each route was accessed
- **Last Accessed At column** - Shows last access timestamp
- **Review Status column** - Shows review status and improvement indicators
- **Action buttons** - Delete and review buttons (when enabled)

#### Breaking Changes

- **None** - All new features are opt-in via configuration

### Web Profiler Integration

The PerformanceDataCollector now shows:
- Access frequency (how many times route was accessed)
- Ranking by request time (position among all routes)
- Ranking by query count (position among all routes)
- Total routes count

This information is automatically collected if the route exists in the database.

### Optional Dependencies

The bundle now optionally uses Symfony UX TwigComponent for better performance. If not installed, it falls back to traditional Twig includes.

To install (optional):
```bash
composer require symfony/ux-twig-component
```

### Testing Your Upgrade

1. **Verify database schema**:
   ```bash
   php bin/console nowo:performance:diagnose
   ```

2. **Check table structure**:
   ```bash
   php bin/console doctrine:schema:validate
   ```

3. **Test dashboard**:
   - Access `/performance` (or your configured path)
   - Verify new columns are visible
   - Test export functionality
   - Test record management (if enabled)

4. **Test commands**:
   ```bash
   php bin/console nowo:performance:create-table --help
   php bin/console nowo:performance:diagnose
   php bin/console nowo:performance:check-dependencies
   ```

### Troubleshooting

**Issue: Missing columns in database**

- **Solution**: Run the migration or use `nowo:performance:create-table --force`

**Issue: Export buttons not visible**

- **Solution**: Clear Symfony cache: `php bin/console cache:clear`

**Issue: Review system not working**

- **Solution**: Enable it in configuration: `enable_review_system: true`

**Issue: Query tracking not working**

- **Solution**: Run `php bin/console nowo:performance:diagnose` to check configuration

**First-time installation:** If you are installing the bundle for the first time, see [INSTALLATION.md](INSTALLATION.md).
