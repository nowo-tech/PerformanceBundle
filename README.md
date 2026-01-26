# Performance Bundle

[![CI](https://github.com/nowo-tech/performance-bundle/actions/workflows/ci.yml/badge.svg)](https://github.com/nowo-tech/performance-bundle/actions/workflows/ci.yml) [![Latest Stable Version](https://poser.pugx.org/nowo-tech/performance-bundle/v)](https://packagist.org/packages/nowo-tech/performance-bundle) [![License](https://poser.pugx.org/nowo-tech/performance-bundle/license)](https://packagist.org/packages/nowo-tech/performance-bundle) [![PHP Version Require](https://poser.pugx.org/nowo-tech/performance-bundle/require/php)](https://packagist.org/packages/nowo-tech/performance-bundle)

> ⭐ **Found this project useful?** Give it a star on GitHub! It helps us maintain and improve the project.

**Symfony bundle for tracking and analyzing route performance metrics.** Automatically records request time, database query count, and query execution time for performance analysis.

> 📋 **Compatible with Symfony 6.1+, 7.x, and 8.x** - This bundle requires Symfony 6.1 or higher.

## What is this?

This bundle helps you **track and analyze route performance** in your Symfony applications:

- 📊 **Automatic Performance Tracking** - Automatically tracks route performance metrics
- 🔍 **Query Analysis** - Counts and times database queries per route
- ⏱️ **Request Timing** - Measures request execution time
- 📈 **Performance Analysis** - Identifies slow routes and query-heavy endpoints
- 🎯 **Route Metrics** - Stores metrics per route and environment
- 🔧 **Manual Updates** - Command to manually set/update route metrics

## Quick Search Terms

Looking for: **route performance**, **performance monitoring**, **query tracking**, **Symfony performance**, **route metrics**, **performance analysis**, **database query tracking**, **request timing**, **profiling**, **performance bundle**? You've found the right bundle!

## Features

- ✅ Automatic route performance tracking via event subscribers
- ✅ Database query counting and execution time tracking
- ✅ Request execution time measurement
- ✅ Route data persistence in database
- ✅ Environment-specific metrics (dev, test, prod)
- ✅ Configurable route ignore list
- ✅ Command to manually set/update route metrics
- ✅ Support for multiple Doctrine connections
- ✅ Performance dashboard with filtering and sorting
- ✅ Role-based access control for dashboard
- ✅ WebProfiler integration
- ✅ Symfony 6.1+, 7.x, and 8.x compatible

## Installation

```bash
composer require nowo-tech/performance-bundle
```

Then, register the bundle in your `config/bundles.php`:

```php
<?php

return [
    // ...
    Nowo\PerformanceBundle\PerformanceBundle::class => ['all' => true],
];
```

## Quick Start

1. **Configure the bundle** (optional - works with defaults):

```yaml
# config/packages/nowo_performance.yaml
nowo_performance:
    enabled: true
    environments: ['dev', 'test']
    connection: 'default'
    track_queries: true
    track_request_time: true
    ignore_routes:
        - '_wdt'
        - '_profiler'
        - '_error'
    dashboard:
        enabled: true
        path: '/performance'
        roles: ['ROLE_ADMIN']  # Optional: restrict access
```

2. **Create the database table**:

```bash
php bin/console doctrine:schema:update --force
# or
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

3. **That's it!** The bundle will automatically track route performance metrics.

## Usage

### Automatic Tracking

The bundle automatically tracks performance metrics for all routes (except ignored ones) in configured environments.

### Manual Metrics Update

Use the command to manually set or update route metrics:

```bash
# Set route metrics
php bin/console nowo:performance:set-route app_home \
    --env=dev \
    --request-time=0.5 \
    --queries=10 \
    --query-time=0.2

# Update with worse metrics (higher time or more queries)
php bin/console nowo:performance:set-route app_user_show \
    --env=prod \
    --request-time=1.2 \
    --queries=25 \
    --query-time=0.3 \
    --params='{"id":123}'
```

### Accessing Metrics

```php
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;

// Get route data
$routeData = $metricsService->getRouteData('app_home', 'dev');

// Get all routes for environment
$routes = $metricsService->getRoutesByEnvironment('dev');

// Get worst performing routes
$worstRoutes = $metricsService->getWorstPerformingRoutes('dev', 10);
```

## Requirements

- PHP >= 8.1, < 8.6
- **Symfony >= 6.1** || >= 7.0 || >= 8.0
- Doctrine ORM >= 2.13 || >= 3.0
- Doctrine Bundle >= 2.8 || >= 3.0 (3.0 required for Symfony 8)

## Configuration

The bundle works with default settings. Create `config/packages/nowo_performance.yaml`:

```yaml
nowo_performance:
    enabled: true                    # Enable/disable tracking
    environments: ['dev', 'test']    # Environments to track
    connection: 'default'            # Doctrine connection name
    table_name: 'routes_data'        # Table name for metrics
    track_queries: true              # Track database queries
    track_request_time: true         # Track request time
    ignore_routes:                   # Routes to ignore
        - '_wdt'
        - '_profiler'
        - '_error'
    dashboard:                       # Performance dashboard
        enabled: true                # Enable/disable dashboard
        path: '/performance'         # Dashboard route path
        roles: []                    # Required roles (empty = unrestricted)
```

## Commands

- **`nowo:performance:set-route`** - Set or update route performance metrics

## Entity Structure

The `RouteData` entity stores:

- `id` - Primary key
- `env` - Environment (dev, test, prod)
- `name` - Route name
- `totalQueries` - Total number of database queries
- `queryTime` - Total query execution time in seconds
- `requestTime` - Request execution time in seconds
- `params` - Route parameters (JSON)
- `createdAt` - Creation timestamp
- `updatedAt` - Last update timestamp

## How It Works

1. **Event Subscriber** (`PerformanceMetricsSubscriber`) listens to kernel events
2. On `KernelEvents::REQUEST`, it starts tracking:
   - Request start time
   - Resets query tracking middleware
3. On `KernelEvents::TERMINATE`, it:
   - Calculates request time
   - Collects query count and execution time using multiple strategies:
     - **Primary**: `QueryTrackingMiddleware` (DBAL middleware for DBAL 3.x compatibility)
     - **Fallback 1**: `DoctrineDataCollector` from Symfony profiler
     - **Fallback 2**: Request attributes (`_profiler`, `_profiler_profile`)
     - **Fallback 3**: Stopwatch (time only)
   - Saves metrics to database via `PerformanceMetricsService`
4. Metrics are only updated if they're worse (higher time or more queries)

### Query Tracking Architecture

The bundle uses a **multi-layered approach** for query tracking:

- **QueryTrackingMiddleware**: A custom DBAL middleware that intercepts all database queries at the driver level. This is the primary method and works with DBAL 3.x (which removed `SQLLogger`).
- **DoctrineDataCollector**: Falls back to Symfony's built-in profiler data collector if the middleware is not available.
- **Request Attributes**: Attempts to access profiler data from request attributes for sub-requests.
- **Stopwatch**: Last resort fallback for timing information only (does not provide query count).

This ensures reliable query tracking across different Symfony and Doctrine versions.

## Documentation

- [Installation Guide](docs/INSTALLATION.md) - Step-by-step installation
- [Configuration Guide](docs/CONFIGURATION.md) - Detailed configuration options
- [Usage Guide](docs/USAGE.md) - Complete usage examples
- [Commands](docs/COMMANDS.md) - Command documentation
- [CHANGELOG](docs/CHANGELOG.md) - Version history
- [UPGRADING](docs/UPGRADING.md) - Upgrade instructions

## Testing

```bash
# Run tests
composer test

# Run with coverage
composer test-coverage
```

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.

## Contributing

We welcome contributions! Please see [CONTRIBUTING.md](docs/CONTRIBUTING.md) for details.

## Author

Created by [Héctor Franco Aceituno](https://github.com/HecFranco) at [Nowo.tech](https://nowo.tech)
