# Configuration Guide

## Default Configuration

The bundle works with sensible defaults. You only need to configure it if you want to change the default behavior.

## Full Configuration Example

```yaml
# config/packages/nowo_performance.yaml
nowo_performance:
    enabled: true                    # Enable/disable performance tracking
    environments: ['dev', 'test']    # Environments where tracking is enabled
    connection: 'default'             # Doctrine connection name
    table_name: 'routes_data'        # Table name for storing metrics
    track_queries: true              # Track database query count and time
    track_request_time: true         # Track request execution time
    track_sub_requests: false         # Track sub-requests (ESI, fragments, etc.)
    enable_access_records: false     # Enable temporal access records tracking
    ignore_routes:                   # Routes to ignore (not tracked)
        - '_wdt'                     # Web Debug Toolbar
        - '_profiler'                # Symfony Profiler
        - '_error'                    # Error pages
    track_status_codes: [200, 404, 500, 503]  # HTTP status codes to track
    dashboard:                       # Performance dashboard configuration
        enabled: true                # Enable/disable the dashboard
        path: '/performance'         # Route path for the dashboard
        prefix: ''                   # Optional route prefix
        roles: []                    # Required roles (empty = no restrictions)
```

## Configuration Options

### `enabled`

**Type:** `boolean`  
**Default:** `true`

Enable or disable performance tracking globally.

```yaml
nowo_performance:
    enabled: false  # Disable tracking
```

### `environments`

**Type:** `array`  
**Default:** `['dev', 'test']`

List of environments where performance tracking is enabled.

```yaml
nowo_performance:
    environments: ['dev', 'test', 'staging']
```

### `connection`

**Type:** `string`  
**Default:** `'default'`

Doctrine connection name to use for storing metrics.

```yaml
nowo_performance:
    connection: 'performance'  # Use a dedicated connection
```

### `table_name`

**Type:** `string`  
**Default:** `'routes_data'`

Table name for storing route performance data.

```yaml
nowo_performance:
    table_name: 'route_performance_metrics'
```

### `track_queries`

**Type:** `boolean`  
**Default:** `true`

Track database query count and execution time.

```yaml
nowo_performance:
    track_queries: false  # Disable query tracking
```

### `track_request_time`

**Type:** `boolean`  
**Default:** `true`

Track request execution time.

```yaml
nowo_performance:
    track_request_time: false  # Disable request time tracking
```

### `track_sub_requests`

**Type:** `boolean`  
**Default:** `false`

Track sub-requests in addition to main requests. When enabled, performance metrics will be collected for both main requests and sub-requests (e.g., ESI, fragments, includes).

By default, only main requests are tracked to avoid duplicate metrics and reduce database load. Enable this option if you need to track performance of sub-requests separately.

```yaml
nowo_performance:
    track_sub_requests: true  # Enable tracking of sub-requests
```

**Use cases:**
- Tracking ESI (Edge Side Includes) performance
- Monitoring fragment rendering performance
- Analyzing include/embed performance
- Debugging sub-request bottlenecks

**Note:** Enabling this feature will increase database storage requirements as it creates metrics for every sub-request (subject to sampling_rate).

### `enable_access_records`

**Type:** `boolean`  
**Default:** `false`

Enable temporal access records tracking. When enabled, creates individual records for each route access with timestamp, HTTP status code, and response time in a separate `routes_data_records` table. Useful for analyzing access patterns by time of day, hour, or specific time periods.

When this option is enabled, the `nowo:performance:create-table` command will automatically create the `routes_data_records` table along with the main `routes_data` table.

```yaml
nowo_performance:
    enable_access_records: true  # Enable temporal access records
```

**Use cases:**
- Analyzing access patterns by time of day
- Tracking individual request details over time
- Time-series analysis of route performance
- Identifying peak usage times

**Note:** Enabling this feature will increase database storage requirements as it creates a record for every route access (subject to sampling_rate).

### `ignore_routes`

**Type:** `array`  
**Default:** `['_wdt', '_profiler', '_error']`

List of route names to ignore (not tracked).

```yaml
nowo_performance:
    ignore_routes:
        - '_wdt'
        - '_profiler'
        - '_error'
        - 'api_doc'
        - 'admin_*'  # Note: wildcards not supported, use exact names
```

### `dashboard`

**Type:** `array`  
**Default:** See below

Performance dashboard configuration.

#### `dashboard.enabled`

**Type:** `boolean`  
**Default:** `true`

Enable or disable the performance dashboard.

```yaml
nowo_performance:
    dashboard:
        enabled: false  # Disable dashboard
```

#### `dashboard.path`

**Type:** `string`  
**Default:** `'/performance'`

Base route path for the performance dashboard.

```yaml
nowo_performance:
    dashboard:
        path: '/metrics'  # Dashboard will be at /metrics
```

#### `dashboard.prefix`

**Type:** `string`  
**Default:** `''`

Optional route prefix for the dashboard. Useful for adding admin prefixes.

```yaml
nowo_performance:
    dashboard:
        prefix: '/admin'  # Dashboard will be at /admin/performance
```

#### `dashboard.roles`

**Type:** `array`  
**Default:** `[]`

Required roles to access the dashboard. Users must have **at least one** of the configured roles. If empty, access is unrestricted.

```yaml
nowo_performance:
    dashboard:
        roles: ['ROLE_ADMIN']  # Only users with ROLE_ADMIN can access
```

**Multiple roles (OR logic):**

```yaml
nowo_performance:
    dashboard:
        roles: ['ROLE_ADMIN', 'ROLE_PERFORMANCE_VIEWER']  # Users with either role can access
```

**Unrestricted access:**

```yaml
nowo_performance:
    dashboard:
        roles: []  # No restrictions (default)
```

#### `dashboard.template`

**Type:** `string`  
**Default:** `'bootstrap'`  
**Options:** `'bootstrap'` or `'tailwind'`

CSS framework to use for the dashboard interface.

```yaml
nowo_performance:
    dashboard:
        template: 'bootstrap'  # Use Bootstrap 5 (default)
        # or
        template: 'tailwind'   # Use Tailwind CSS
```

**Bootstrap (default):**
- Uses Bootstrap 5.3.0 from CDN
- Traditional grid system and components
- Includes Bootstrap JavaScript bundle

**Tailwind:**
- Uses Tailwind CSS from CDN
- Utility-first CSS framework
- Modern, responsive design
- No JavaScript dependencies

**Customization:**
You can override individual components regardless of the selected template:
- Bootstrap components: `_statistics_bootstrap.html.twig`, `_filters_bootstrap.html.twig`, `_routes_table_bootstrap.html.twig`
- Tailwind components: `_statistics_tailwind.html.twig`, `_filters_tailwind.html.twig`, `_routes_table_tailwind.html.twig`

Override them in: `templates/bundles/NowoPerformanceBundle/Performance/components/`

#### `dashboard.enable_record_management`

**Type:** `boolean`  
**Default:** `false`

Enable individual record deletion from the dashboard. When enabled, a delete button appears for each record in the routes table.

```yaml
nowo_performance:
    dashboard:
        enable_record_management: true
```

**Security:**
- Requires CSRF token validation
- Respects dashboard role restrictions
- Delete operations redirect to referer

#### `dashboard.enable_review_system`

**Type:** `boolean`  
**Default:** `false`

Enable the record review system. When enabled, users can mark records as reviewed and indicate if queries or time improved.

```yaml
nowo_performance:
    dashboard:
        enable_review_system: true
```

**Features:**
- Review modal for each record
- Track if queries improved (yes/no/not specified)
- Track if time improved (yes/no/not specified)
- Record reviewer username and review date
- Visual indicators for review status

**Security:**
- Requires CSRF token validation
- Respects dashboard role restrictions
- Reviewer is automatically set from current user

#### `dashboard.date_formats`

**Type:** `array`  
**Default:** See below

Date format configuration for displaying dates in the dashboard.

```yaml
nowo_performance:
    dashboard:
        date_formats:
            datetime: 'Y-m-d H:i:s'  # Format for date and time with seconds
            date: 'Y-m-d H:i'        # Format for date and time without seconds
```

#### `dashboard.auto_refresh_interval`

**Type:** `integer`  
**Default:** `0` (disabled)

Auto-refresh interval for the dashboard in seconds. When set to a value greater than 0, the dashboard will automatically reload data at the specified interval.

```yaml
nowo_performance:
    dashboard:
        auto_refresh_interval: 30  # Refresh every 30 seconds
```

**Features:**
- Visual countdown indicator
- Automatically pauses when window loses focus
- Resume when window regains focus

### `track_status_codes`

**Type:** `array`  
**Default:** `[200, 404, 500, 503]`

HTTP status codes to track and calculate ratios for. Only the configured status codes will be tracked and displayed in the dashboard.

```yaml
nowo_performance:
    track_status_codes: [200, 201, 400, 404, 500, 503]
```

**Note:** The bundle automatically increments the count for each status code when a response is recorded. Ratios are calculated as percentages of total responses.

### `sampling_rate`

**Type:** `float`  
**Default:** `1.0`  
**Range:** `0.0` to `1.0`

Sampling rate for high-traffic routes. Reduces database load for frequently accessed routes by tracking only a percentage of requests.

```yaml
nowo_performance:
    sampling_rate: 0.1  # Track only 10% of requests
```

**Use cases:**
- High-traffic production environments
- Routes with thousands of requests per minute
- When database write performance is a concern

### `query_tracking_threshold`

**Type:** `integer`  
**Default:** `0`

Minimum query count to track query execution time. Queries below this threshold are counted but not timed individually.

```yaml
nowo_performance:
    query_tracking_threshold: 5  # Only time queries if route has 5+ queries
```

### `thresholds`

**Type:** `array`  
**Default:** See below

Performance thresholds for warning and critical levels. Used for visual indicators in dashboard and automatic notifications.

```yaml
nowo_performance:
    thresholds:
        request_time:
            warning: 0.5    # seconds
            critical: 1.0   # seconds
        query_count:
            warning: 20
            critical: 50
        memory_usage:
            warning: 20.0   # MB
            critical: 50.0  # MB
```

### `notifications`

**Type:** `array`  
**Default:** See below

Performance alert notifications configuration. See [NOTIFICATIONS.md](NOTIFICATIONS.md) for complete documentation.

```yaml
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
        teams:
            enabled: true
            webhook_url: 'https://outlook.office.com/webhook/YOUR/WEBHOOK/URL'
        webhook:
            enabled: false
            url: ''
            format: 'json'
            headers: []
```

## Environment-Specific Configuration

You can override configuration per environment:

```yaml
# config/packages/dev/nowo_performance.yaml
nowo_performance:
    enabled: true
    environments: ['dev']

# config/packages/prod/nowo_performance.yaml
nowo_performance:
    enabled: false  # Disable in production
```

## Multiple Connections

If you need to use a different Doctrine connection:

```yaml
# config/packages/doctrine.yaml
doctrine:
    dbal:
        connections:
            default:
                # ... default connection config
            performance:
                # ... performance connection config

# config/packages/nowo_performance.yaml
nowo_performance:
    connection: 'performance'
```

## Dashboard Access Control

### Restricting Dashboard Access

By default, the dashboard is accessible to everyone. To restrict access to specific roles:

```yaml
nowo_performance:
    dashboard:
        enabled: true
        path: '/performance'
        roles: ['ROLE_ADMIN', 'ROLE_PERFORMANCE_VIEWER']
```

### Security Considerations

- **Production environments**: Always configure roles in production to prevent unauthorized access
- **Development environments**: You may leave roles empty for easier access during development
- **Role hierarchy**: Symfony's role hierarchy is respected (e.g., `ROLE_ADMIN` includes `ROLE_USER`)

### Example: Admin-Only Dashboard

```yaml
# config/packages/prod/nowo_performance.yaml
nowo_performance:
    dashboard:
        enabled: true
        path: '/admin/performance'
        prefix: '/admin'
        roles: ['ROLE_ADMIN']
```

### Example: Multiple Roles

```yaml
nowo_performance:
    dashboard:
        roles: ['ROLE_ADMIN', 'ROLE_MONITORING', 'ROLE_DEVOPS']
```

Users with **any** of these roles will have access.
