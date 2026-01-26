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
    ignore_routes:                   # Routes to ignore (not tracked)
        - '_wdt'                     # Web Debug Toolbar
        - '_profiler'                # Symfony Profiler
        - '_error'                    # Error pages
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
