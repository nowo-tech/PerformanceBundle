# Usage Guide


## Table of contents

- [Automatic Tracking](#automatic-tracking)
  - [How It Works](#how-it-works)
  - [Example](#example)
  - [Temporal Access Records (Seasonality)](#temporal-access-records-seasonality)
- [Manual Metrics Management](#manual-metrics-management)
  - [Setting Route Metrics](#setting-route-metrics)
  - [Command Options](#command-options)
- [Overriding templates (REQ-TWIG-001)](#overriding-templates-req-twig-001)
- [Customizing the Dashboard View](#customizing-the-dashboard-view)
- [Extension points (non-final classes)](#extension-points-non-final-classes)
  - [CSS Framework Selection](#css-framework-selection)
  - [Component Structure](#component-structure)
  - [Overriding Components](#overriding-components)
  - [Overriding the Complete Template](#overriding-the-complete-template)
  - [Available Variables](#available-variables)
  - [Translation Overrides](#translation-overrides)
- [Dashboard Features](#dashboard-features)
  - [Data Export](#data-export)
  - [Record Management](#record-management)
  - [Review System](#review-system)
- [Programmatic Usage](#programmatic-usage)
  - [Accessing Metrics Service](#accessing-metrics-service)
  - [Getting Route Metrics](#getting-route-metrics)
  - [Getting All Routes for Environment](#getting-all-routes-for-environment)
  - [Getting Worst Performing Routes](#getting-worst-performing-routes)
- [Direct Entity Access](#direct-entity-access)
- [HTTP Status Code Tracking](#http-status-code-tracking)
  - [Configuration](#configuration)
  - [Accessing Status Code Data](#accessing-status-code-data)
  - [Dashboard Display](#dashboard-display)
- [Performance Notifications](#performance-notifications)
  - [Configuration](#configuration-1)
  - [How It Works](#how-it-works-1)
- [Best Practices](#best-practices)
- [Examples](#examples)
  - [Example 1: Track API Endpoints](#example-1-track-api-endpoints)
  - [Example 2: Production Monitoring](#example-2-production-monitoring)
  - [Example 3: Custom Connection](#example-3-custom-connection)

## Automatic Tracking

The bundle automatically tracks route performance metrics for all routes (except ignored ones) in configured environments.

### How It Works

1. When a request comes in, the `PerformanceMetricsSubscriber` starts tracking:
   - Request start time
   - Database query logger

2. When the request finishes, it:
   - Calculates request execution time
   - Collects query count and execution time
   - Saves metrics to database

3. Metrics are only updated if they're worse (higher time or more queries)

### Example

Simply make requests to your routes, and metrics will be automatically tracked:

```bash
# Make a request
curl http://localhost:8000/app_home

# Metrics are automatically saved to database
```

### Temporal Access Records (Seasonality)

When `enable_access_records: true` is enabled in configuration, the bundle also stores
**one access record per request** in the `routes_data_records` table (`RouteDataRecord` entity).
These records contain:

- `accessedAt` (timestamp)
- `statusCode`
- `responseTime`
- `totalQueries`, `queryTime`, `memoryUsage` (when tracked)
- `requestId` (unique per request, for deduplication)
- `referer` (HTTP Referer header when present)
- `userIdentifier`, `userId` (logged-in user when `track_user` is true)

They power the **Access Statistics** and **Access Records** pages:

- `/performance/access-statistics` – charts and heatmaps (by hour, day of week, month)
- `/performance/access-records` – paginated list of individual hits with filters (date range, route, status code, query time, memory, referer, user). Columns are sortable; the Path column shows a clickable link to the exact URL.

The main `RouteData` entity remains an aggregate view (per route + environment), while
`RouteDataRecord` is the normalized temporal log used for seasonality analysis.

## Manual Metrics Management

### Setting Route Metrics

Use the command to manually set or update route metrics:

```bash
# Set basic metrics
php bin/console nowo:performance:set-route app_home \
    --env=dev \
    --request-time=0.5 \
    --queries=10

# Set all metrics
php bin/console nowo:performance:set-route app_user_show \
    --env=prod \
    --request-time=1.2 \
    --queries=25 \
    --query-time=0.3 \
    --params='{"id":123}'
```

### Command Options

- `route` (required) - Route name
- `--env, -e` - Environment (default: `dev`)
- `--request-time, -r` - Request time in seconds (float)
- `--queries` - Total number of queries (integer)
- `--query-time, -t` - Total query execution time in seconds (float)
- `--memory, -m` - Peak memory usage in bytes (integer)
- `--params, -p` - Route parameters as JSON string

## Overriding templates (REQ-TWIG-001)

The bundle registers the Twig namespace **`@NowoPerformanceBundle/`**. **`TwigPathsPass`** maps the Symfony override directory **`templates/bundles/NowoPerformanceBundle/`** to that namespace with **`prependPath()`** when the folder exists, then registers the bundle **`src/Resources/views`** path with **`addPath()`**, so your app copies are tried before the vendor templates. You do not need entries in **`config/packages/twig.yaml`** for this.

**Freeze rule:** a full-file override at `templates/bundles/NowoPerformanceBundle/<subpath>` always wins and will **not** pick up vendor changes for that path until you delete or merge it. Prefer config (`nowo_ui_kit.layouts.admin`, `nowo_ui_kit.theme`, `nowo_performance.web_ui.templates.<page>`) or a thin `{% extends '@!NowoPerformanceBundle/…' %}` that overrides one data block when you want to keep receiving UI fixes on package upgrades.

**Procedure (app):**

1. Take the template path relative to the bundle views root (the **`<subpath>`** below).
2. Create **`templates/bundles/NowoPerformanceBundle/<subpath>`** in your project (same relative path).
3. Clear the Twig / Symfony cache in dev if needed: **`php bin/console cache:clear`**.

**Example:**

```text
templates/bundles/NowoPerformanceBundle/Performance/index.html.twig
```

**Templates you can override** (REQ-UI-003 page contract since 3.15; full map, data blocks and view models in
[TEMPLATES.md](TEMPLATES.md)):

| Subpath | Purpose |
|--------|---------|
| `Performance/index.html.twig` | Routes (dashboard home): `@NowoUiKit/page/list` — KPI tiles, filters, routes table, row actions, pagination, trend + top routes cards, review dialogs. |
| `Performance/statistics.html.twig` | Advanced statistics: `@NowoUiKit/page/dashboard` — KPI tiles and analysis cards with server-side histograms. |
| `Performance/access_statistics.html.twig` | Access statistics: `@NowoUiKit/page/dashboard` — by hour (bar chart), heatmap, by weekday, by month. |
| `Performance/access_records.html.twig` | Access records: `@NowoUiKit/page/list` — filters, sortable columns, pagination, purge dialog. |
| `Performance/diagnose.html.twig` | Diagnose: `@NowoUiKit/page/show` — findings and one detail section per diagnostic group. |
| `Performance/_kpis.html.twig`, `_card.html.twig`, `_bar_chart.html.twig`, `_heatmap.html.twig` | Shared slot-based partials (KPI tiles, cards, inline SVG charts — no JavaScript, no CDN). |
| `Performance/_legacy_layout.html.twig` | Deprecated bridge around a 3.14-style layout (see `web_ui.layout_template`). |
| `components/*.html.twig` | Deprecated Twig components (`nowo_performance.Statistics`, `.Filters`, `.Charts`, `.RoutesTable`; removed in 4.0). |

**Layout and theme without copying files (REQ-UI-001 / REQ-UI-003):** the pages extend the UiKit scaffolds, which extend
`nowo_ui_kit.layouts.admin` — set your project layout (or a one-file bridge rendering the canonical blocks) there,
once for every Nowo bundle. Theme classes, attributes, partials and tokens are configured once under
`nowo_ui_kit.theme` (per-bundle exceptions: `nowo_ui_kit.theme.bundles.NowoPerformanceBundle`). Only when the dashboard
needs a different layout set `nowo_performance.web_ui.layout_template`.

```yaml
# config/packages/nowo_ui_kit.yaml
nowo_ui_kit:
    css_framework: bootstrap5
    layouts:
        admin: 'layouts/admin.html.twig'
```

**One data block instead of a full override** — `web_ui.templates.<page>` points to a thin template:

```yaml
# config/packages/nowo_performance.yaml
nowo_performance:
    web_ui:
        templates:
            index: 'admin/performance/index.html.twig'
```

```twig
{# templates/admin/performance/index.html.twig #}
{% extends '@!NowoPerformanceBundle/Performance/index.html.twig' %}

{% block nowo_ui_list_after %}
    {{ parent() }}
    <p class="{{ nowo_ui_class('muted', page.bundle) }}">Data retention: 30 days.</p>
{% endblock %}
```

## Customizing the Dashboard View

### Pages, scaffolds and view models

Each page receives a UiKit view model as `page` (built by `Nowo\PerformanceBundle\Ui\PerformancePageFactory`) and fills
only data blocks; header, area tabs, actions, filters, table frame, row actions, confirm dialogs, pagination and empty
states come from the scaffold and the host theme. Destructive actions (clear all, delete route, delete matching
records) are POST `Action`s with a CSRF `FormView` behind the UiKit confirm `<dialog>`; forms with fields (review,
purge) open in a UiKit `dialog`. There is no inline JavaScript and no CDN: charts are server-rendered inline SVG painted
with `currentColor`, auto refresh is a `<meta http-equiv="refresh">`, the statistics environment selector uses UiKit's
`data-nowo-ui-autosubmit`.

### Available variables

Besides `page`, the controller keeps passing the 3.14 variables so overrides keep working, e.g. on the routes page:
`routes`, `stats` (`total_routes`, `avg_request_time`, `max_request_time`, `avg_query_time`, `max_query_time`,
`avg_queries`, `max_queries`, `total_records`, `top_used_routes`, `top_consumed_routes`), `paginator`, `environment`,
`environments`, `form`, `clearForm`, `deleteForms`, `reviewForms`, `thresholds`, `dateTimeFormat`, `dateFormat`,
`autoRefreshInterval`, plus the new `kpis` and `trend`. The same FormViews back the page actions: render each only once.
`chartFiltersForm` is no longer passed (the client-side chart was removed).

### Twig components (deprecated)

With `symfony/ux-twig-component`, `{{ component('nowo_performance.Statistics', {stats: stats}) }}` (and `.Filters`,
`.Charts`, `.RoutesTable`) still render, now through the same slot-based partials; they are deprecated since 3.15 and
removed in 4.0 — the dashboard pages no longer use them.

#### Forms (REQ-TWIG-005)

Every control of the dashboard is a Symfony Form rendered with `form_start`, the child loop and `form_end`: filters
(`PerformanceFiltersType`, `RecordFiltersType`, `StatisticsEnvFilterType`), clear data, delete record, delete by
filter, purge and review. The form types carry no framework classes (the host form theme / UiKit slots style them);
`with_submit: false` (filters, clear data, delete by filter) leaves the submit button to the page (UiKit list filters
or Action). Overrides of the dashboard templates and the demos must not reintroduce raw `<form>` / `<input>` /
`<select>` / `<textarea>` markup; `make check-no-raw-html-form` (part of `make release-check`) fails when they do.

### Translation Overrides

The bundle ships translations under the `NowoPerformanceBundle` domain in `src/Resources/translations/`.

To override any key from your Symfony application:

1. Create (or edit) your app translation file, for example:
   - `translations/NowoPerformanceBundle.en.yaml`
   - `translations/NowoPerformanceBundle.es.yaml`
2. Copy only the keys you want to customize.
3. Clear cache in dev if needed (`php bin/console cache:clear --env=dev --no-warmup`).

Example:

```yaml
# translations/NowoPerformanceBundle.en.yaml
dashboard:
  title: "My Custom Performance Dashboard"
```

## Dashboard Features

### Data Export

The dashboard includes export functionality to download performance data:

**CSV Export:**
- Click "Export CSV" button in dashboard header
- Downloads a CSV file with all current filtered data
- Includes: route name, environment, metrics, memory usage, access count, timestamps
- UTF-8 encoding with BOM for Excel compatibility

**JSON Export:**
- Click "Export JSON" button in dashboard header
- Downloads a JSON file with all current filtered data
- Includes metadata: environment, export date
- Structured format for programmatic use

**Export respects filters:**
- Current environment filter
- Route name filters
- Time/query range filters
- Date range filters
- Current sorting

**Access records export (CSV / JSON):**
- On the **Access Records** page (temporal records per route), use **Export Records (CSV)** or **Export Records (JSON)**.
- Exports individual `RouteDataRecord` rows (id, route name, path, params, environment, accessed at, status code, response time, total queries, query time, memory usage, referer, user_identifier, user_id).
- Uses the same filters as the records page: environment, date range, route, status code, query time, memory, referer, user.
- Requires `enable_access_records: true`. Subject to dashboard roles if configured.

### Record Management

When `enable_record_management` is enabled:

**Delete Individual Records:**
- Delete button appears in Actions column for each record
- Confirmation dialog before deletion
- CSRF protection
- Redirects to referer after deletion
- Cache is automatically invalidated

**Clear All Records:**
- "Clear All Records" button in dashboard header
- Optionally filters by environment
- Confirmation dialog before clearing
- CSRF protection
- Redirects to referer after clearing

**Delete records by filter:**
- Available on **Access Records** and **Access Statistics** pages when `enable_record_management` is enabled
- Deletes all `RouteDataRecord` rows matching the current filters (environment, date range, route, status code, query time, memory, referer, user)
- Confirmation dialog before deletion
- CSRF protection (`delete_records_by_filter` token)
- Redirects back to the page you came from (Access Records or Access Statistics)

### Review System

When `enable_review_system` is enabled:

**Mark Records as Reviewed:**
- Review button (check icon) appears for unreviewed records
- Modal form to mark as reviewed
- Options:
  - Queries Improved: Yes / No / Not specified
  - Time Improved: Yes / No / Not specified
- Reviewer username is automatically recorded
- Review date is automatically set

**Edit Existing Review:**
- Edit review button (pencil icon) appears for already reviewed records
- Same modal opens with the form pre-filled with current values
- Change "Queries improved" or "Time improved" and save; flash message "Review updated"

**Review Status Display:**
- "Reviewed" badge for reviewed records
- "Pending" badge for unreviewed records
- Improvement indicators:
  - Green badge if improved
  - Red badge if not improved
  - Not shown if not specified

**Filtering by Review Status:**
- Can be added to filters (future enhancement)

## Programmatic Usage

### Accessing Metrics Service

```php
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;

class YourController
{
    public function __construct(
        private readonly PerformanceMetricsService $metricsService
    ) {
    }

    public function index(): Response
    {
        // Get route data
        $routeData = $this->metricsService->getRouteData('app_home', 'dev');
        
        if ($routeData) {
            $requestTime = $routeData->getRequestTime();
            $queryCount = $routeData->getTotalQueries();
        }

        // ...
    }
}
```

### Getting Route Metrics

```php
// Get specific route data
$routeData = $metricsService->getRouteData('app_home', 'dev');

if ($routeData) {
    echo "Request Time: " . $routeData->getRequestTime() . "s\n";
    echo "Queries: " . $routeData->getTotalQueries() . "\n";
    echo "Query Time: " . $routeData->getQueryTime() . "s\n";
}
```

### Getting All Routes for Environment

```php
// Get all routes for an environment
$routes = $metricsService->getRoutesByEnvironment('dev');

foreach ($routes as $route) {
    echo $route->getName() . ": " . $route->getRequestTime() . "s\n";
}
```

### Getting Worst Performing Routes

```php
// Get top 10 worst performing routes
$worstRoutes = $metricsService->getWorstPerformingRoutes('dev', 10);

foreach ($worstRoutes as $route) {
    echo sprintf(
        "%s: %.4fs, %d queries\n",
        $route->getName(),
        $route->getRequestTime(),
        $route->getTotalQueries()
    );
}
```

## Direct Entity Access

You can also access the entity directly via Doctrine:

```php
use Nowo\PerformanceBundle\Entity\RouteData;
use Doctrine\ORM\EntityManagerInterface;

class YourService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function getMetrics(): array
    {
        $repository = $this->entityManager->getRepository(RouteData::class);
        
        // Custom queries
        $routes = $repository->findByEnvironment('dev');
        
        return $routes;
    }
}
```

## HTTP Status Code Tracking

The bundle automatically tracks HTTP status codes for each route and calculates ratios.

### Configuration

Configure which status codes to track:

```yaml
# config/packages/nowo_performance.yaml
nowo_performance:
    track_status_codes: [200, 404, 500, 503]
```

### Accessing Status Code Data

```php
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;

$routeData = $metricsService->getRouteData('app_home', 'dev');

if ($routeData) {
    // Get status codes counts
    $statusCodes = $routeData->getStatusCodes(); // ['200' => 100, '404' => 5, '500' => 2]
    
    // Get count for specific status code
    $count200 = $routeData->getStatusCodeCount(200); // 100
    
    // Get ratio (percentage) for specific status code
    $ratio200 = $routeData->getStatusCodeRatio(200); // 93.46 (percentage)
    
    // Get total responses tracked
    $total = $routeData->getTotalResponses(); // 107
}
```

### Dashboard Display

Status codes are displayed in the dashboard with:
- Color-coded badges (green for 200, red for errors)
- Percentage ratios (e.g., "200: 95.5%")
- Tooltips with absolute counts (e.g., "100 de 105 (95.5%)")

## Performance Notifications

The bundle can send automatic notifications when performance thresholds are exceeded.

### Configuration

See [NOTIFICATIONS.md](NOTIFICATIONS.md) for complete documentation.

**Quick example:**

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

### How It Works

Notifications are automatically sent when:
- Request time exceeds warning/critical thresholds
- Query count exceeds warning/critical thresholds
- Memory usage exceeds warning/critical thresholds

## Best Practices

1. **Enable only in dev/test** - Don't track in production unless needed
2. **Use ignore_routes** - Ignore profiler and debug routes
3. **Monitor worst routes** - Regularly check worst performing routes
4. **Set baseline metrics** - Use the command to set baseline metrics for important routes
5. **Review periodically** - Review metrics to identify performance issues
6. **Configure status code tracking** - Track relevant HTTP status codes for your application
7. **Set up notifications** - Configure email/Slack/Teams alerts for production environments
8. **Sub-request tracking** - Enable `track_sub_requests` only if you need to monitor ESI, fragments, or includes separately (increases database storage)

## Examples

### Example 1: Track API Endpoints

```yaml
# config/packages/nowo_performance.yaml
nowo_performance:
    environments: ['dev', 'staging']
    ignore_routes:
        - '_wdt'
        - '_profiler'
```

### Example 2: Production Monitoring

```yaml
# config/packages/prod/nowo_performance.yaml
nowo_performance:
    enabled: true
    environments: ['prod']
    track_queries: true
    track_request_time: true
```

### Example 3: Custom Connection

```yaml
# Use dedicated connection for metrics
nowo_performance:
    connection: 'metrics'
```

## Extension points (non-final classes)

New classes are `final` by default (REQ-PHP-001). The classes below stay open on purpose in 3.x:

| Classes | Why they are not final |
| --- | --- |
| `Entity\RouteData`, `Entity\RouteDataRecord` | Doctrine ORM lazy-loading proxies extend entity classes. |
| `Service\PerformanceMetricsService`, `Service\PerformanceCacheService`, `Service\PerformanceAnalysisService`, `Service\NotificationService`, `Service\TableStatusChecker`, `Service\DependencyChecker`, `Repository\RouteDataRepository`, `Repository\RouteDataRecordRepository`, `DataCollector\PerformanceDataCollector` | Public 3.x services that hosts decorate or subclass; prefer service decoration (`#[AsDecorator]`) over inheritance. |
| `EventSubscriber\*`, `DBAL\*`, `Controller\PerformanceController`, `Command\Create*TableCommand`, `Form\*`, `Twig\ArrayExtension`, `Twig\IconExtension`, `DependencyInjection\Compiler\*` | Registered by class name since 1.x/2.x; making them final is a BC break scheduled for 4.0 (UPGRADING.md will list it). |

