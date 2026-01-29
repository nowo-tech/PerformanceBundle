# Events

The Performance Bundle dispatches custom events that allow you to extend its functionality by listening to these events in your application.

## Internal event flow and priorities

The bundle subscribes to **Symfony kernel events** to collect metrics. The order of execution is determined by **priority**: higher priority runs first. Documenting this helps avoid regressions if priorities are changed (e.g. for compatibility with other bundles).

### Kernel events used

| Event | Subscriber | Priority | Purpose |
|-------|------------|----------|---------|
| `KernelEvents::REQUEST` | `QueryTrackingConnectionSubscriber` | **4096** | Apply Doctrine query-tracking middleware to the connection as early as possible. |
| `KernelEvents::REQUEST` | (Symfony) `RouterListener` | **32** | Resolve the current route and set `Request::attributes->get('_route')`. |
| `KernelEvents::REQUEST` | `PerformanceMetricsSubscriber` | **31** | Read `_route`, check `ignore_routes`, environment, and start timing/query tracking. Must run **after** RouterListener so `_route` is set. |
| `KernelEvents::TERMINATE` | `PerformanceMetricsSubscriber` | **-1024** | Persist metrics after the response has been sent. |

### Why `PerformanceMetricsSubscriber` uses priority 31

The `ignore_routes` option is applied in `PerformanceMetricsSubscriber::onKernelRequest()`. To decide if the current route is ignored, the subscriber needs `$request->attributes->get('_route')`. That attribute is set by Symfony’s **RouterListener**, which runs on `kernel.request` with priority **32**.

- If `PerformanceMetricsSubscriber` ran with a **higher** priority than 32 (e.g. 1024), it would run **before** RouterListener, so `_route` would still be `null` and **no route would ever be considered ignored**.
- Therefore the subscriber is registered with priority **31** (lower than 32), so it runs **after** RouterListener and `ignore_routes` works correctly.

**If you change this priority** (e.g. to integrate with another listener): keep it **strictly lower than RouterListener’s 32**, or `ignore_routes` will stop working. See [CONFIGURATION.md](CONFIGURATION.md#ignore_routes) for `ignore_routes` options.

### Summary

1. **REQUEST (priority 4096)** – Query tracking middleware is applied to the DBAL connection.
2. **REQUEST (priority 32)** – Symfony resolves the route and sets `_route`.
3. **REQUEST (priority 31)** – Bundle reads `_route`, applies `ignore_routes` and environment checks, and starts collecting metrics when tracking is enabled.
4. **TERMINATE (priority -1024)** – Bundle persists the collected metrics.

Changing any of these priorities can affect `ignore_routes`, query tracking, or the moment metrics are written; document and test any change.

## Available Events

### Metrics Recording Events

#### `BeforeMetricsRecordedEvent`

Dispatched before performance metrics are recorded to the database.

**Properties:**
- `getRouteName(): string` - The route name
- `getEnv(): string` - The environment
- `getRequestTime(): ?float` - Request execution time (modifiable)
- `setRequestTime(?float $requestTime): void` - Modify request time
- `getTotalQueries(): ?int` - Total number of queries (modifiable)
- `setTotalQueries(?int $totalQueries): void` - Modify query count
- `getQueryTime(): ?float` - Query execution time (modifiable)
- `setQueryTime(?float $queryTime): void` - Modify query time
- `getParams(): ?array` - Route parameters (modifiable)
- `setParams(?array $params): void` - Modify parameters
- `getMemoryUsage(): ?int` - Memory usage in bytes (modifiable)
- `setMemoryUsage(?int $memoryUsage): void` - Modify memory usage

**Use Cases:**
- Modify metrics before they are saved
- Add custom validation
- Enrich metrics with additional data
- Filter or normalize values

**Example:**

```php
use Nowo\PerformanceBundle\Event\BeforeMetricsRecordedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class MetricsModifierListener
{
    public function onBeforeMetricsRecorded(BeforeMetricsRecordedEvent $event): void
    {
        // Round request time to 2 decimals
        if ($event->getRequestTime() !== null) {
            $event->setRequestTime(round($event->getRequestTime(), 2));
        }

        // Add custom metadata to params
        $params = $event->getParams() ?? [];
        $params['custom_field'] = 'custom_value';
        $event->setParams($params);
    }
}
```

#### `AfterMetricsRecordedEvent`

Dispatched after performance metrics are recorded to the database.

**Properties:**
- `getRouteData(): RouteData` - The saved route data entity
- `isNew(): bool` - Whether this was a new record (true) or an update (false)

**Use Cases:**
- Send notifications when metrics are recorded
- Log metrics to external systems
- Trigger additional processing
- Update related data

**Example:**

```php
use Nowo\PerformanceBundle\Event\AfterMetricsRecordedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class MetricsNotificationListener
{
    public function onAfterMetricsRecorded(AfterMetricsRecordedEvent $event): void
    {
        $routeData = $event->getRouteData();
        
        // Send alert if request time is too high
        if ($routeData->getRequestTime() > 2.0) {
            // Send notification...
        }

        // Log to external system
        if ($event->isNew()) {
            // New route detected...
        }
    }
}
```

### Record Management Events

#### `BeforeRecordDeletedEvent`

Dispatched before a performance record is deleted.

**Properties:**
- `getRouteData(): RouteData` - The route data entity to be deleted
- `preventDeletion(): void` - Prevent the deletion from happening
- `isDeletionPrevented(): bool` - Check if deletion was prevented

**Use Cases:**
- Prevent deletion based on custom rules
- Log deletion attempts
- Perform cleanup before deletion

**Example:**

```php
use Nowo\PerformanceBundle\Event\BeforeRecordDeletedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class DeletionProtectionListener
{
    public function onBeforeRecordDeleted(BeforeRecordDeletedEvent $event): void
    {
        $routeData = $event->getRouteData();
        
        // Prevent deletion of production routes
        if ($routeData->getEnv() === 'prod') {
            $event->preventDeletion();
        }

        // Log deletion attempt
        // logger->info('Deletion attempted', ['route' => $routeData->getName()]);
    }
}
```

#### `AfterRecordDeletedEvent`

Dispatched after a performance record is deleted.

**Properties:**
- `getRecordId(): int` - The ID of the deleted record
- `getRouteName(): string` - The route name of the deleted record
- `getEnv(): string` - The environment of the deleted record

**Use Cases:**
- Clean up related data
- Send notifications
- Update external systems

**Example:**

```php
use Nowo\PerformanceBundle\Event\AfterRecordDeletedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class DeletionCleanupListener
{
    public function onAfterRecordDeleted(AfterRecordDeletedEvent $event): void
    {
        // Clean up related cache
        // cache->delete('route_' . $event->getRouteName());
        
        // Notify external system
        // api->notifyDeletion($event->getRecordId());
    }
}
```

#### `BeforeRecordsClearedEvent`

Dispatched before all performance records are cleared.

**Properties:**
- `getEnv(): ?string` - Optional environment filter (null = all environments)
- `preventClearing(): void` - Prevent the clearing from happening
- `isClearingPrevented(): bool` - Check if clearing was prevented

**Use Cases:**
- Prevent clearing in production
- Require additional confirmation
- Log clearing attempts

**Example:**

```php
use Nowo\PerformanceBundle\Event\BeforeRecordsClearedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class ClearingProtectionListener
{
    public function onBeforeRecordsCleared(BeforeRecordsClearedEvent $event): void
    {
        // Prevent clearing in production
        if ($event->getEnv() === 'prod') {
            $event->preventClearing();
        }

        // Require special permission
        // if (!hasSpecialPermission()) {
        //     $event->preventClearing();
        // }
    }
}
```

#### `AfterRecordsClearedEvent`

Dispatched after all performance records are cleared.

**Properties:**
- `getDeletedCount(): int` - Number of records deleted
- `getEnv(): ?string` - Optional environment filter (null = all environments)

**Use Cases:**
- Log clearing operations
- Notify administrators
- Update external systems

**Example:**

```php
use Nowo\PerformanceBundle\Event\AfterRecordsClearedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class ClearingNotificationListener
{
    public function onAfterRecordsCleared(AfterRecordsClearedEvent $event): void
    {
        // Log the operation
        // logger->warning('Performance records cleared', [
        //     'count' => $event->getDeletedCount(),
        //     'env' => $event->getEnv(),
        // ]);
        
        // Notify administrators
        // mailer->send('Records cleared', ...);
    }
}
```

### Review System Events

#### `BeforeRecordReviewedEvent`

Dispatched before a performance record is marked as reviewed.

**Properties:**
- `getRouteData(): RouteData` - The route data entity to be reviewed
- `getQueriesImproved(): ?bool` - Whether queries improved (modifiable)
- `setQueriesImproved(?bool $queriesImproved): void` - Modify queries improved flag
- `getTimeImproved(): ?bool` - Whether time improved (modifiable)
- `setTimeImproved(?bool $timeImproved): void` - Modify time improved flag
- `getReviewedBy(): ?string` - The reviewer username (modifiable)
- `setReviewedBy(?string $reviewedBy): void` - Modify reviewer
- `preventReview(): void` - Prevent the review from happening
- `isReviewPrevented(): bool` - Check if review was prevented

**Use Cases:**
- Validate review data
- Modify review flags based on custom logic
- Prevent review under certain conditions
- Add additional review metadata

**Example:**

```php
use Nowo\PerformanceBundle\Event\BeforeRecordReviewedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class ReviewValidationListener
{
    public function onBeforeRecordReviewed(BeforeRecordReviewedEvent $event): void
    {
        $routeData = $event->getRouteData();
        
        // Auto-detect improvements based on metrics
        if ($routeData->getRequestTime() < 0.5) {
            $event->setTimeImproved(true);
        }
        
        if ($routeData->getTotalQueries() < 5) {
            $event->setQueriesImproved(true);
        }

        // Prevent review if route is too new
        $daysSinceCreation = (time() - $routeData->getCreatedAt()->getTimestamp()) / 86400;
        if ($daysSinceCreation < 7) {
            $event->preventReview();
        }
    }
}
```

#### `AfterRecordReviewedEvent`

Dispatched after a performance record is marked as reviewed.

**Properties:**
- `getRouteData(): RouteData` - The reviewed route data entity

**Use Cases:**
- Send notifications
- Update related systems
- Trigger workflows
- Log review actions

**Example:**

```php
use Nowo\PerformanceBundle\Event\AfterRecordReviewedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class ReviewNotificationListener
{
    public function onAfterRecordReviewed(AfterRecordReviewedEvent $event): void
    {
        $routeData = $event->getRouteData();
        
        // Notify team if improvements were made
        if ($routeData->getQueriesImproved() === true || $routeData->getTimeImproved() === true) {
            // Send notification...
        }

        // Update external tracking system
        // tracker->markReviewed($routeData->getId());
    }
}
```

## Event Listener Registration

### Using Attributes (Recommended)

```php
use Nowo\PerformanceBundle\Event\BeforeMetricsRecordedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class MyMetricsListener
{
    public function onBeforeMetricsRecorded(BeforeMetricsRecordedEvent $event): void
    {
        // Your logic here
    }
}
```

### Using services.yaml

```yaml
services:
    App\EventListener\MyMetricsListener:
        tags:
            - { name: kernel.event_listener, event: Nowo\PerformanceBundle\Event\BeforeMetricsRecordedEvent, method: onBeforeMetricsRecorded }
```

## Event Priority

You can set event priority using the `priority` parameter:

```php
#[AsEventListener(priority: 100)]
class HighPriorityListener
{
    public function onBeforeMetricsRecorded(BeforeMetricsRecordedEvent $event): void
    {
        // This listener runs before others with lower priority
    }
}
```

## Best Practices

1. **Don't break the flow**: Avoid throwing exceptions in event listeners unless absolutely necessary
2. **Keep listeners fast**: Event listeners should execute quickly to not slow down the main flow
3. **Handle errors gracefully**: Wrap risky operations in try-catch blocks
4. **Use appropriate priorities**: Higher priority listeners run first
5. **Document your listeners**: Add PHPDoc comments explaining what your listener does

## Example: Complete Integration

```php
namespace App\EventListener;

use Nowo\PerformanceBundle\Event\AfterMetricsRecordedEvent;
use Nowo\PerformanceBundle\Event\BeforeRecordDeletedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Psr\Log\LoggerInterface;

class PerformanceMetricsListener
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    #[AsEventListener]
    public function onAfterMetricsRecorded(AfterMetricsRecordedEvent $event): void
    {
        $routeData = $event->getRouteData();
        
        $this->logger->info('Performance metrics recorded', [
            'route' => $routeData->getName(),
            'env' => $routeData->getEnv(),
            'request_time' => $routeData->getRequestTime(),
            'queries' => $routeData->getTotalQueries(),
            'is_new' => $event->isNew(),
        ]);
    }

    #[AsEventListener(priority: 100)]
    public function onBeforeRecordDeleted(BeforeRecordDeletedEvent $event): void
    {
        $routeData = $event->getRouteData();
        
        // Prevent deletion of important routes
        if (in_array($routeData->getName(), ['app_home', 'app_api_status'])) {
            $event->preventDeletion();
            $this->logger->warning('Deletion prevented for important route', [
                'route' => $routeData->getName(),
            ]);
        }
    }
}
```
