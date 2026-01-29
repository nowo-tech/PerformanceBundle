# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

_Nothing yet._

---

## [2.0.5] - 2026-01-28

### Added
- **Access records: logged-in user** – When access records and `track_user` are enabled, each `RouteDataRecord` can store the logged-in user: `user_identifier` (e.g. username, email from `UserInterface::getUserIdentifier()`) and `user_id` (stringified ID from `User::getId()` if present). New columns `user_identifier` (VARCHAR 255, nullable) and `user_id` (VARCHAR 64, nullable) on `routes_data_records`. Config: `nowo_performance.track_user` (default `false`). The Access Records UI shows a User column; CSV and JSON exports include both fields. Run `php bin/console nowo:performance:create-table --update` or `nowo:performance:sync-schema` after updating.
- **Makefile: validate-translations** – New target `make validate-translations` runs the translation YAML validation script inside the PHP container (starts the container if needed).
- **Translation validation: block-aware duplicate detection** – The script `scripts/validate-translations-yaml.php` now treats duplicate keys only when they appear in the same parent block. The same key under different parents (e.g. `statistics.max_queries` and `filters.max_queries`) is no longer reported as a duplicate.

### Fixed
- **Security autowiring (Symfony 7/8)** – Resolved "Cannot autowire service PerformanceMetricsSubscriber: argument $security has type Symfony\Component\Security\Core\Security but this class was not found." The Security dependency is now optional (`?security.helper`); the bundle works when SecurityBundle is not installed or when using Symfony 7+ where the old Security class was removed. User tracking is applied only when `track_user` is true and the security helper is available.

### Added (tests)
- **PerformanceMetricsSubscriberSecurityTest** – Tests for `track_user` with security null, with user (identifier/id), and user without `getId()`.
- **ValidateTranslationsYamlTest** – Nested-block duplicate detection, three-level nesting, comments/blank lines, default dir when no argument, duplicate at root level.
- **RecordMetricsMessageTest** – getUserIdentifier/getUserId getters; **RouteDataRecordTest** – userIdentifier/userId setters and getters.
- **DeleteRecordsByFilterRequestTest**, **StatisticsEnvFilterTest**, **ArrayExtensionTest**, **PerformanceAlertTest**, **RecordFiltersTest**, **NowoPerformanceBundleTest**, **RouteDataWithAggregatesTest**, **Event/Twig component tests** – Additional edge-case and coverage tests.

See [UPGRADING](UPGRADING.md#upgrading-to-205-2026-01-28) for migration steps.

---

## [2.0.4] - 2026-01-28

### Added
- **Access records: HTTP Referer** – When access records are enabled, each `RouteDataRecord` now stores the HTTP `Referer` header (page that linked to the request). New column `referer` (VARCHAR 2048, nullable) on `routes_data_records`. The Access Records UI shows a Referer column (with link); CSV and JSON exports include the referer. Run `php bin/console nowo:performance:create-table --update` or `nowo:performance:sync-schema` after updating.
- **Per-route: disable saving access records** – When access records are enabled, you can now turn off saving access records for individual routes. In the review/config modal for each route (same form as "Mark as reviewed"), a checkbox **"Save access records for this route"** appears. If unchecked, the bundle still updates aggregate metrics (RouteData) for that route but does not create new `RouteDataRecord` rows. Useful for high-traffic or internal routes where you want aggregates but not per-request history. New column `save_access_records` (boolean, default true) on `routes_data`. Run `php bin/console nowo:performance:create-table --update` or `nowo:performance:sync-schema` after updating.

See [UPGRADING](UPGRADING.md#upgrading-to-204-2026-01-28) for migration steps.

---

## [2.0.3] - 2026-01-29

### Fixed
- **Request ID on sub-requests** – Fixed "undefined method getMainRequest of Request" when running on Symfony or HttpFoundation versions where `Request::getMainRequest()` does not exist. The subscriber now uses `RequestStack::getMainRequest()` (or `getMasterRequest()` on older Symfony) to resolve the main request for sharing the request ID with sub-requests. No schema or config changes.

---

## [2.0.2] - 2026-01-29

### Added
- **Request ID deduplication** – When access records are enabled, the bundle assigns a unique `request_id` per HTTP request (shared between main and sub-requests). At most one `RouteDataRecord` is created per logical request, avoiding duplicate entries when multiple `TERMINATE` events fire (e.g. main request + fragment).
- **`routes_data_records`** – New optional column `request_id` (VARCHAR 64, nullable, unique). Existing records keep `request_id = NULL`. Run `php bin/console nowo:performance:sync-schema` or your Doctrine migrations after updating.
- **Translation YAML validation** – Script `scripts/validate-translations-yaml.php` checks translation YAML files for valid syntax and duplicate keys. CI runs it in the test job. Composer: `composer validate-translations`; included in `composer qa`.
- **Collector & diagnose: records table status** – When `enable_access_records` is true, the Web Profiler Performance panel shows **Access Records Table** (exists, complete, missing columns). TableStatusChecker gains `recordsTableExists()`, `recordsTableIsComplete()`, `getRecordsMissingColumns()`, `getRecordsTableName()`, `isAccessRecordsEnabled()`. CLI `nowo:performance:diagnose` includes a **Database Tables** section (main table + records table) with missing columns. Missing `request_id` (or any entity column) is detected and the UI suggests running `sync-schema` or `create-records-table --update`.

### Fixed
- **CreateRecordsTableCommand** – Creating the records table from scratch now sets `AUTO_INCREMENT` on the `id` column for MySQL/MariaDB. `--update` now creates missing **unique constraints** (e.g. `uniq_record_request_id` on `request_id`) and uses the same operation order as the main table (Drop → Add → Update).
- **CreateTableCommand** – `addMissingIndexes()` now uses `getSchemaManager()` for DBAL 2.x compatibility.

See [UPGRADING](UPGRADING.md#upgrading-to-202-2026-01-29) for migration steps.

---

## [2.0.1] - 2026-01-28

### Added
- **Review system: edit existing review** – Routes already marked as reviewed can now be edited. An "Edit review" button (pencil icon) appears for reviewed routes; the same modal opens with the form pre-filled with current values (Queries improved, Time improved). Modal title and submit button label differ when editing ("Edit Review" / "Update Review"). Flash message "Review updated" when saving an existing review. New translation keys in all locales: `routes_table.edit_review`, `review.modal_title_edit`, `review.edit_review`, `flash.review_updated`.

### Fixed
- **Routes table: sort by Memory usage** – Sorting by the Memory usage column now uses the numeric value (int bytes). `PerformanceController::getSortValue()` now includes a `memoryUsage` case so the table orders correctly instead of falling back to request time.
- **Charts: initialization after DOM** – Chart scripts (dashboard Performance Trends, Statistics histograms, Charts component) run inside `DOMContentLoaded` and check for `Chart` and canvas before use. Avoids console errors when Chart.js is loaded in `{% block scripts %}` after the inline script in the content.

### Changed
- **Routes table: Status Codes and Access Count** – Removed the "Total responses" line from the Status Codes column (same value as Access Count). Status Codes and Access Count columns are now adjacent for easier reading.
- **Routes table: View access records** – The link to access records now uses the Symfony UX Icons eye icon (`bi:eye`) instead of the 👁 emoji.

---

## [2.0.0] - 2026-01-28

**Breaking:** Entity normalization. See [V2_MIGRATION.md](V2_MIGRATION.md) and [ENTITY_NORMALIZATION_PLAN.md](ENTITY_NORMALIZATION_PLAN.md).

### Removed (from RouteData)
- `totalQueries`, `requestTime`, `queryTime`, `memoryUsage`, `accessCount`, `statusCodes`, `updatedAt` — metrics move to aggregates from `RouteDataRecord` or a dedicated aggregates layer.

### Added (RouteDataRecord)
- `totalQueries`, `queryTime`, `memoryUsage` per access record.

### Added
- **Export access records (CSV / JSON)** – On the Access Records page, export individual `RouteDataRecord` rows via **Export Records (CSV)** and **Export Records (JSON)**. Uses the same filters as the records view (env, date range, route, status code, query time, memory). Requires `enable_access_records: true`. New routes: `nowo_performance.export_records_csv`, `nowo_performance.export_records_json`.
- **Access Records: query time, memory, filters** – Access Records page shows columns Queries, Query Time, Memory (formatted). Form filters: min/max query time (s), min/max memory (MB). Export and pagination preserve these filter params. RecordFilters and RouteDataRecordRepository support `minQueryTime`, `maxQueryTime`, `minMemoryUsage`, `maxMemoryUsage`.
- **Index dashboard: Status Codes column** – Routes table shows status codes with percentage and total responses per code (descending), only codes that have records. Total responses and error-rate warning when >10% non-2xx.
- **Schema sync command** – `nowo:performance:sync-schema` syncs both `routes_data` and `routes_data_records` with entity metadata (add missing, alter differing, optional drop with `--drop-obsolete`). Primary key `id` is never dropped.
- **Drop obsolete option** – `--drop-obsolete` for `nowo:performance:create-table`, `nowo:performance:create-records-table`, and `nowo:performance:sync-schema`. Drops columns that exist in DB but not in the entity.
- **Create-records-table column updates** – `--update` now also alters existing columns when type, nullable or default differ (previously only added missing columns).
- **Translations** – New/updated keys for status codes, total responses, error rate, query time, memory usage, min/max query time and memory in all supported locales.

### Changed
- RouteData only holds identity (env, name, httpMethod, params) and metadata (createdAt, lastAccessedAt, reviewed, reviewedAt, reviewedBy, queriesImproved, timeImproved). Dashboard, API, notifications and exports use aggregated data or records instead of RouteData getters for metrics.
- **Collector diagnostics when disabled** – Web Profiler Performance panel shows full diagnostics even when tracking is disabled (route, request time, query count, query time, environment, processing mode, table status, dependency status). Subscriber always sets `configured_environments`, `current_environment`, and `route_name` in the collector before any early return.
- RebuildAggregatesCommand updates RouteData `lastAccessedAt` from RouteDataRecord; metrics live in records.

### Fixed
- **Advanced Performance Statistics** – Histograms not rendering: chart script moved to `scripts_extra` block so Chart.js is loaded before use; creation wrapped in IIFE with `typeof Chart !== 'undefined'` check.
- **Access statistics with empty route filter** – When `route=` was present in the URL, the controller passed an empty string and no rows matched. Empty route is now normalized to `null` so statistics are returned for all routes.

### Added (tests)
- Models: `RecordFiltersTest` (query time/memory filters), `DeleteRecordsByFilterRequestTest` (min/max query time and memory), `StatisticsEnvFilterTest`, `ClearPerformanceDataRequestTest`.
- Form types: `StatisticsEnvFilterTypeTest`, `ClearPerformanceDataTypeTest`, `DeleteRecordTypeTest`; extended `RecordFiltersTypeTest` and `DeleteRecordsByFilterTypeTest` for new fields.
- Repository: `RouteDataRecordRepositoryAdvancedTest` – `getPaginatedRecords` and `deleteByFilter` with query time and memory filters; explicit calls with full parameter list.
- DataCollector: `disabledReason`, `configuredEnvironments` / `currentEnvironment`, diagnostics when disabled, table/dependency status, `getProcessingMode`.
- `PerformanceMetricsSubscriberCollectorDiagnosticsTest`, `RouteDataWithAggregatesTest`, `SyncSchemaCommandTest`.

### Documentation
- COMMANDS.md: `--drop-obsolete` and `nowo:performance:sync-schema`.
- INSTALLATION.md: Step 5 lists main commands; note on sync-schema after entity changes.
- UPGRADING.md: "Upgrading to 2.0.0" with migration steps; removed duplicate sections.
- PHPDoc: all bundle PHP docblocks and comments in English (constructors, params, returns, form types, models, commands, examples).

## [1.0.8] - 2026-01-27

### Changed
- **Default environments now include production** - Changed default value for `environments` configuration
  - Default changed from `['dev', 'test']` to `['prod', 'dev', 'test']`
  - Bundle now tracks performance in production by default
  - This is more appropriate for a performance monitoring bundle
  - Existing configurations are not affected (only applies when not explicitly configured)
  - Fixes issue where production environments were not tracked by default

### Fixed
- **Demo environments configuration** - Fixed demo projects to allow `APP_ENV=prod`
  - Updated `docker-compose.yml` in both Symfony 7 and Symfony 8 demos
  - Changed from hardcoded `APP_ENV=dev` to `APP_ENV=${APP_ENV:-dev}`
  - Added `APP_DEBUG=${APP_DEBUG:-0}` for better environment control
  - Updated Makefiles to include `APP_DEBUG=1` in default `.env` creation
  - Removed `when@dev:` condition from `nowo_performance.yaml` in demos
  - Configuration now applies to all environments, not just `dev`

### Added
- **Comprehensive environment configuration tests** - Added extensive test coverage for environment handling
  - 5 tests for `PerformanceExtension` environment defaults and configurations
  - 6 tests for `Configuration` environment defaults and edge cases
  - 4 tests for `PerformanceMetricsSubscriber` environment filtering
  - 3 tests for `PerformanceController` diagnose suggestions
  - All tests verify the new default includes `prod`
  - Tests cover edge cases like empty configs, single environments, and custom environments
  - Updated existing tests to reflect new default values

## [1.0.7] - 2026-01-27

### Added
- **Enhanced debug logging** - Added comprehensive logging throughout the performance tracking flow
  - Detailed logs in `onKernelRequest` showing collector state and configuration
  - Detailed logs in `onKernelTerminate` showing start state, route checks, and environment validation
  - Logs for request time calculation (when tracked and when not)
  - Logs for query metrics calculation (count and time)
  - Logs for memory usage calculation
  - Logs when attempting to save metrics with all details (route, env, method, statusCode, requestTime, queryCount, queryTime, memoryUsage, samplingRate)
  - Logs showing the result of `recordMetrics` (is_new, was_updated)
  - Logs in `recordMetrics` showing start state and async/sync mode
  - Logs in `recordMetricsSync` showing search for existing records, creation of new records, access count increments, shouldUpdate checks, before/after flush, and success/error states
  - All logs include route name and environment for easy filtering
  - Helps diagnose why data might not be saved in production environments

### Added
- **Comprehensive test coverage for debugging** - Added 33 new tests for logging and edge cases
  - 13 tests for `PerformanceMetricsSubscriber` debug logging
  - 12 tests for `PerformanceMetricsService` debug logging
  - 8 tests for edge cases where data might not be saved
  - Tests verify all debug logs are generated correctly
  - Tests cover scenarios like collector disabled between REQUEST and TERMINATE, route name lost, environment changed, null startTime/startMemory, all metrics null, and sampling skips
  - Improves reliability and helps diagnose production issues

## [1.0.6] - 2026-01-27

### Fixed
- **Symfony 7 compatibility** - Fixed compatibility issue with Symfony 7.x commands
  - Moved `help` parameter from `#[AsCommand]` attribute to `configure()` method using `setHelp()`
  - Fixed "Unknown named parameter $help" error in all commands
  - All commands now use `configure()` method for help text (compatible with Symfony 6.x and 7.x)
  - Commands affected: `check-dependencies`, `diagnose`, `create-table`, `create-records-table`, `set-route`

### Added
- **Comprehensive test coverage** - Added 60+ new tests for improved reliability
  - 7 tests for `getChartData()` method covering all metrics (requestTime, queryTime, totalQueries, memoryUsage)
  - 5 tests for `accessStatistics()` method covering disabled states, exceptions, and date ranges
  - 7 tests for subscriber detection in diagnose page
  - 13 tests for `getAvailableEnvironments()` method (already in v1.0.4)
  - 16 tests for export functionality (CSV and JSON) (already in v1.0.4)
  - 12 tests for `buildFiltersFromRequest()` method (already in v1.0.4)
  - Improves code quality and ensures edge cases are properly handled

## [1.0.5] - 2026-01-27

### Fixed
- **Subscriber detection in diagnose** - Fixed issue where diagnose page showed subscriber as not registered even when it was
  - Improved subscriber detection with multiple fallback methods
  - Checks event dispatcher listeners for both REQUEST and TERMINATE events
  - Falls back to container service lookup
  - Final fallback to class existence and interface verification
  - Now correctly detects subscriber registration in all scenarios
  - Added detection method information to diagnostic output

### Added
- **Test coverage for subscriber detection** - Added 7 new tests for diagnose subscriber detection
  - Tests for detection via event dispatcher listeners (REQUEST and TERMINATE)
  - Tests for detection via container service lookup
  - Tests for fallback to class existence check
  - Tests for error handling scenarios
  - Tests for detection method reporting

## [1.0.4] - 2026-01-27

### Fixed
- **Environment filter empty when no data** - Fixed issue where environment filter dropdown was empty when no data was recorded yet
  - `getAvailableEnvironments()` now uses `allowedEnvironments` from configuration as fallback when database is empty
  - Falls back to current environment if `allowedEnvironments` is empty
  - Final fallback to default environments (`['dev', 'test', 'prod']`) if all else fails
  - Ensures filter always has options even when no performance data has been recorded
  - Fixes issue where users couldn't select an environment in the dashboard filter

### Added
- **Comprehensive test coverage** - Added 41 new tests for improved reliability
  - 13 tests for `getAvailableEnvironments()` method covering cache, database, and fallback scenarios
  - 16 tests for export functionality (CSV and JSON) covering disabled states, access control, empty data, and exceptions
  - 12 tests for `buildFiltersFromRequest()` method covering all filter types and edge cases
  - Improves code quality and ensures edge cases are properly handled

## [1.0.3] - 2026-01-27

### Fixed
- **Driver name detection with middleware** - Fixed error when getting driver name from wrapped drivers
  - Fixed "Attempted to call an undefined method named 'getName' of class 'AbstractDriverMiddleware'" error
  - Created `getDriverName()` helper method in `PerformanceController` that handles wrapped drivers
  - Uses reflection to access underlying driver when wrapped with middleware
  - Falls back to platform class name inference when direct methods are unavailable
  - Compatible with all DBAL versions and middleware configurations
  - Fixes error in diagnose command when driver is wrapped with `QueryTrackingMiddleware`

## [1.0.2] - 2026-01-27

### Fixed
- **Modal dependency display** - Fixed modal rendering issues in Bootstrap/Tailwind templates
  - Tailwind modal is now only included when `template == 'tailwind'`
  - Bootstrap modal always available when using Bootstrap
  - Improved Bootstrap vs Tailwind detection in JavaScript
  - Prevents Tailwind modal from appearing broken in Bootstrap projects
  - The correct modal is shown according to the active template
- **Division by zero error** - Fixed `DivisionByZeroError` in `PerformanceAnalysisService::calculateCorrelation()`
  - Improved variance checks before computing `sqrt()`
  - Check for `NaN` and `INF` in the denominator
  - Check of the final correlation result
  - Returns `null` when correlation cannot be computed safely
  - Prevents errors when data has zero variance or constant values
- **Data Collector "Unknown" status** - Fixed issue where "Data Saved to Database" showed "⚠ Unknown"
  - `wasRecordNew()` and `wasRecordUpdated()` now read from the properties first (set in `onKernelTerminate`)
  - `setRecordOperation()` now updates both the properties and the `$this->data` array
  - `wasUpdated` is now always `true` when `accessCount` is incremented (because it updates `last_accessed_at`)
  - The collector now correctly shows "✓ Saved (new record created)" or "✓ Saved (existing record updated)"

### Changed
- **PerformanceMetricsService** - Improved `wasUpdated` logic
  - `wasUpdated` is now always `true` when an existing record is updated
  - This is correct because `incrementAccessCount()` always updates `last_accessed_at`
  - Improves the accuracy of the status shown in the Web Profiler

## [1.0.1] - 2026-01-27

### Added
- **Dependency detection and management** - Extended DependencyChecker to detect optional dependencies
  - Detects Symfony Messenger availability for async metrics recording
  - Detects Symfony Mailer availability for email notifications
  - Detects Symfony HttpClient availability for Slack, Teams, and webhook notifications
  - Provides information about missing dependencies with installation commands
  - Modal informativo en el dashboard para mostrar dependencias faltantes
  - Compatible con Bootstrap y Tailwind CSS templates
- **Automatic routes file creation** - Symfony Flex recipe now creates `config/routes/nowo_performance.yaml`
  - Routes are automatically imported when installing the bundle
  - Uses configured prefix and path from bundle configuration
  - Can be customized or overridden by users
- **Performance optimizations** - Significant reduction in database queries
  - Table status caching: `TableStatusChecker` now caches table existence and completeness (5 minutes TTL)
  - Reduces ~10 `information_schema` queries per request to 0 (cached)
  - Optional ranking queries: New `enable_ranking_queries` configuration option
  - When disabled, eliminates 3 ranking queries per request
  - Overall reduction: ~90-95% fewer queries from the bundle per request
- **Generic cache methods** - Added generic cache methods to PerformanceCacheService
  - `getCachedValue()` - Get any cached value by key
  - `cacheValue()` - Cache any value with custom TTL
  - `invalidateValue()` - Invalidate cached value by key
  - Enables caching of any bundle data, not just statistics

### Changed
- **DataCollector ranking queries** - Made ranking queries optional and configurable
  - New configuration: `nowo_performance.dashboard.enable_ranking_queries` (default: `true`)
  - When disabled, ranking information is not calculated in WebProfiler
  - Reduces database load for applications that don't need ranking information
  - Backward compatible: enabled by default
- **TableStatusChecker** - Now uses cache to avoid expensive `information_schema` queries
  - Caches `tableExists()` results for 5 minutes
  - Caches `tableIsComplete()` results for 5 minutes
  - Automatically uses PerformanceCacheService if available
  - Falls back to direct queries if cache is not available

### Fixed
- **CreateTableCommand DBAL 3.x compatibility** - Fixed error when adding columns with `--update`
  - Added required `'name'` key to column array for `getSQLDeclaration()` in DBAL 3.x
  - Fixes "Undefined array key 'name'" error when running `nowo:performance:create-table --update`
  - Added proper column properties (precision, scale, unsigned, fixed) to column definition
  - Maintains full backward compatibility with DBAL 2.x
- **Doctrine DBAL deprecation warnings** - Fixed deprecation warnings in CreateTableCommand and TableStatusChecker
  - Replaced deprecated `AbstractPlatform::quoteIdentifier()` with helper method compatible with DBAL 2.x and 3.x
  - Replaced deprecated `Column::getName()` with `getColumnName()` helper method
  - Replaced deprecated `AbstractAsset::getName()` (for Index objects) with `getAssetName()` helper method
  - All helper methods use `getQuotedName()` for DBAL 3.x and fallback to `getName()` for DBAL 2.x
  - Eliminates deprecation warnings when running `nowo:performance:create-table --update`
  - Maintains full backward compatibility with DBAL 2.x

## [1.0.0] - 2026-01-27

### Added
- **Sub-request tracking support** - Added `track_sub_requests` configuration option
  - Allows tracking performance metrics for sub-requests (ESI, fragments, includes) in addition to main requests
  - Default: `false` (maintains backward compatibility, only main requests tracked)
  - When enabled, tracks both main requests and sub-requests separately
  - Useful for monitoring ESI performance, fragment rendering, and debugging sub-request bottlenecks
  - Includes request type (main/sub) in logging for better diagnostics

## [0.0.7] - 2026-01-27

### Added
- **Environment information in PerformanceDataCollector** - Added environment configuration and current environment display
  - Shows configured environments (where tracking is enabled) in collector toolbar and panel
  - Displays current system environment with status indicator
  - Visual indicators: green if current environment is enabled, red if disabled
  - Helps diagnose why tracking might be disabled (environment not in allowed list)
  - Information available in both toolbar tooltip and detailed panel view

### Fixed
- **Environment detection in PerformanceMetricsSubscriber** - Improved environment detection robustness
  - Enhanced environment detection to try multiple methods: kernel, request server, `$_SERVER`, `$_ENV`
  - Added debug logging to show which method was used for environment detection
  - More reliable fallback chain to ensure environment is always detected
  - Fixes issues when kernel is not available or `APP_ENV` is not in expected location
- **PerformanceDataCollector visibility** - Collector now always visible in profiler toolbar
  - Collector displays even when disabled to provide status information
  - Shows "Disabled" status when tracking is disabled
  - Displays reason for being disabled (bundle disabled or environment not tracked)
  - Still shows table status even when disabled
  - Fixes issue where collector was completely hidden when disabled

### Changed
- **COMPATIBILITY.md documentation** - Updated with discovered compatibility information
  - Added detailed information about DoctrineBundle 2.17.1 not supporting YAML middleware config
  - Expanded middleware registration section with universal approach explanation
  - Added troubleshooting section with common issues and solutions
  - Updated compatibility matrix with tested versions (2.17.1)
  - Documented reflection-based middleware application approach
  - Added error messages and solutions for YAML configuration issues

## [0.0.6] - 2026-01-27

### Added
- **Tests for QueryTrackingConnectionSubscriber** - Added comprehensive test coverage
  - Tests for `getSubscribedEvents()` method
  - Tests for `onKernelRequest()` with different configurations (enabled/disabled, trackQueries on/off)
  - Tests for error handling when connection is not found
  - Tests for query tracking reset functionality
  - Total: 6 new tests covering all scenarios

## [0.0.5] - 2026-01-27

### Fixed
- **DoctrineBundle middleware configuration** - Removed YAML middleware configuration completely
  - YAML middleware options (`middlewares` and `yamlMiddleware`) are not reliably available across all DoctrineBundle versions
  - Some versions (like 2.17.1) do not support these options, causing "Unrecognized option" errors
  - Changed to use only reflection-based middleware application via `QueryTrackingConnectionSubscriber`
  - This approach works consistently across all DoctrineBundle versions (2.x and 3.x)
  - No YAML configuration required, avoiding compatibility issues
  - Updated documentation to reflect this change
- **QueryTrackingConnectionSubscriber getSubscribedEvents method** - Added required method for EventSubscriberInterface
  - Added `getSubscribedEvents()` method to comply with `EventSubscriberInterface`
  - Events are registered via `#[AsEventListener]` attributes, but method is required by interface
  - Fixes "Class contains 1 abstract method" error

## [0.0.4] - 2026-01-27

### Fixed
- **PerformanceDataCollector Throwable import** - Fixed fatal error when autoloading PerformanceDataCollector
  - Added missing `use Throwable;` import statement
  - Fixes "Class 'Nowo\PerformanceBundle\DataCollector\Throwable' not found" error
  - Resolves ReflectionException during container compilation

## [0.0.3] - 2026-01-27

### Fixed
- **DoctrineBundle middleware configuration** - Fixed compatibility issue with `yamlMiddleware` option
  - Changed from using `yamlMiddleware` to always using `middlewares` for DoctrineBundle 2.x
  - `yamlMiddleware` is not reliably available across all DoctrineBundle 2.x versions
  - `middlewares` is more widely supported and works consistently across all 2.x versions
  - Fixes "Unrecognized option 'yamlMiddleware'" errors when installing the bundle
  - Updated documentation to reflect this change

## [0.0.2] - 2026-01-27

### Added
- **Comprehensive test coverage for CreateTableCommand** - Added 14+ new tests
  - Tests for AUTO_INCREMENT detection and fixing
  - Tests for foreign key handling during AUTO_INCREMENT fixes
  - Tests for column type, length, and default value differences
  - Tests for boolean and numeric default value comparison
  - Tests for nullable differences detection
  - Tests for missing indexes addition
  - Tests for platform compatibility (MySQL vs PostgreSQL)
  - Tests for error handling when restoring foreign keys
  - Total: 20+ tests covering all edge cases

### Changed
- **Symfony Flex recipe** - Added uninstall hook
  - Automatically removes bundle from `config/bundles.php` when uninstalling
  - Automatically removes configuration files (`config/packages/nowo_performance.yaml` and dev-specific config)
  - Proper cleanup when running `composer remove nowo-tech/performance-bundle`

### Fixed
- **AUTO_INCREMENT for id column** - Fixed issue where `id` column was not configured as AUTO_INCREMENT in MySQL/MariaDB
  - Command `nowo:performance:create-table --update` now automatically detects and fixes missing AUTO_INCREMENT
  - Handles foreign key constraints by temporarily dropping and restoring them during column modification
  - Improved regex patterns for adding AUTO_INCREMENT when creating tables via SchemaTool
  - Enhanced column definition generation to include AUTO_INCREMENT for identifier columns
  - Detects autoincrement status from entity metadata (GeneratedValue strategy)
  - Fixes "Field 'id' doesn't have a default value" errors when inserting records
  - Query INFORMATION_SCHEMA correctly using JOIN between KEY_COLUMN_USAGE and REFERENTIAL_CONSTRAINTS
  - Properly restores foreign keys with original UPDATE_RULE and DELETE_RULE after fixing AUTO_INCREMENT

## [0.0.1] - 2026-01-26

### Added
- Initial release
- Automatic route performance tracking via event subscribers
- Database query counting and execution time tracking
- Request execution time measurement
- **HTTP Method tracking** - Track HTTP method (GET, POST, PUT, DELETE, etc.) for each route
  - New field in `RouteData` entity: `httpMethod` (STRING, nullable)
  - Automatically captured from request
  - Displayed in dashboard with color-coded badges
  - Included in CSV and JSON exports
- **Memory usage tracking** - Track peak memory consumption per route
  - New field in `RouteData` entity: `memoryUsage` (BIGINT, nullable)
  - Automatically tracked during request processing
  - Displayed in dashboard and exports
  - Can be set via `nowo:performance:set-route --memory` command
- **Route access frequency tracking** - Track how often routes are accessed
  - New fields: `accessCount` (INTEGER, default: 1) and `lastAccessedAt` (DATETIME_IMMUTABLE, nullable)
  - Automatically incremented on each route access
  - Displayed in dashboard with badge showing access count
  - Can be sorted by access count in dashboard
- **Record management system** - Delete individual records from the dashboard
  - New configuration option: `nowo_performance.dashboard.enable_record_management` (boolean, default: `false`)
  - Delete button for each record in the dashboard
  - CSRF protection for delete operations
  - Role-based access control (uses dashboard roles)
- **Record review system** - Comprehensive review system for performance records
  - New configuration option: `nowo_performance.dashboard.enable_review_system` (boolean, default: `false`)
  - New fields in `RouteData`: `reviewed` (boolean), `reviewedAt` (DATETIME_IMMUTABLE), `queriesImproved` (boolean, nullable), `timeImproved` (boolean, nullable), `reviewedBy` (string, nullable)
  - Review modal in dashboard to mark records as reviewed
  - Visual indicators for review status and improvements
  - Indexes added for `reviewed` and `reviewedAt` fields
- **Data export functionality** - CSV and JSON export capabilities
  - Export buttons in dashboard (CSV and JSON)
  - Exports respect current filters and sorting
  - Includes all fields: route name, HTTP method, environment, metrics, memory usage, access count, review status
  - Proper CSV encoding (UTF-8 with BOM)
  - JSON export with metadata (environment, export date)
- **Performance ranking in Web Profiler** - Ranking information in PerformanceDataCollector
  - Shows access frequency (how many times route was accessed)
  - Shows ranking position by request time (1 = slowest)
  - Shows ranking position by query count (1 = most queries)
  - Displays total routes count for context
  - Visual indicators for slow routes (top 3) and routes needing attention (top 10)
  - Shows database storage status and table existence
  - Indicates whether current route's performance data is being saved to database
  - Displays reason if data is not being saved (disabled, environment not tracked, etc.)
  - Always visible in toolbar, even when disabled, to provide status information
- **New console commands**:
  - `nowo:performance:create-table` - Create the performance metrics database table with all indexes
  - `nowo:performance:diagnose` - Comprehensive diagnostic report of bundle configuration and status
  - `nowo:performance:check-dependencies` - Check status of optional dependencies (e.g., Symfony UX TwigComponent)
- **Database table creation command** - `nowo:performance:create-table` command for easy table setup
  - Checks if table exists before creating
  - `--force` option to drop and recreate table
  - `--update` option to add missing columns
  - `--connection` option to specify Doctrine connection
  - Shows SQL statements being executed
  - Provides alternative migration commands if needed
- **Advanced filtering in dashboard** - Enhanced filtering capabilities
  - Filter by route name pattern (LIKE search)
  - Filter by multiple route names (OR condition)
  - Filter by request time range (min/max)
  - Filter by query count range (min/max)
  - Filter by query time range (min/max)
  - Filter by date range (createdAt)
  - Sort by access count
- **Caching layer** - `PerformanceCacheService` for improved dashboard performance
  - Caches statistics calculations
  - Caches environment lists
  - Automatic cache invalidation on metrics updates
  - Configurable TTL (default: 1 hour)
- **Symfony UX Twig Components support** - Optional integration with Symfony UX TwigComponent
  - Uses Twig Components when `symfony/ux-twig-component` is installed
  - Falls back to traditional Twig includes if not available
  - Better performance and maintainability with components
  - Dependency validation with informative messages
- **Chart.js integration** - Interactive charts in dashboard
  - Performance trends visualization
  - API endpoint `/api/chart-data` for chart data
  - Supports filtering by environment and route
  - Alert when no data is available
- **Role-based access control for performance dashboard** - Restrict access to the performance dashboard
  - New configuration option: `nowo_performance.dashboard.roles` (array, default: `[]`)
  - Access control is enforced in `PerformanceController::index()` method
  - Supports multiple roles with OR logic (user needs at least one role)
- **QueryTrackingMiddleware for DBAL 3.x compatibility** - Custom DBAL middleware to intercept and track database queries
  - Automatic query interception via DBAL middleware
  - Static methods for query count and execution time tracking
  - Multiple fallback strategies for query metrics collection
  - Support for Doctrine DataCollector as fallback
  - Version-aware middleware registration (DoctrineBundle 2.x vs 3.x)
  - `QueryTrackingConnectionSubscriber` for automatic middleware application
- Route data persistence in database
- Environment-specific metrics (dev, test, prod)
- Configurable route ignore list
- Command to manually set/update route metrics (`nowo:performance:set-route`)
- Support for multiple Doctrine connections
- Configurable table name for storing metrics
- Performance dashboard with filtering and sorting capabilities
- WebProfiler integration with PerformanceDataCollector
- Symfony 6.1+, 7.x, and 8.x compatibility
- Comprehensive test coverage (123 tests, 34% code coverage)
- Full documentation
- **Date format configuration** - Customizable date formats for dashboard display
  - New configuration: `nowo_performance.dashboard.date_formats.datetime` (default: 'Y-m-d H:i:s')
  - New configuration: `nowo_performance.dashboard.date_formats.date` (default: 'Y-m-d H:i')
- **Table status checking** - Service to verify database table existence and structure
  - `TableStatusChecker` service
  - Displays warnings in dashboard if table is missing or incomplete
  - Shows table status in Web Profiler collector
- **Symfony Flex recipe** - Automatic bundle configuration via Symfony Flex
  - Recipe for automatic setup
  - Default configuration files
  - Post-install instructions
- **Advanced Performance Statistics page** - Comprehensive statistical analysis page
  - Detailed statistics for all metrics (Request Time, Query Time, Query Count, Memory Usage, Access Count)
  - Statistical measures: Mean, Median, Mode, Standard Deviation
  - Percentiles: P25, P50, P75, P90, P95, P99
  - Distribution histograms with Chart.js for each metric
  - Outlier detection and identification
  - Routes needing attention section (top 95th percentile)
  - Key insights and interpretation guides for each metric
  - Contextual information explaining what each chart shows
  - Visual indicators for optimization targets
  - Metric-specific guidance (e.g., N+1 detection, memory optimization, hot path identification)
- **Symfony Translation component support** - Full internationalization support
  - `symfony/translation` as bundle dependency
  - Complete translation files for English, Spanish, Czech, Turkish, and Polish
  - All UI elements are translatable
  - Translation keys for dashboard, filters, statistics, and messages
  - Demo applications configured with translation support
- **Comprehensive test suite** - Extensive test coverage for all components
  - Unit tests for all commands (CreateTableCommand, CheckDependenciesCommand, DiagnoseCommand, SetRouteMetricsCommand)
  - Unit tests for all forms (PerformanceFiltersType, ReviewRouteDataType)
  - Unit tests for all events (BeforeMetricsRecordedEvent, AfterMetricsRecordedEvent, BeforeRecordDeletedEvent, etc.)
  - Unit tests for Twig components (RoutesTableComponent, FiltersComponent, StatisticsComponent, ChartsComponent)
  - Unit tests for Twig extensions (IconExtension)
  - Unit tests for DBAL components (QueryTrackingMiddlewareRegistry)
  - Unit tests for repository methods (findByRouteAndEnv, markAsReviewed, deleteById, findAllForStatistics, deleteAll)
  - Unit tests for entity methods (markAsReviewed, __toString)
  - Unit tests for sampling functionality in PerformanceMetricsSubscriber
  - Total: 37 test files, 150+ individual tests

### Changed
- **Improved query metrics collection** - Enhanced `getQueryMetrics()` method with multiple strategies:
  1. QueryTrackingMiddleware (primary method)
  2. Doctrine DataCollector from profiler (fallback)
  3. Request attributes (fallback)
  4. Stopwatch (time only, fallback)
- **Command option shortcut fix** - Removed conflicting shortcut `-q` from `queries` option in `nowo:performance:set-route` command
- **Enhanced command help** - All commands now include comprehensive help text
- **Database schema improvements** - Added composite indexes for better query performance
  - Index on `env + name` for route lookups
  - Index on `env + requestTime` for sorting
  - Index on `env + accessCount` for access frequency sorting
  - Index on `createdAt` for date filtering
  - Index on `reviewed` and `reviewedAt` for review system
- **Dashboard UI improvements** - Enhanced dashboard with new features
  - Export buttons (CSV/JSON) in header
  - Clear All Records button with referer redirect
  - Access count and last accessed date columns
  - Review status column with visual indicators
  - Action buttons (delete, review) conditionally displayed
  - Improved advanced filters layout with visual grouping
  - HTTP Method column with color-coded badges (GET, POST, PUT, DELETE, etc.)
  - Table status warnings when database table is missing or incomplete
  - Informative alerts when chart data is not available
  - Enhanced filter organization with grouped sections and icons
- **Repository methods** - Added new methods to `RouteDataRepository`
  - `getRankingByRequestTime()` - Get ranking position by request time (accepts RouteData entity)
  - `getRankingByQueryCount()` - Get ranking position by query count (accepts RouteData entity)
  - `getTotalRoutesCount()` - Get total number of routes in environment
  - `deleteById()` - Delete single record by ID
  - `markAsReviewed()` - Mark record as reviewed with improvement flags
- **Query optimization** - Optimized duplicate queries in repository methods
  - `getRankingByRequestTime()` and `getRankingByQueryCount()` now accept RouteData entity directly
  - Reduced duplicate `findByRouteAndEnv()` calls
  - Improved dashboard performance by reusing fetched data for statistics
- **Test coverage improvements** - Significantly expanded test suite
  - Added tests for all commands, forms, events, and Twig components
  - Improved coverage for repository methods and entity methods
  - Added tests for sampling and auto-refresh functionality
  - Total test count increased from 19 to 37 test files

### Fixed
- **Query tracking compatibility** - Fixed query tracking to work correctly with DBAL 3.x by implementing a custom middleware instead of relying on deprecated `SQLLogger`
- **Connection interface compatibility** - Fixed `QueryTrackingConnection` to properly implement all required methods from `Doctrine\DBAL\Driver\Connection` interface
- **Server version method signature** - Fixed `getServerVersion()` return type to match `ServerVersionProvider` interface requirements
- **Query tracking registration** - Fixed middleware registration to work with both DoctrineBundle 2.x and 3.x
  - Automatic version detection
  - YAML configuration for DoctrineBundle 2.x
  - Reflection-based registration for DoctrineBundle 3.x
  - Enhanced `QueryTrackingMiddlewareRegistry` for robust middleware application
- **Cache service autowiring** - Fixed `PerformanceCacheService` to handle cases where `cache.app` might not be available
- **Data collector query metrics** - Fixed `PerformanceDataCollector` to fetch query metrics directly from `QueryTrackingMiddleware` if not set
- **Access count and last accessed display** - Fixed Twig templates to use getter methods instead of direct property access
- **Table naming** - Fixed table name to use `routes_data` (plural) consistently
  - Added `#[ORM\Table(name: 'routes_data')]` to RouteData entity
  - Updated `TableNameSubscriber` to correctly override table name
  - Fixed index column names to use snake_case
- **Division by zero** - Fixed division by zero error in statistics calculation when all values are identical
- **Bundle alias** - Fixed bundle alias resolution by renaming bundle class to `NowoPerformanceBundle`
- **Route import** - Fixed route import path to use correct bundle alias `@NowoPerformanceBundle`
- **Twig syntax errors** - Fixed duplicate and missing Twig tags in RoutesTable component
- **Deprecation warnings** - Fixed `Doctrine\DBAL\Schema\AbstractAsset::getName` deprecation warning
- **Bundle class duplication** - Removed duplicate `PerformanceBundle.php` file that caused duplicate bundle registration
- **Symfony Flex recipe** - Fixed recipe to create configuration file automatically on installation
- **Entity method missing** - Added missing `markAsReviewed()` method to `RouteData` entity

### Security
- **Dashboard access control** - The performance dashboard supports role-based access control to restrict access to authorized users only
- **CSRF protection** - All form submissions (delete, review, clear) are protected with CSRF tokens
