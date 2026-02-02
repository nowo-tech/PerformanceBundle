# Usage Guide

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

## Customizing the Dashboard View

The performance dashboard is built using reusable Twig components, allowing you to customize specific parts without replacing the entire template.

### CSS Framework Selection

The dashboard supports two CSS frameworks: **Bootstrap** (default) and **Tailwind CSS**. You can choose which one to use via configuration:

```yaml
# config/packages/nowo_performance.yaml
nowo_performance:
    dashboard:
        template: 'bootstrap'  # or 'tailwind'
```

### Component Structure

The dashboard consists of three main components, available in both Bootstrap and Tailwind versions:

**Bootstrap components:**
1. **Statistics Component** (`_statistics_bootstrap.html.twig`) - Displays performance statistics cards
2. **Filters Component** (`_filters_bootstrap.html.twig`) - Contains the filtering form
3. **Routes Table Component** (`_routes_table_bootstrap.html.twig`) - Shows the routes data table

**Tailwind components:**
1. **Statistics Component** (`_statistics_tailwind.html.twig`) - Displays performance statistics cards
2. **Filters Component** (`_filters_tailwind.html.twig`) - Contains the filtering form
3. **Routes Table Component** (`_routes_table_tailwind.html.twig`) - Shows the routes data table

### Overriding Components

You can override individual components by creating them in your project's template directory:

**Path structure:**
```
templates/
  bundles/
    NowoPerformanceBundle/
      Performance/
        components/
          _statistics_bootstrap.html.twig    # Override Bootstrap statistics
          _filters_bootstrap.html.twig       # Override Bootstrap filters
          _routes_table_bootstrap.html.twig # Override Bootstrap table
          _statistics_tailwind.html.twig     # Override Tailwind statistics
          _filters_tailwind.html.twig        # Override Tailwind filters
          _routes_table_tailwind.html.twig   # Override Tailwind table
```

**Example: Custom Bootstrap Statistics Component**

```twig
{# templates/bundles/NowoPerformanceBundle/Performance/components/_statistics_bootstrap.html.twig #}
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h5>Custom Statistics</h5>
                <p>Total Routes: {{ stats.total_routes }}</p>
                <p>Total Queries: {{ stats.total_queries }}</p>
            </div>
        </div>
    </div>
</div>
```

**Example: Custom Tailwind Statistics Component**

```twig
{# templates/bundles/NowoPerformanceBundle/Performance/components/_statistics_tailwind.html.twig #}
<div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 mb-8">
    <div class="bg-white overflow-hidden shadow rounded-lg p-5">
        <h5 class="text-lg font-semibold">Custom Statistics</h5>
        <p>Total Routes: {{ stats.total_routes }}</p>
        <p>Total Queries: {{ stats.total_queries }}</p>
    </div>
</div>
```

**Example: Custom Routes Table**

```twig
{# templates/bundles/NowoPerformanceBundle/Performance/components/_routes_table.html.twig #}
<div class="row">
    <div class="col-12">
        <div class="custom-table">
            {% for route in routes %}
                <div class="route-item">
                    <strong>{{ route.name }}</strong>
                    <span>{{ (route.requestTime * 1000)|number_format(2) }} ms</span>
                </div>
            {% endfor %}
        </div>
    </div>
</div>
```

### Overriding the Complete Template

If you prefer to replace the entire dashboard template, create:

```
templates/bundles/NowoPerformanceBundle/Performance/index.html.twig
```

This will completely override the default template. You can still use the components if needed:

```twig
{# Your custom template #}
{% extends 'base.html.twig' %}

{% block content %}
    <h1>My Custom Dashboard</h1>
    
    {# Use the default statistics component #}
    {% include '@NowoPerformanceBundle/Performance/components/_statistics.html.twig' %}
    
    {# Or use your custom component #}
    {% include 'bundles/NowoPerformanceBundle/Performance/components/_statistics.html.twig' %}
{% endblock %}
```

### Available Variables

All components receive the same variables from the controller:

- `routes` - Array of RouteData entities
- `stats` - Statistics array with:
  - `total_routes` - Total number of routes
  - `total_queries` - Total number of queries
  - `avg_request_time` - Average request time (in seconds)
  - `avg_query_time` - Average query time (in seconds)
  - `max_request_time` - Maximum request time (in seconds)
  - `max_query_time` - Maximum query time (in seconds)
  - `max_queries` - Maximum query count
- `environment` - Current environment filter
- `currentRoute` - Current route name filter
- `sortBy` - Current sort field
- `order` - Current sort order (ASC/DESC)
- `limit` - Current result limit
- `environments` - Array of available environments

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
