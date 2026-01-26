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
