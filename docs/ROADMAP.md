# Performance Bundle Roadmap

This document outlines the planned improvements, optimizations, and new features for the Performance Bundle.


## Table of contents

- [🎯 Vision](#vision)
- [📅 Roadmap Timeline](#roadmap-timeline)
  - [Phase 1: Foundation & Core Improvements (Q1 2026)](#phase-1-foundation-core-improvements-q1-2026)
  - [Phase 2: Advanced Features (Q2 2026)](#phase-2-advanced-features-q2-2026)
  - [Phase 3: Enterprise Features (Q3 2026)](#phase-3-enterprise-features-q3-2026)
  - [Phase 4: Developer Experience (Q4 2026)](#phase-4-developer-experience-q4-2026)
- [🔧 Technical Improvements](#technical-improvements)
  - [Architecture](#architecture)
  - [Scalability](#scalability)
  - [Performance](#performance)
- [🎨 UI/UX Improvements](#uiux-improvements)
  - [Dashboard](#dashboard)
  - [Visualization](#visualization)
- [🔌 Integrations](#integrations)
  - [Monitoring Platforms](#monitoring-platforms)
  - [CI/CD](#cicd)
- [📊 Metrics & Analytics](#metrics-analytics)
  - [New Metrics](#new-metrics)
  - [Advanced Analytics](#advanced-analytics)
- [🛡️ Reliability & Stability](#reliability-stability)
  - [Error Handling](#error-handling)
  - [Testing](#testing)
- [📈 Performance Benchmarks](#performance-benchmarks)
  - [Current Performance](#current-performance)
  - [Target Performance](#target-performance)
- [🎓 Learning & Resources](#learning-resources)
  - [Documentation](#documentation)
- [🤝 Community & Contribution](#community-contribution)
  - [Open Source](#open-source)
  - [Support](#support)
- [📝 Notes](#notes)
  - [Priority Levels](#priority-levels)
  - [Version Planning](#version-planning)
- [🔄 Feedback & Contributions](#feedback-contributions)
- [✅ Recently Completed Features (2026-01-26)](#recently-completed-features-2026-01-26)
  - [Core Features](#core-features)
  - [Dashboard Enhancements](#dashboard-enhancements)
  - [Technical Improvements](#technical-improvements)
  - [Web Profiler Integration](#web-profiler-integration)
  - [Recent Improvements (2026-01-27)](#recent-improvements-2026-01-27)
  - [Recent Improvements (2026-01-26)](#recent-improvements-2026-01-26)

## 🎯 Vision

Transform the Performance Bundle into a comprehensive performance monitoring and analysis tool for Symfony applications, providing actionable insights to optimize application performance.

---

## 📅 Roadmap Timeline

### Phase 1: Foundation & Core Improvements (Q1 2026)
**Status:** ✅ **COMPLETED** (2026-01-26)

#### Performance Optimizations
- [x] **Database indexing optimization** ✅ **COMPLETED**
  - ✅ Add composite indexes for common queries (env + name, env + requestTime)
  - ✅ Optimize query performance for large datasets
  - ✅ Added indexes: idx_route_env_name, idx_route_env_request_time, idx_route_created_at, idx_route_env_access_count, idx_route_reviewed, idx_route_reviewed_at
  - [ ] Add database partitioning support for high-volume scenarios

- [x] **Caching layer** ✅ **COMPLETED**
  - ✅ Cache statistics calculations
  - ✅ Cache environment lists
  - ✅ Implement cache invalidation strategy
  - ✅ PerformanceCacheService with configurable TTL
  - [ ] Add Redis/Memcached support for distributed caching

- [x] **Query tracking optimization** ✅ **COMPLETED**
  - ✅ Version-aware middleware registration (DoctrineBundle 2.x and 3.x support)
  - ✅ Reflection-based middleware application for DoctrineBundle 3.x
  - ✅ QueryTrackingMiddleware with connection wrapping
  - ✅ QueryTrackingConnectionSubscriber for automatic middleware application
  - [x] **Sampling for high-traffic routes** ✅ **ADDED** (2026-01-26)
    - ✅ Configurable sampling rate (0.0 to 1.0)
    - ✅ Reduces database load for frequently accessed routes
  - [x] **Configurable query tracking threshold** ✅ **ADDED** (2026-01-26)
    - ✅ Minimum query count threshold for timing individual queries
    - ✅ Reduces overhead for low-query routes

- [ ] **Batch processing for metrics recording**
  - Collect metrics in memory during request
  - Flush to database in batches to reduce I/O
  - Implement queue-based async recording for production
  - Note: Async recording via Symfony Messenger is already supported

#### Dashboard Enhancements
- [x] **Real-time updates** ✅ **PARTIALLY COMPLETED**
  - [x] Auto-refresh dashboard ✅ **ADDED** (2026-01-26)
    - ✅ Configurable refresh interval
    - ✅ Visual countdown indicator
    - ✅ Pause on window blur
  - [ ] WebSocket support for live metrics
  - [ ] Real-time alerts for performance degradation

- [x] **Advanced filtering** ✅ **COMPLETED**
  - ✅ Date range filtering
  - ✅ Multiple route name filters
  - ✅ Query count range filters
  - ✅ Request time range filters
  - ✅ Query time range filters
  - ✅ Sorting by access count and last accessed date
  - ✅ Improved filter layout with visual grouping

- [x] **Export functionality** ✅ **COMPLETED**
  - ✅ Export to CSV (UTF-8 with BOM for Excel compatibility)
  - ✅ Export to JSON (with metadata)
  - ✅ Includes all metrics (request time, query time, queries, memory, access count, last accessed, HTTP method)
  - ✅ Respects current filters and sorting
  - [ ] Export to PDF reports
  - [ ] Scheduled report generation

- [x] **Visualization improvements** ✅ **COMPLETED**
  - ✅ Charts and graphs (Chart.js integration)
  - ✅ API endpoint for chart data (`/api/chart-data`)
  - ✅ Performance trends visualization
  - ✅ Alert when no chart data is available
  - ✅ **Advanced Performance Statistics page** ✅ **ADDED** (2026-01-26)
    - ✅ Detailed statistics for all metrics
    - ✅ Statistical measures: Mean, Median, Mode, Standard Deviation
    - ✅ Percentiles: P25, P50, P75, P90, P95, P99
    - ✅ Distribution histograms with Chart.js
    - ✅ Outlier detection and identification
    - ✅ Routes needing attention section
    - ✅ Key insights and interpretation guides
  - [ ] Route comparison views
  - [ ] Heatmaps for performance hotspots

#### Code Quality
- [x] **Test coverage** ✅ **SIGNIFICANTLY IMPROVED** (2026-01-26)
  - ✅ Unit tests for core components (712+ tests, 1262+ assertions)
  - ✅ Test coverage: Improved from 34% to ~60%+ (ongoing)
  - ✅ Integration tests for dashboard
  - ✅ Functional tests for query tracking
  - ✅ Tests for all services (PerformanceCacheService, DependencyChecker, TableStatusChecker)
  - ✅ Tests for MessageHandler and Events
  - ✅ Tests for Controller methods (statistics, delete, review, clear, chartData)
  - ✅ Tests for Entity methods (review, access count, memory usage, __toString)
  - ✅ **Additional tests added** ✅ **ADDED** (2026-01-26)
    - ✅ CreateTableCommand tests for column update functionality
    - ✅ PerformanceDataCollector tests for record operation tracking
    - ✅ PerformanceMetricsService tests for operation info return values
    - ✅ 37 test files with comprehensive coverage
  - ✅ **Controller helper methods tests** ✅ **ADDED** (2026-01-27)
    - ✅ PerformanceControllerHelperMethodsTest: 28+ tests for private helper methods
    - ✅ Tests for getSortValue() (8 tests: name, requestTime, queryTime, totalQueries, accessCount, env, default, null values)
    - ✅ Tests for calculateStats() (4 tests: empty, single route, multiple routes, null filtering)
    - ✅ Tests for calculateAdvancedStats() (3 tests: empty, with routes, null filtering)
    - ✅ Tests for calculateDetailedStats() (8 tests: empty, single, multiple, even count, outliers, distribution, same values, rounding)
    - ✅ Tests for getRoutesNeedingAttention() (4 tests: empty, slow request time, high query count, high memory)
    - ✅ Tests for getChartData() (8 tests: requestTime, queryTime, totalQueries, memoryUsage, specific route, empty results, different days)
    - ✅ Tests for buildFiltersFromRequest() (11 tests: no filters, route, min/max request time, min/max query count, date from/to, all filters, invalid dates, datetime format)
  - ✅ **Driver name compatibility tests** ✅ **ADDED** (2026-01-27)
    - ✅ PerformanceControllerDriverNameTest: 9 tests for getDriverName() method
    - ✅ Tests for unwrapped drivers, wrapped drivers (middleware), and fallback scenarios
    - ✅ Tests for MySQL, PostgreSQL, SQLite, SQLServer platform detection
  - ✅ 72+ test files with comprehensive coverage
  - [ ] 100% test coverage target (in progress)
  - [ ] Performance tests for middleware

- [x] **Documentation improvements** ✅ **COMPLETED**
  - ✅ Installation guide
  - ✅ Configuration documentation
  - ✅ Usage examples
  - ✅ Command documentation (create-table, create-records-table, sync-schema, diagnose, check-dependencies, set-route, rebuild-aggregates)
  - ✅ CHANGELOG.md with detailed feature list
  - ✅ UPGRADING.md with migration guides
  - ✅ Symfony Flex recipe with automatic configuration
  - [ ] API documentation
  - [ ] Architecture diagrams
  - [ ] Video tutorials
  - [ ] Best practices guide

---

### Phase 2: Advanced Features (Q2 2026)
**Status:** Partially Started

#### Performance Analysis
- [x] **Performance thresholds** ✅ **COMPLETED** (2026-01-26)
  - ✅ Configurable thresholds for request time, query count, and memory usage
  - ✅ Warning and critical levels
  - ✅ Visual indicators in dashboard
  - [x] **Performance alerts** ✅ **COMPLETED** (2026-01-26)
    - ✅ Email notifications via Symfony Mailer
    - ✅ Slack webhook integration
    - ✅ Microsoft Teams webhook integration
    - ✅ Generic webhook support (JSON, Slack, Teams formats)
    - ✅ Custom notification channels via `NotificationChannelInterface`
    - ✅ Automatic alerts when thresholds are exceeded
    - ✅ See [NOTIFICATIONS.md](NOTIFICATIONS.md) for documentation

- [ ] **Performance baselines**
  - Automatic baseline calculation
  - Deviation detection
  - Regression tracking
  - Performance budgets

- [ ] **Route grouping**
  - Group routes by prefix/pattern
  - Aggregate metrics by group
  - Group-based performance analysis

- [ ] **Performance recommendations**
  - AI-powered suggestions
  - Query optimization tips
  - Route optimization recommendations
  - Automatic performance reports

#### Integration Enhancements
- [x] **Symfony Messenger integration** ✅ **COMPLETED**
  - ✅ Async metrics recording via Messenger
  - ✅ Queue-based processing
  - ✅ RecordMetricsMessage and RecordMetricsMessageHandler
  - ✅ Configurable via `async: true`
  - [ ] Retry mechanisms (handled by Messenger)

- [ ] **APM integration**
  - New Relic integration
  - Datadog integration
  - Elastic APM integration
  - Custom APM provider interface

- [ ] **Monitoring tools integration**
  - Prometheus metrics export
  - Grafana dashboard templates
  - StatsD/MetricsD support

#### Advanced Tracking
- [x] **Memory usage tracking** ✅ **COMPLETED**
  - ✅ Track memory consumption per route
  - ✅ Peak memory analysis
  - ✅ Display in dashboard and exports
  - ✅ Memory usage thresholds
  - [ ] Memory leak detection

- [x] **HTTP Method tracking** ✅ **COMPLETED** (2026-01-26)
  - ✅ Track HTTP method (GET, POST, PUT, DELETE, etc.) for each route
  - ✅ Display in dashboard with color-coded badges
  - ✅ Included in CSV and JSON exports

- [x] **HTTP Status Code tracking** ✅ **COMPLETED** (2026-01-26)
  - ✅ Track HTTP status codes per route (200, 404, 500, etc.)
  - ✅ Configurable status codes to track
  - ✅ Automatic ratio calculation (percentage per status code)
  - ✅ Display in dashboard with color-coded badges
  - ✅ Methods: `incrementStatusCode()`, `getStatusCodeCount()`, `getStatusCodeRatio()`, `getTotalResponses()`

- [x] **Access frequency tracking** ✅ **COMPLETED**
  - ✅ Track how often routes are accessed (accessCount)
  - ✅ Last access timestamp (lastAccessedAt)
  - ✅ Display in dashboard with badges
  - ✅ Sorting by access count
  - ✅ Temporal access records (`RouteDataRecord`) for individual hits
  - ✅ Seasonality analysis (hour/day/month + heatmaps) based on records

- [x] **Sub-request tracking** ✅ **COMPLETED** (2026-01-27)
  - ✅ Configurable `track_sub_requests` option
  - ✅ Track sub-requests (ESI, fragments, includes) in addition to main requests
  - ✅ Default: false (only main requests tracked for backward compatibility)
  - ✅ Useful for monitoring ESI performance, fragment rendering, and debugging sub-request bottlenecks
  - ✅ Request type (main/sub) included in logging for diagnostics

- [ ] **Cache performance tracking**
  - Cache hit/miss ratios
  - Cache operation timing
  - Cache efficiency metrics

- [ ] **External API tracking**
  - HTTP client request tracking
  - External service call timing
  - API dependency mapping

---

### Phase 3: Enterprise Features (Q3 2026)
**Status:** Future

#### Multi-tenancy Support
- [ ] **Tenant isolation**
  - Multi-tenant metrics storage
  - Tenant-specific dashboards
  - Tenant-based access control

- [ ] **Organization management**
  - Multiple organizations support
  - Organization-level statistics
  - Cross-organization comparisons

#### Advanced Analytics
- [x] **Statistical analysis** ✅ **COMPLETED** (2026-01-26)
  - ✅ Percentile calculations (P25, P50, P75, P90, P95, P99)
  - ✅ Standard deviation analysis
  - ✅ Outlier detection
  - ✅ Distribution histograms
  - [ ] Time-series analysis
    - Historical trend analysis
    - Seasonal pattern detection
    - Anomaly detection
    - Predictive analytics

- [ ] **Performance profiling**
  - Detailed request profiling
  - Stack trace analysis
  - Performance bottleneck identification
  - Code-level performance insights

- [ ] **Comparative analysis**
  - Environment comparison (dev vs prod)
  - Route version comparison
  - A/B testing support

#### Security & Compliance
- [ ] **Data retention policies**
  - Automatic data cleanup
  - Configurable retention periods
  - GDPR compliance features

- [ ] **Audit logging**
  - Track dashboard access
  - Log configuration changes
  - Audit trail for metrics

- [ ] **Data encryption**
  - Encrypt sensitive route parameters
  - At-rest encryption support
  - Encryption key management

---

### Phase 4: Developer Experience (Q4 2026)
**Status:** Future

#### Developer Tools
- [x] **CLI commands** ✅ **COMPLETED**
  - ✅ `nowo:performance:create-table` - Create/update database table
    - ✅ **Enhanced column management** ✅ **ADDED** (2026-01-26)
      - ✅ Individual column comparison and update
      - ✅ Add missing columns without data loss
      - ✅ Update existing columns with differences (type, nullable, length, default)
      - ✅ Shows differences before updating
      - ✅ Safe `--update` option preserves existing data
    - ✅ **Schema sync and drop obsolete** ✅ **ADDED**
      - ✅ `--drop-obsolete` option to remove columns no longer in entity (use with `--update`)
      - ✅ Never drops primary key `id`
  - ✅ `nowo:performance:create-records-table` - Create/update access records table
    - ✅ `--update` adds/alters columns; `--drop-obsolete` drops columns not in entity
  - ✅ `nowo:performance:sync-schema` - Sync both tables with entity metadata in one go (add, alter, optional drop)
  - ✅ `nowo:performance:diagnose` - Comprehensive diagnostic report
  - ✅ `nowo:performance:check-dependencies` - Check optional dependencies
  - ✅ `nowo:performance:set-route` - Manually set/update route metrics
  - [ ] Interactive dashboard in terminal
  - [ ] Performance comparison commands
  - [ ] Bulk import/export commands
  - [ ] Data migration tools

- [ ] **IDE integration**
  - PhpStorm plugin
  - VS Code extension
  - Performance hints in IDE

- [ ] **Testing tools**
  - Performance test helpers
  - Benchmark utilities
  - Load testing integration

#### Documentation & Community
- [ ] **Interactive documentation**
  - Live examples
  - Interactive API explorer
  - Code playground

- [ ] **Community features**
  - Community templates
  - Plugin system
  - Extension marketplace

---

## 🔧 Technical Improvements

### Architecture
- [x] **Event-driven architecture** ✅ **PARTIALLY COMPLETED**
  - ✅ Decouple metrics collection from storage (via events)
  - ✅ BeforeMetricsRecordedEvent for metric modification
  - ✅ AfterMetricsRecordedEvent, BeforeRecordDeletedEvent, etc.
  - [ ] Event sourcing for metrics
  - [ ] CQRS pattern implementation

- [ ] **Microservices support**
  - Distributed metrics collection
  - Cross-service performance tracking
  - Service dependency mapping

### Scalability
- [ ] **Horizontal scaling**
  - Support for multiple application instances
  - Distributed metrics aggregation
  - Load balancing support

- [ ] **Database optimization**
  - Read replicas support
  - Sharding strategies
  - Materialized views for statistics

### Performance
- [x] **Zero-overhead mode** ✅ **PARTIALLY COMPLETED**
  - ✅ Minimal impact on application performance
  - ✅ Configurable sampling rates ✅ **ADDED** (2026-01-26)
  - ✅ Production-optimized defaults
  - ✅ Optional async recording

- [x] **Lazy loading** ✅ **COMPLETED**
  - ✅ On-demand metrics calculation
  - ✅ Deferred statistics updates (via caching)
  - ✅ Background processing (via Messenger)

---

## 🎨 UI/UX Improvements

### Dashboard
- [x] **Modern UI framework** ✅ **COMPLETED**
  - ✅ Bootstrap 5 support
  - ✅ Tailwind CSS support
  - ✅ Responsive design
  - ✅ Mobile-friendly interface
  - [ ] Dark mode support

- [x] **User experience** ✅ **SIGNIFICANTLY IMPROVED**
  - ✅ Improved filter organization with visual grouping
  - ✅ Color-coded HTTP method badges
  - ✅ Visual indicators for review status
  - ✅ Access count badges
  - ✅ Table status warnings
  - ✅ Informative alerts when no data available
  - [ ] Drag-and-drop filters
  - [ ] Saved filter presets
  - [ ] Customizable dashboard layouts
  - [ ] Keyboard shortcuts

- [ ] **Accessibility**
  - WCAG 2.1 AA compliance
  - Screen reader support
  - Keyboard navigation
  - High contrast mode

### Visualization
- [x] **Interactive charts** ✅ **COMPLETED**
  - ✅ Chart.js integration
  - ✅ Performance trends visualization
  - ✅ Distribution histograms
  - [ ] Zoom and pan capabilities
  - [ ] Drill-down functionality
  - [ ] Custom chart types
  - [ ] Export charts as images

- [x] **Performance graphs** ✅ **COMPLETED**
  - ✅ Timeline views (chart data API)
  - ✅ Distribution graphs (histograms)
  - [ ] Comparison charts
  - [ ] Correlation analysis

---

## 🔌 Integrations

### Monitoring Platforms
- [ ] **Prometheus**
  - Native Prometheus metrics
  - Service discovery integration
  - Alertmanager rules

- [ ] **Grafana**
  - Pre-built dashboard templates
  - Custom panel plugins
  - Alert integration

- [ ] **ELK Stack**
  - Elasticsearch integration
  - Logstash pipelines
  - Kibana dashboards

### CI/CD
- [ ] **GitHub Actions**
  - Performance regression detection
  - Automated performance reports
  - PR performance comments

- [ ] **GitLab CI**
  - Performance testing integration
  - Pipeline performance tracking

- [ ] **Jenkins**
  - Performance trend tracking
  - Build performance analysis

---

## 📊 Metrics & Analytics

### New Metrics
- [ ] **Response size tracking**
  - Track response payload sizes
  - Identify large responses
  - Compression ratio analysis

- [ ] **Error rate tracking**
  - Track errors per route
  - Error rate trends
  - Error correlation with performance

- [ ] **User experience metrics**
  - Time to first byte (TTFB)
  - First contentful paint (FCP)
  - Largest contentful paint (LCP)

### Advanced Analytics
- [ ] **Machine learning**
  - Anomaly detection
  - Performance prediction
  - Auto-scaling recommendations

- [x] **Statistical analysis** ✅ **COMPLETED** (2026-01-26)
  - ✅ Percentile calculations (p50, p95, p99)
  - ✅ Standard deviation analysis
  - ✅ Outlier detection
  - ✅ Distribution analysis

---

## 🛡️ Reliability & Stability

### Error Handling
- [x] **Graceful degradation** ✅ **COMPLETED**
  - ✅ Continue working if database is unavailable
  - ✅ Fallback mechanisms (default environments, empty arrays)
  - ✅ Error recovery strategies (try-catch blocks)
  - ✅ Table status checking and warnings

- [ ] **Resilience**
  - Circuit breaker pattern
  - Retry mechanisms
  - Timeout handling

### Testing
- [ ] **Load testing**
  - High-volume scenario testing
  - Stress testing
  - Performance regression tests

- [x] **Compatibility testing** ✅ **COMPLETED**
  - ✅ Multi-version Symfony support (6.1+, 7.x, 8.x)
  - ✅ Database compatibility (MySQL, PostgreSQL, SQLite)
  - ✅ PHP version compatibility (8.1+)
  - ✅ DoctrineBundle 2.x and 3.x support

---

## 📈 Performance Benchmarks

### Current Performance
- Metrics collection overhead: < 1ms per request
- Dashboard load time: < 500ms
- Query tracking overhead: < 0.5ms per query
- **With sampling enabled**: Overhead reduced proportionally

### Target Performance
- Metrics collection overhead: < 0.5ms per request
- Dashboard load time: < 200ms
- Query tracking overhead: < 0.1ms per query
- Support for 10,000+ routes
- Support for 1M+ metrics records

---

## 🎓 Learning & Resources

### Documentation
- [ ] **Video tutorials**
  - Installation walkthrough
  - Configuration guide
  - Dashboard customization
  - Advanced usage

- [ ] **Case studies**
  - Real-world usage examples
  - Performance improvement stories
  - Best practices from production

- [ ] **API reference**
  - Complete API documentation
  - Code examples
  - Integration patterns

---

## 🤝 Community & Contribution

### Open Source
- [ ] **Community guidelines**
  - Contribution guide
  - Code of conduct
  - Issue templates

- [ ] **Plugin system**
  - Extension points
  - Plugin API
  - Community plugins directory

### Support
- [ ] **Support channels**
  - GitHub Discussions
  - Discord/Slack community
  - Stack Overflow tag

---

## 📝 Notes

### Priority Levels
- **P0 (Critical)**: Must have for next release
- **P1 (High)**: Important, planned for near future
- **P2 (Medium)**: Nice to have, planned for later
- **P3 (Low)**: Future consideration

### Version Planning
- **v0.0.1**: Initial release (2026-01-26) - Foundation complete
- **v0.1.0**: Performance optimizations + additional dashboard improvements
- **v0.2.0**: Advanced features + integrations
- **v1.0.0**: Stable release (current 1.x line — no breaking changes)
- **v2.0.0** (2026-01-28): **Released — breaking.** Entity normalization (RouteData / RouteDataRecord). See [ENTITY_NORMALIZATION_PLAN.md](ENTITY_NORMALIZATION_PLAN.md) and [V2_MIGRATION.md](V2_MIGRATION.md). Metrics move from RouteData to records/aggregates; dashboard and API use new data source.

---

## 🔄 Feedback & Contributions

This roadmap is a living document. We welcome feedback and contributions:

- **Suggestions**: Open an issue with the `roadmap` label
- **Contributions**: See [CONTRIBUTING.md](CONTRIBUTING.md)
- **Discussions**: Use GitHub Discussions

---

## ✅ Recently Completed Features (2026-01-26)

### Core Features
- ✅ **Database Schema Management**: Added `nowo:performance:create-table` command for easy table creation
- ✅ **Access Tracking**: Implemented `accessCount` and `lastAccessedAt` fields to track route usage frequency
- ✅ **Data Management**: Added `clear()` method to delete performance records from dashboard
- ✅ **Dependency Validation**: Implemented `DependencyChecker` service and `nowo:performance:check-dependencies` command
- ✅ **Symfony UX Components**: Integrated Twig Components for better performance and maintainability
- ✅ **Version-Aware Middleware**: Implemented robust query tracking for DoctrineBundle 2.x and 3.x
- ✅ **Diagnostic Tools**: Added `nowo:performance:diagnose` command for troubleshooting
- ✅ **HTTP Method Tracking**: Track and display HTTP method (GET, POST, PUT, DELETE, etc.) for each route
- ✅ **Table Status Checking**: Service to verify database table existence and structure
- ✅ **Symfony Flex Recipe**: Automatic bundle configuration via Symfony Flex
- ✅ **Symfony Translation Component**: Full internationalization support with multiple languages

### Dashboard Enhancements
- ✅ **Chart.js Integration**: Full Chart.js integration with API endpoint for chart data
- ✅ **Advanced Filters**: Date ranges, query count ranges, request time ranges, multiple route filters
- ✅ **Export Features**: CSV and JSON export with all metrics including access count, last accessed date, and HTTP method
- ✅ **Memory Tracking**: Display and track peak memory usage per route
- ✅ **Access Statistics**: Display number of accesses and last access date in dashboard table
- ✅ **Advanced Performance Statistics Page**: Comprehensive statistical analysis with histograms, percentiles, and insights
- ✅ **Auto-refresh Dashboard**: Configurable auto-refresh with visual countdown indicator
- ✅ **Improved Filter Layout**: Visual grouping with icons and clearer labels

### Technical Improvements
- ✅ **Caching**: PerformanceCacheService for statistics and environment caching
- ✅ **Database Indexes**: Composite indexes for optimized queries
- ✅ **Error Handling**: Graceful degradation when optional dependencies are missing
- ✅ **Code Quality**: 123+ tests with 320+ assertions, improved code coverage
- ✅ **Sampling for High-Traffic Routes**: Configurable sampling rate to reduce database load
- ✅ **Query Tracking Threshold**: Configurable threshold for tracking query execution time
- ✅ **Query Optimization**: Reduced duplicate queries in repository methods

### Web Profiler Integration
- ✅ **Enhanced Data Collector**: Shows database storage status, table existence, and saving status
- ✅ **Ranking Information**: Access frequency, ranking by request time and query count
- ✅ **Visual Indicators**: Status indicators for slow routes and routes needing attention
- ✅ **Always Visible**: Collector always visible in toolbar, even when disabled
- ✅ **Record Operation Tracking** ✅ **ADDED** (2026-01-26)
  - ✅ Track if record was newly created or updated
  - ✅ Display operation status in collector detail view
  - ✅ Information about whether metrics were saved or skipped

---

**Last Updated:** 2026-01-27  
**Next Review:** 2026-04-27

### Recent Improvements (2026-01-27)

#### Testing & Quality
- ✅ **Extended Test Coverage**: Added 37+ new tests for controller helper methods
  - PerformanceControllerHelperMethodsTest: Comprehensive tests for private helper methods
    - getSortValue(): 8 tests covering all sort options and null handling
    - calculateStats(): 4 tests for statistics calculation with various scenarios
    - calculateAdvancedStats(): 3 tests for advanced statistics with memory conversion
    - calculateDetailedStats(): 8 tests for detailed statistical analysis (percentiles, outliers, distribution)
    - getRoutesNeedingAttention(): 4 tests for identifying routes needing attention
    - getChartData(): 8 tests for chart data generation with different metrics and scenarios
    - buildFiltersFromRequest(): 11 tests for request filter parsing and validation
  - PerformanceControllerDriverNameTest: 9 tests for database driver name detection
    - Tests for unwrapped drivers, middleware-wrapped drivers, and platform fallbacks
    - Ensures compatibility with DBAL 2.x and 3.x driver detection
  - Total: 712+ tests, 1262+ assertions across 72+ test files

### Recent Improvements (2026-01-26)

#### Database Schema Management
- ✅ **Enhanced CreateTableCommand**: Improved `--update` option to check and update columns individually
  - Column-by-column comparison (type, nullable, length, default)
  - Safe updates without data loss
  - Clear difference reporting before updates
  - Better error handling and user feedback

#### Testing & Quality
- ✅ **Additional Test Coverage**: Added 14+ new tests (2026-01-26)
  - CreateTableCommand: 6 tests for column update functionality
  - PerformanceDataCollector: 5 tests for record operation tracking
  - PerformanceMetricsService: 3 tests for operation info return values
  - Updated existing tests to verify new return values
- ✅ **Extended Test Coverage for Controller Helpers** ✅ **ADDED** (2026-01-27)
  - PerformanceControllerHelperMethodsTest: 28+ tests for private helper methods
    - getSortValue(), calculateStats(), calculateAdvancedStats(), calculateDetailedStats()
    - getRoutesNeedingAttention(), getChartData(), buildFiltersFromRequest()
  - PerformanceControllerDriverNameTest: 9 tests for database driver name detection
  - Total test suite: 712+ tests, 1262+ assertions across 72+ test files

#### Web Profiler
- ✅ **Record Operation Status**: Added tracking and display of whether records were created or updated
  - Shows "New record created" or "Existing record updated" status
  - Helps debug metric recording behavior

#### Compatibility & Code Quality
- ✅ **Doctrine DBAL Deprecation Fixes** ✅ **ADDED** (2026-01-27)
  - ✅ Fixed deprecation warnings in CreateTableCommand and TableStatusChecker
  - ✅ Replaced deprecated `AbstractPlatform::quoteIdentifier()` with helper method compatible with DBAL 2.x and 3.x
  - ✅ Replaced deprecated `Column::getName()` with `getColumnName()` helper method
  - ✅ Replaced deprecated `AbstractAsset::getName()` (for Index objects) with `getAssetName()` helper method
  - ✅ All helper methods use `getQuotedName()` for DBAL 3.x and fallback to `getName()` for DBAL 2.x
  - ✅ Eliminates deprecation warnings when running `nowo:performance:create-table --update`
  - ✅ Maintains full backward compatibility with DBAL 2.x

#### Advanced Tracking
- ✅ **Sub-request Tracking Support** ✅ **ADDED** (2026-01-27)
  - ✅ Configurable `track_sub_requests` option (default: false)
  - ✅ Tracks both main requests and sub-requests separately when enabled
  - ✅ Useful for ESI performance monitoring, fragment rendering, and debugging sub-request bottlenecks
  - ✅ Request type (main/sub) included in logging for better diagnostics
