# Feature Specification: PerformanceBundle baseline (100% code coverage)

**Feature Branch**: `001-baseline`  
**Created**: 2026-07-07  
**Status**: Active  
**Input**: Backfill GitHub Spec Kit baseline documenting 100% of production code in `src/`.

**Related docs**: [`docs/SPEC-DRIVEN-DEVELOPMENT.md`](../../docs/SPEC-DRIVEN-DEVELOPMENT.md), [`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md), [`docs/USAGE.md`](../../docs/USAGE.md)  
**Code inventory (traceability)**: [`code-inventory.md`](code-inventory.md)

---

## Summary

**Package**: `nowo-tech/performance-bundle`  
**Configuration root**: `nowo_performance`

Symfony bundle for **tracking and analyzing route performance metrics**: automatic collection of request time, database query count/duration, memory usage, HTTP status ratios, optional temporal access records, web dashboard with Bootstrap/Tailwind themes, Web Profiler integration, async recording via Messenger, and configurable email/webhook alerts. Symfony 7|8 · PHP 8.2+.

---

## User Scenarios & Testing

### User Story 1 — Automatic route metrics (Priority: P1)

As a performance engineer, I enable the bundle so every matched route records wall time, query count, query time, and memory in the configured environment.

**Independent Test**: Hit an instrumented route in `dev`; verify a row in `routes_data` (or configured table) with expected columns; Web Profiler panel shows ranking.

**Acceptance Scenarios**:

1. **Given** `enabled=true` and environment in `environments`, **When** a main request completes, **Then** `PerformanceMetricsSubscriber` records metrics via `PerformanceMetricsService`.
2. **Given** `track_queries=true`, **When** Doctrine executes statements, **Then** `QueryTrackingMiddleware` and `QueryLogger` count and time queries.
3. **Given** route name matches `ignore_routes`, **When** request completes, **Then** no metrics row is written.

---

### User Story 2 — Performance dashboard (Priority: P1)

As an operator, I open the configured dashboard path to filter, sort, export, review, and delete route metrics.

**Independent Test**: Authenticate with configured role; visit `/performance` (default); apply filters; export CSV/JSON; open Advanced Statistics and Access Statistics when enabled.

**Acceptance Scenarios**:

1. **Given** dashboard `path` and `role`, **When** authorized user visits index, **Then** `PerformanceController` renders KPIs, trends chart, and routes table.
2. **Given** `css_framework=bootstrap|tailwind`, **When** components render, **Then** matching partials (`_*_bootstrap` / `_*_tailwind`) are used.
3. **Given** sufficient data, **When** Advanced Statistics is opened, **Then** `PerformanceAnalysisService` outputs recommendations, correlations, and percentile tables.

---

### User Story 3 — Access records & temporal analysis (Priority: P2)

As an operator, I enable access records to analyze traffic by hour, day, and heatmaps, and purge old rows by retention policy.

**Acceptance Scenarios**:

1. **Given** `enable_access_records=true` and records table exists, **When** requests complete, **Then** individual `RouteDataRecord` rows store timestamp, status, response time, optional user id.
2. **Given** access statistics page filters, **When** applied, **Then** charts and tables aggregate by hour/day/month.
3. **Given** `access_records_retention_days`, **When** purge command or UI action runs, **Then** older records are deleted and lifecycle events fire.

---

### User Story 4 — Alerts and async recording (Priority: P2)

As an integrator, I configure thresholds and notification channels so slow routes trigger email or webhook alerts; optionally record metrics asynchronously.

**Acceptance Scenarios**:

1. **Given** thresholds exceeded, **When** terminate event runs, **Then** `PerformanceAlertSubscriber` builds `PerformanceAlert` and `NotificationService` dispatches to tagged channels.
2. **Given** `async=true` and Messenger configured, **When** metrics would be written, **Then** `RecordMetricsMessage` is handled by `RecordMetricsMessageHandler`.
3. **Given** `sampling_rate < 1.0`, **When** high-traffic route is hit, **Then** only sampled requests persist metrics.

---

### User Story 5 — CLI maintenance (Priority: P3)

As a maintainer, I create/sync schema, diagnose table health, rebuild aggregates, set manual metrics, and check optional dependencies from the console.

**Acceptance Scenarios**:

1. **Given** fresh database, **When** `nowo:performance:create-table` runs, **Then** aggregate table schema matches entity mapping.
2. **Given** missing optional packages, **When** `nowo:performance:check-dependencies` runs, **Then** `DependencyChecker` lists suggested `composer require` hints.
3. **Given** corrupt aggregates, **When** `nowo:performance:rebuild-aggregates` runs, **Then** `RouteData` rows recompute from records.

---

### Edge Cases

- Sub-requests: ignored unless `track_sub_requests=true`.
- Profiler/WDT routes: default `ignore_routes` skips toolbar noise.
- Multiple Doctrine connections: `connection` config selects target; middleware pass registers per connection.
- Custom table names: `table_name` and compiler passes rewrite entity table names at runtime.
- `check_table_status=false`: skips introspection queries in dev dashboard/profiler.
- Review system disabled: review forms and columns hidden per config.

---

## Requirements

### Bundle & DI

- **FR-BUNDLE-001**: `NowoPerformanceBundle` MUST expose alias `nowo_performance` via `PerformanceExtension`.
- **FR-CFG-001**: `Configuration` MUST define tracking toggles (`enabled`, `environments`, `connection`, `table_name`, `track_queries`, `track_request_time`, `track_sub_requests`, `ignore_routes`, `track_status_codes`, `async`, `sampling_rate`, `query_tracking_threshold`, access records, cache, thresholds, dashboard, notifications, review/delete features, and CSS framework).
- **FR-CFG-002**: `PerformanceExtension` MUST load YAML configs, register compiler passes, and expose `%nowo_performance.*%` parameters.
- **FR-DI-001**: `services.yaml`, `routes.yaml`, and optional `services_twig_component.yaml` MUST wire subscribers, services, controllers, forms, and notification channels.

### Metrics collection

- **FR-METRICS-001**: `PerformanceMetricsSubscriber` MUST capture start time/memory on request, respect ignore list and sampling, and persist on terminate via `PerformanceMetricsService`; MUST populate `PerformanceDataCollector`.
- **FR-METRICS-002**: `PerformanceAlertSubscriber` MUST evaluate configured thresholds and trigger notifications without blocking the response.

### DBAL & persistence

- **FR-DBAL-001**: `QueryTrackingMiddleware`, `QueryTrackingMiddlewareRegistry`, `QueryTrackingConnectionSubscriber`, and `QueryLogger` MUST count/time queries when `track_queries=true`.
- **FR-DBAL-002**: `TableNamePass`, `TableNameSubscriber`, and `RouteDataRecordTableNameSubscriber` MUST apply configurable table names to entities/repositories.
- **FR-ENTITY-001**: `RouteData` (aggregates) and `RouteDataRecord` (temporal access) entities MUST map documented columns including status code ratios, memory, review flags.
- **FR-REPO-001**: Repositories MUST provide dashboard queries, filters, aggregates rebuild, and purge helpers.

### Services

- **FR-SVC-001**: `PerformanceMetricsService` MUST upsert route metrics and optional access records idempotently per request id.
- **FR-SVC-002**: `PerformanceAnalysisService` MUST compute statistics, recommendations, correlations, and efficiency metrics for the advanced dashboard.
- **FR-SVC-003**: `PerformanceCacheService` MUST cache expensive dashboard queries using configured pool (`nowo_performance.cache` by default).
- **FR-SVC-004**: `TableStatusChecker` MUST verify table existence/completeness when `check_table_status=true`.
- **FR-SVC-005**: `LogHelper` MUST gate `error_log` calls via `enable_logging`.

### Dashboard & forms

- **FR-DASH-001**: `PerformanceController` MUST expose index, statistics, diagnose, access records/statistics, export (CSV/JSON), review, delete, clear, and purge actions with role checks.
- **FR-DASH-002**: Core dashboard Twig templates MUST render KPI cards, trends, routes table, dependency modal, and access views.
- **FR-DASH-003**: Bootstrap/Tailwind component partials MUST share equivalent UX for filters, statistics, charts, routes table, and paginator.
- **FR-FORM-001**: Dashboard forms (`PerformanceFiltersType`, `RecordFiltersType`, `StatisticsEnvFilterType`, review/delete/clear/purge types) MUST validate filter payloads bound to model DTOs.
- **FR-MDL-001**: Filter and request models MUST typehint controller/form bindings (`RecordFilters`, `RouteDataWithAggregates`, etc.).

### Web Profiler

- **FR-PROF-001**: `PerformanceDataCollector` MUST expose route ranking, query stats, and table status in `@NowoPerformance/Collector/performance.html.twig`.

### Events

- **FR-EVT-001**: Before/after events for metrics recording, record delete/review, and bulk clear MUST allow listeners to veto or react to lifecycle changes.

### Async messaging

- **FR-MSG-001**: `RecordMetricsMessage`, `RecordMetricsMessageHandler`, `MessageBusInterface`, and `MessengerBusAdapter` MUST record metrics off the request thread when `async=true`.
- **FR-MSG-002**: `AsMessageHandlerPolyfill` MUST provide Symfony 6.4 attribute compatibility.

### Notifications

- **FR-NOTIF-001**: `NotificationService`, `NotificationChannelInterface`, `PerformanceAlert`, and `NotificationChannelsPass` MUST dispatch alerts to tagged channels.
- **FR-NOTIF-002**: `EmailNotificationChannel` MUST render HTML/text templates for alert emails.
- **FR-NOTIF-003**: `WebhookNotificationChannel` MUST POST JSON payloads suitable for Slack/Teams/generic webhooks.

### Twig & UX components

- **FR-TWIG-001**: `ArrayExtension` and `IconExtension` MUST supply dashboard helper functions/filters.
- **FR-TWIG-002**: Optional Symfony UX Twig Components (`ChartsComponent`, `FiltersComponent`, `RoutesTableComponent`, `StatisticsComponent`) and matching templates MUST mirror dashboard functionality when UX is installed.

### CLI

- **FR-CLI-001**: Commands `nowo:performance:check-dependencies`, `create-table`, `create-records-table`, `sync-schema`, `diagnose`, `set-route-metrics`, `rebuild-aggregates`, and `purge-access-records` MUST perform documented maintenance tasks.

### Internationalization

- **FR-I18N-001**: Translation catalogs `NowoPerformanceBundle.*.yaml` MUST cover dashboard labels, alerts, and form validation messages.

---

## Key Entities

- **RouteData**: Aggregated metrics per route/environment (request time, queries, memory, status ratios, review metadata).
- **RouteDataRecord**: Individual access row with timestamp for temporal analytics.
- **PerformanceAlert**: Threshold breach payload passed to notification channels.
- **RouteDataWithAggregates**: Dashboard row combining entity fields with computed aggregates.

---

## Success Criteria

- **SC-001**: 100% of production files in `src/` mapped in [`code-inventory.md`](code-inventory.md) (**111/111**).
- **SC-002**: Configuration keys in `docs/CONFIGURATION.md` match `Configuration.php`.
- **SC-003**: PHPUnit and PHPStan pass in CI (`composer qa`).
- **SC-004**: Dashboard exports produce valid CSV/JSON for filtered datasets.
- **SC-005**: Ignored routes and sampling reduce write volume without breaking aggregate queries.

---

## Assumptions

- Doctrine ORM/DBAL is available on the configured connection.
- Dashboard access is protected by Symfony Security using configured role(s).
- Chart.js and optional UX Twig Components are loaded by the application when those features are used.
- Messenger is optional and required only when `async=true`.
- Demos under `demo/` illustrate integration but are not Packagist API.

---

## Explicit non-goals

- APM-grade distributed tracing across services.
- Real-time streaming metrics (batch/terminate-time persistence only).
- Automatic query optimization or ORM query rewriting.

---

## Validation

| Check | Command |
| --- | --- |
| Full QA | `composer qa` or `make release-check` |
| PHP tests | `vendor/bin/phpunit` |
| Static analysis | `vendor/bin/phpstan analyse` |
| Code inventory | `find src -type f \| wc -l` must match inventory total (111) |

When changing behavior, update this spec, [`code-inventory.md`](code-inventory.md), tests, and integrator docs.
