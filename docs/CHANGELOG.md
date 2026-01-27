# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
  - Modal de Tailwind ahora solo se incluye cuando `template == 'tailwind'`
  - Modal de Bootstrap siempre disponible cuando se usa Bootstrap
  - Mejorada la detección de Bootstrap vs Tailwind en JavaScript
  - Previene que el modal de Tailwind aparezca desmaquetado en proyectos Bootstrap
  - El modal correcto se muestra según el template activo
- **Division by zero error** - Fixed `DivisionByZeroError` in `PerformanceAnalysisService::calculateCorrelation()`
  - Verificación mejorada de varianzas antes de calcular `sqrt()`
  - Verificación de `NaN` e `INF` en el denominador
  - Verificación del resultado final de la correlación
  - Retorna `null` cuando no se puede calcular la correlación de forma segura
  - Previene errores cuando los datos tienen varianza cero o valores constantes
- **Data Collector "Unknown" status** - Fixed issue where "Data Saved to Database" showed "⚠ Unknown"
  - `wasRecordNew()` y `wasRecordUpdated()` ahora leen primero de las propiedades (establecidas en `onKernelTerminate`)
  - `setRecordOperation()` ahora actualiza tanto las propiedades como el array `$this->data`
  - `wasUpdated` ahora siempre es `true` cuando se incrementa `accessCount` (porque actualiza `last_accessed_at`)
  - El collector ahora muestra correctamente "✓ Saved (new record created)" o "✓ Saved (existing record updated)"

### Changed
- **PerformanceMetricsService** - Improved `wasUpdated` logic
  - `wasUpdated` ahora siempre es `true` cuando se actualiza un registro existente
  - Esto es correcto porque `incrementAccessCount()` siempre actualiza `last_accessed_at`
  - Mejora la precisión del estado mostrado en el Web Profiler

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

## [0.0.2] - 2025-01-27

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

## [0.0.1] - 2025-01-26

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
