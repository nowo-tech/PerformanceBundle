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
- `--queries, -q` - Total number of queries (integer)
- `--query-time, -t` - Total query execution time in seconds (float)
- `--params, -p` - Route parameters as JSON string

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

## Best Practices

1. **Enable only in dev/test** - Don't track in production unless needed
2. **Use ignore_routes** - Ignore profiler and debug routes
3. **Monitor worst routes** - Regularly check worst performing routes
4. **Set baseline metrics** - Use the command to set baseline metrics for important routes
5. **Review periodically** - Review metrics to identify performance issues

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
