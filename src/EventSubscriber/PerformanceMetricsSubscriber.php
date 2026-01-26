<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\EventSubscriber;

use Doctrine\DBAL\Logging\Middleware;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\DataCollector\PerformanceDataCollector;
use Nowo\PerformanceBundle\DBAL\QueryTrackingMiddleware;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * Event subscriber for tracking route performance metrics.
 *
 * Listens to kernel request and terminate events to collect and store
 * performance metrics for routes including request time, query count, and query time.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 1024)]
#[AsEventListener(event: KernelEvents::TERMINATE, priority: -1024)]
class PerformanceMetricsSubscriber implements EventSubscriberInterface
{
    /**
     * Request start time for timing calculation.
     */
    private ?float $startTime = null;

    /**
     * Initial memory usage at request start.
     */
    private ?int $startMemory = null;

    /**
     * Query logger instance for tracking database queries.
     */
    private ?QueryLogger $queryLogger = null;

    /**
     * Stopwatch instance for tracking query execution time.
     */
    private ?Stopwatch $stopwatch = null;

    /**
     * Profiler instance for accessing Doctrine DataCollector.
     */
    private readonly ?Profiler $profiler;

    /**
     * Current route name being tracked.
     */
    private ?string $routeName = null;

    /**
     * Route parameters for the current request.
     */
    private ?array $routeParams = null;

    /**
     * Constructor.
     *
     * @param PerformanceMetricsService $metricsService Service for recording metrics
     * @param ManagerRegistry           $registry       Doctrine registry for entity manager access
     * @param PerformanceDataCollector  $dataCollector  Data collector for WebProfiler
     */
    public function __construct(
        private readonly PerformanceMetricsService $metricsService,
        private readonly ManagerRegistry $registry,
        #[Autowire('%nowo_performance.connection%')]
        private readonly string $connectionName,
        private readonly PerformanceDataCollector $dataCollector,
        #[Autowire('%nowo_performance.enabled%')]
        private readonly bool $enabled,
        #[Autowire('%nowo_performance.environments%')]
        private readonly array $environments,
        #[Autowire('%nowo_performance.ignore_routes%')]
        private readonly array $ignoreRoutes,
        #[Autowire('%nowo_performance.track_queries%')]
        private readonly bool $trackQueries,
        #[Autowire('%nowo_performance.track_request_time%')]
        private readonly bool $trackRequestTime,
        #[Autowire('%nowo_performance.async%')]
        private readonly bool $async = false,
        private readonly ?RequestStack $requestStack = null,
        #[Autowire(service: '?stopwatch')]
        ?Stopwatch $stopwatch = null,
    ) {
        $this->dataCollector->setEnabled($enabled);
        $this->dataCollector->setAsync($async);
        $this->stopwatch = $stopwatch;
    }

    /**
     * Get the subscribed kernel events.
     *
     * Note: This method is kept for compatibility but events are registered
     * via #[AsEventListener] attributes above the class.
     *
     * @return array<string, string> Array of event names and handlers
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
            KernelEvents::TERMINATE => 'onKernelTerminate',
        ];
    }

    /**
     * Handle the kernel request event.
     *
     * Initializes performance tracking for the current request if enabled
     * and the environment is configured for tracking.
     *
     * @param RequestEvent $event The request event
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$this->enabled || !$event->isMainRequest()) {
            $this->dataCollector->setEnabled(false);

            return;
        }

        $request = $event->getRequest();
        $env = $request->server->get('APP_ENV') ?? 'dev';

        if (!\in_array($env, $this->environments, true)) {
            $this->dataCollector->setEnabled(false);

            return;
        }

        $this->dataCollector->setEnabled(true);

        // Get route name
        $this->routeName = $request->attributes->get('_route');
        $this->dataCollector->setRouteName($this->routeName);

        // Skip ignored routes
        if (null !== $this->routeName && \in_array($this->routeName, $this->ignoreRoutes, true)) {
            $this->dataCollector->setEnabled(false);

            return;
        }

        // Start timing
        if ($this->trackRequestTime) {
            $this->startTime = microtime(true);
            $this->dataCollector->setStartTime($this->startTime);
        }

        // Track initial memory usage
        $this->startMemory = memory_get_usage(true);

        // Track queries if enabled
        if ($this->trackQueries) {
            // Reset query tracking middleware
            QueryTrackingMiddleware::reset();
            $this->queryLogger = new QueryLogger();
            $this->startQueryTracking();
        }

        // Get route parameters
        $this->routeParams = $request->attributes->get('_route_params', []);
    }

    /**
     * Handle the kernel terminate event.
     *
     * Calculates final performance metrics and records them to the database.
     * This is called after the response has been sent to the client.
     *
     * @param TerminateEvent $event The terminate event
     */
    public function onKernelTerminate(TerminateEvent $event): void
    {
        if (!$this->enabled || !$this->dataCollector->isEnabled()) {
            return;
        }

        $request = $event->getRequest();
        $env = $request->server->get('APP_ENV') ?? 'dev';

        // Get route name here, as it should be resolved by now
        $this->routeName = $this->routeName ?? $request->attributes->get('_route');
        $this->dataCollector->setRouteName($this->routeName);

        if (null === $this->routeName) {
            return;
        }

        if (!\in_array($env, $this->environments, true)) {
            return;
        }

        // Calculate request time
        $requestTime = null;
        if ($this->trackRequestTime && null !== $this->startTime) {
            $requestTime = microtime(true) - $this->startTime;
            $this->dataCollector->setRequestTime($requestTime);
        }

        // Get query metrics BEFORE stopping query tracking
        $queryCount = null;
        $queryTime = null;
        if ($this->trackQueries) {
            $metrics = $this->getQueryMetrics($request);
            $queryCount = $metrics['count'];
            $queryTime = $metrics['time'];
            $this->dataCollector->setQueryCount($queryCount);
            $this->dataCollector->setQueryTime($queryTime);
        }

        // Calculate peak memory usage
        $memoryUsage = null;
        if (null !== $this->startMemory) {
            $peakMemory = memory_get_peak_usage(true);
            $memoryUsage = $peakMemory - $this->startMemory;
            // Ensure non-negative (in case memory was freed)
            if ($memoryUsage < 0) {
                $memoryUsage = $peakMemory;
            }
        }

        // Get HTTP method
        $httpMethod = $request->getMethod();

        // Record metrics - ensure no output is generated
        try {
            // Suppress error reporting temporarily to prevent warnings from generating output
            $errorReporting = error_reporting(0);

            // Use output buffering to catch any potential output
            $obLevel = ob_get_level();
            if (0 === $obLevel) {
                ob_start();
            }

            if (\function_exists('error_log')) {
                error_log(\sprintf(
                    '[PerformanceBundle] Attempting to save metrics: route=%s, env=%s, method=%s, requestTime=%s, queryCount=%s',
                    $this->routeName ?? 'null',
                    $env,
                    $httpMethod,
                    null !== $requestTime ? (string) $requestTime : 'null',
                    null !== $queryCount ? (string) $queryCount : 'null'
                ));
            }

            $this->metricsService->recordMetrics(
                $this->routeName,
                $env,
                $requestTime,
                $queryCount,
                $queryTime,
                $this->routeParams,
                $memoryUsage,
                $httpMethod
            );

            if (\function_exists('error_log')) {
                error_log(\sprintf('[PerformanceBundle] Metrics saved successfully for route: %s', $this->routeName ?? 'null'));
            }

            // Clean output buffer if we started it
            if (0 === $obLevel && ob_get_level() > 0) {
                ob_end_clean();
            }

            // Restore error reporting
            error_reporting($errorReporting);
        } catch (\Exception $e) {
            // Restore error reporting
            if (isset($errorReporting)) {
                error_reporting($errorReporting);
            }

            // Clean output buffer if needed
            $obLevel = ob_get_level();
            if ($obLevel > 0) {
                ob_end_clean();
            }

            // Log the error for debugging
            if (\function_exists('error_log')) {
                error_log(\sprintf(
                    '[PerformanceBundle] Error saving metrics for route %s: %s',
                    $this->routeName ?? 'null',
                    $e->getMessage()
                ));
            }

            // Silently fail to not break the application
            // In production, you might want to log this
        } finally {
            // Stop query tracking
            if ($this->trackQueries) {
                $this->stopQueryTracking();
            }
        }
    }

    /**
     * Start tracking database queries.
     *
     * Initializes the query logger for the current request.
     */
    private function startQueryTracking(): void
    {
        if (null === $this->queryLogger) {
            return;
        }

        // Reset query logger for new request
        $this->queryLogger->reset();

        // Start stopwatch for query tracking
        if (null !== $this->stopwatch) {
            $this->stopwatch->reset();
            $this->stopwatch->start('doctrine.queries');
        }
    }

    /**
     * Stop tracking database queries.
     *
     * Performs cleanup after query tracking is complete.
     */
    private function stopQueryTracking(): void
    {
        // Stop stopwatch if it was started
        if (null !== $this->stopwatch && $this->stopwatch->isStarted('doctrine.queries')) {
            $this->stopwatch->stop('doctrine.queries');
        }
    }

    /**
     * Get query metrics from QueryTrackingMiddleware, Doctrine DataCollector, or fallback.
     *
     * @param \Symfony\Component\HttpFoundation\Request|null $request The request object (optional, will use RequestStack if not provided)
     *
     * @return array{count: int, time: float} Array with query count and total time
     */
    private function getQueryMetrics(?\Symfony\Component\HttpFoundation\Request $request = null): array
    {
        $queryCount = 0;
        $queryTime = 0.0;

        // Priority 1: Try to get metrics from QueryTrackingMiddleware (most reliable for our use case)
        try {
            $queryCount = QueryTrackingMiddleware::getQueryCount();
            $queryTime = QueryTrackingMiddleware::getTotalQueryTime();

            // Even if count is 0, return it if we got valid data (time might be 0 for very fast queries)
            // Only fallback if both are 0 AND we have a request to check profiler
            if ($queryCount > 0 || ($queryTime > 0 && null === $request)) {
                return ['count' => $queryCount, 'time' => $queryTime];
            }

            // If middleware returned 0/0, it might not be working, try fallback
            // But only if we have a request to check profiler
            if (0 === $queryCount && 0.0 === $queryTime && null !== $request) {
                // Continue to fallback methods
            } else {
                // Return what we got from middleware
                return ['count' => $queryCount, 'time' => $queryTime];
            }
        } catch (\Exception $e) {
            // Silently fail and try next method
        }

        // Priority 2: Try to get metrics from Doctrine DataCollector via Profiler service
        // Note: This requires the Response, which we don't have in this method
        // So we'll skip this approach and use request attributes instead

        // Priority 3: Try to get from request attributes (fallback)
        // Use provided request or get from RequestStack
        if (null === $request && null !== $this->requestStack) {
            $request = $this->requestStack->getMainRequest();
        }

        if (null !== $request) {
            try {
                // Try multiple ways to get the profiler from request
                $profilerProfile = $request->attributes->get('_profiler');

                // If not in attributes, try to get from parent request (for sub-requests)
                if (null === $profilerProfile && null !== $this->requestStack) {
                    $parentRequest = $this->requestStack->getParentRequest();
                    if (null !== $parentRequest) {
                        $profilerProfile = $parentRequest->attributes->get('_profiler');
                    }
                }

                // Also try _profiler_profile (alternative attribute name)
                if (null === $profilerProfile) {
                    $profilerProfile = $request->attributes->get('_profiler_profile');
                }

                if (null !== $profilerProfile) {
                    // Try to get DoctrineDataCollector
                    $doctrineCollector = null;

                    // Method 1: Direct get() call
                    if (method_exists($profilerProfile, 'get')) {
                        $doctrineCollector = $profilerProfile->get('doctrine');
                    }

                    // Method 2: getCollector() method
                    if (null === $doctrineCollector && method_exists($profilerProfile, 'getCollector')) {
                        $doctrineCollector = $profilerProfile->getCollector('doctrine');
                    }

                    // Method 3: getCollectors() and find 'db' or 'doctrine'
                    if (null === $doctrineCollector && method_exists($profilerProfile, 'getCollectors')) {
                        $collectors = $profilerProfile->getCollectors();
                        $doctrineCollector = $collectors['db'] ?? $collectors['doctrine'] ?? null;
                    }

                    if ($doctrineCollector instanceof DoctrineDataCollector) {
                        // Get query count and time from Doctrine DataCollector
                        $queryCount = $doctrineCollector->getQueryCount();
                        $queryTime = $doctrineCollector->getTime() / 1000.0; // Convert from milliseconds to seconds

                        // If we got valid data, return it
                        if ($queryCount > 0 || $queryTime > 0) {
                            return ['count' => $queryCount, 'time' => $queryTime];
                        }
                    }
                }
            } catch (\Exception $e) {
                // Silently fail and try fallback
            }
        }

        // Priority 3: Fallback to Stopwatch for time tracking only
        // Note: Stopwatch won't give us accurate query count
        if (null !== $this->stopwatch) {
            try {
                if ($this->stopwatch->isStarted('doctrine.queries')) {
                    $event = $this->stopwatch->getEvent('doctrine.queries');
                    if (null !== $event) {
                        $queryTime = $event->getDuration() / 1000.0; // Convert from milliseconds to seconds
                    }
                }
            } catch (\Exception $e) {
                // Silently fail
            }
        }

        return ['count' => $queryCount, 'time' => $queryTime];
    }
}
