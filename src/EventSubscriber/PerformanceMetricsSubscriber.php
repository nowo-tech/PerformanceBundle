<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\EventSubscriber;

use Doctrine\DBAL\Logging\Middleware;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\DataCollector\PerformanceDataCollector;
use Nowo\PerformanceBundle\DBAL\QueryTrackingMiddleware;
use Nowo\PerformanceBundle\Helper\LogHelper;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;
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
     * Kernel interface for getting environment.
     */
    private readonly ?KernelInterface $kernel;

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
        #[Autowire('%nowo_performance.track_sub_requests%')]
        private readonly bool $trackSubRequests = false,
        #[Autowire('%nowo_performance.async%')]
        private readonly bool $async = false,
        #[Autowire('%nowo_performance.sampling_rate%')]
        private readonly float $samplingRate = 1.0,
        #[Autowire('%nowo_performance.track_status_codes%')]
        private readonly array $trackStatusCodes = [200, 404, 500, 503],
        #[Autowire('%nowo_performance.enable_logging%')]
        private readonly bool $enableLogging = true,
        private readonly ?RequestStack $requestStack = null,
        #[Autowire(service: '?stopwatch')]
        ?Stopwatch $stopwatch = null,
        #[Autowire(service: '?kernel')]
        ?KernelInterface $kernel = null,
    ) {
        $this->dataCollector->setEnabled($enabled);
        $this->dataCollector->setAsync($async);
        $this->stopwatch = $stopwatch;
        $this->kernel = $kernel;
    }

    /**
     * Get the subscribed kernel events.
     *
     * Note: Events are registered via #[AsEventListener] attributes on methods,
     * but this method is kept for compatibility with EventSubscriberInterface.
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
    #[AsEventListener(event: KernelEvents::REQUEST, priority: 1024)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$this->enabled) {
            LogHelper::log('[PerformanceBundle] Tracking disabled: enabled=false', $this->enableLogging);
            $this->dataCollector->setEnabled(false);
            $this->dataCollector->setDisabledReason('Bundle is disabled in configuration (nowo_performance.enabled: false)');
            LogHelper::logf(
                '[PerformanceBundle] DataCollector setEnabled(false) - reason: Bundle disabled, isEnabled()=%s',
                $this->enableLogging,
                $this->dataCollector->isEnabled() ? 'true' : 'false'
            );

            return;
        }

        if (!$event->isMainRequest() && !$this->trackSubRequests) {
            LogHelper::log('[PerformanceBundle] Tracking disabled: not main request (sub-request) and track_sub_requests is disabled', $this->enableLogging);
            $this->dataCollector->setEnabled(false);
            $this->dataCollector->setDisabledReason('Not a main request (sub-request). Enable track_sub_requests to track sub-requests.');
            LogHelper::logf(
                '[PerformanceBundle] DataCollector setEnabled(false) - reason: Sub-request, isEnabled()=%s',
                $this->enableLogging,
                $this->dataCollector->isEnabled() ? 'true' : 'false'
            );

            return;
        }

        $request = $event->getRequest();

        // Try multiple methods to detect environment
        $env = null;
        if (null !== $this->kernel) {
            $env = $this->kernel->getEnvironment();
        } elseif ($request->server->has('APP_ENV')) {
            $env = $request->server->get('APP_ENV');
        } elseif (isset($_SERVER['APP_ENV'])) {
            $env = $_SERVER['APP_ENV'];
        } elseif (isset($_ENV['APP_ENV'])) {
            $env = $_ENV['APP_ENV'];
        } else {
            $env = 'dev'; // Default fallback
        }

        // Set environment information in collector
        $this->dataCollector->setConfiguredEnvironments($this->environments);
        $this->dataCollector->setCurrentEnvironment($env);

        LogHelper::logf('[PerformanceBundle] Environment detection: kernel=%s, detected_env=%s, allowed=%s', $this->enableLogging,
            null !== $this->kernel ? $this->kernel->getEnvironment() : 'null',
            $env,
            implode(', ', $this->environments)
        );

        if (!\in_array($env, $this->environments, true)) {
            LogHelper::logf('[PerformanceBundle] Tracking disabled: env=%s not in allowed environments: %s', $this->enableLogging, $env, implode(', ', $this->environments));
            $this->dataCollector->setEnabled(false);
            $this->dataCollector->setDisabledReason(\sprintf('Environment "%s" is not in allowed environments: %s', $env, implode(', ', $this->environments)));
            LogHelper::logf(
                '[PerformanceBundle] DataCollector setEnabled(false) - reason: Environment not allowed, isEnabled()=%s',
                $this->enableLogging,
                $this->dataCollector->isEnabled() ? 'true' : 'false'
            );

            return;
        }

        $this->dataCollector->setEnabled(true);
        $this->dataCollector->setDisabledReason(null); // Clear any previous reason
        LogHelper::logf(
            '[PerformanceBundle] DataCollector setEnabled(true) - isEnabled()=%s',
            $this->enableLogging,
            $this->dataCollector->isEnabled() ? 'true' : 'false'
        );

        // Get route name
        $this->routeName = $request->attributes->get('_route');
        $this->dataCollector->setRouteName($this->routeName);

        LogHelper::logf(
            '[PerformanceBundle] onKernelRequest: Collector enabled, route=%s, env=%s, trackQueries=%s, trackRequestTime=%s, trackSubRequests=%s',
            $this->enableLogging,
            $this->routeName ?? 'null',
            $env,
            $this->trackQueries ? 'true' : 'false',
            $this->trackRequestTime ? 'true' : 'false',
            $this->trackSubRequests ? 'true' : 'false'
        );

        // Skip ignored routes
        if (null !== $this->routeName && \in_array($this->routeName, $this->ignoreRoutes, true)) {
            LogHelper::logf('[PerformanceBundle] Tracking disabled: route "%s" is in ignore_routes list', $this->enableLogging, $this->routeName);
            $this->dataCollector->setEnabled(false);
            $this->dataCollector->setDisabledReason(\sprintf('Route "%s" is in ignore_routes list', $this->routeName));
            LogHelper::logf(
                '[PerformanceBundle] DataCollector setEnabled(false) - reason: Route ignored, isEnabled()=%s',
                $this->enableLogging,
                $this->dataCollector->isEnabled() ? 'true' : 'false'
            );
            // Inform collector that route is ignored
            $this->dataCollector->setRecordOperation(false, false);

            return;
        }

        // Only log if route name is available (to reduce noise from asset/profiler routes)
        if (null !== $this->routeName) {
            $requestType = $event->isMainRequest() ? 'main' : 'sub';
            LogHelper::logf('[PerformanceBundle] Tracking enabled: route="%s", env=%s, request_type=%s', $this->enableLogging, $this->routeName, $env, $requestType);
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
    #[AsEventListener(event: KernelEvents::TERMINATE, priority: -1024)]
    public function onKernelTerminate(TerminateEvent $event): void
    {
        // Check collector state before logging
        $collectorEnabled = $this->dataCollector->isEnabled();
        $disabledReason = $this->dataCollector->getDisabledReason();
        
        LogHelper::logf(
            '[PerformanceBundle] onKernelTerminate: START - enabled=%s, collectorEnabled=%s, route=%s, disabledReason=%s',
            $this->enableLogging,
            $this->enabled ? 'true' : 'false',
            $collectorEnabled ? 'true' : 'false',
            $this->routeName ?? 'null',
            $disabledReason ?? 'null'
        );

        if (!$this->enabled) {
            if (\function_exists('error_log')) {
                error_log('[PerformanceBundle] onKernelTerminate: enabled=false, skipping');
            }
            // Inform collector that tracking is disabled
            $this->dataCollector->setRecordOperation(false, false);

            return;
        }

        if (!$this->dataCollector->isEnabled()) {
            // Log why collector is disabled (for debugging)
            $disabledReason = $this->dataCollector->getDisabledReason();
            
            // Use reflection to check the actual property value vs what isEnabled() returns
            $reflection = new \ReflectionClass($this->dataCollector);
            $enabledProperty = $reflection->getProperty('enabled');
            $enabledProperty->setAccessible(true);
            $enabledPropertyValue = $enabledProperty->getValue($this->dataCollector);
            
            $dataProperty = $reflection->getProperty('data');
            $dataProperty->setAccessible(true);
            $dataArray = $dataProperty->getValue($this->dataCollector);
            $dataArrayEnabled = $dataArray['enabled'] ?? null;
            
            LogHelper::logf(
                '[PerformanceBundle] onKernelTerminate: dataCollector not enabled, skipping. Reason: %s, route=%s, enabledProperty=%s, dataArrayEnabled=%s, isEnabled()=%s',
                $this->enableLogging,
                $disabledReason ?? 'Unknown',
                $this->routeName ?? 'null',
                $enabledPropertyValue ? 'true' : 'false',
                null !== $dataArrayEnabled ? ($dataArrayEnabled ? 'true' : 'false') : 'null',
                $this->dataCollector->isEnabled() ? 'true' : 'false'
            );
            // Inform collector that collector is disabled
            $this->dataCollector->setRecordOperation(false, false);

            return;
        }

        $request = $event->getRequest();

        // Try multiple methods to detect environment
        $env = null;
        if (null !== $this->kernel) {
            $env = $this->kernel->getEnvironment();
        } elseif ($request->server->has('APP_ENV')) {
            $env = $request->server->get('APP_ENV');
        } elseif (isset($_SERVER['APP_ENV'])) {
            $env = $_SERVER['APP_ENV'];
        } elseif (isset($_ENV['APP_ENV'])) {
            $env = $_ENV['APP_ENV'];
        } else {
            $env = 'dev'; // Default fallback
        }

        // Get route name here, as it should be resolved by now
        $routeNameFromRequest = $request->attributes->get('_route');
        $this->routeName = $this->routeName ?? $routeNameFromRequest;
        $this->dataCollector->setRouteName($this->routeName);

        LogHelper::logf(
            '[PerformanceBundle] onKernelTerminate: Route check - storedRoute=%s, requestRoute=%s',
            $this->enableLogging,
            $this->routeName ?? 'null',
            $routeNameFromRequest ?? 'null'
        );

        if (null === $this->routeName) {
            LogHelper::log('[PerformanceBundle] onKernelTerminate: routeName is null, skipping', $this->enableLogging);
            // Inform collector that no route name was available
            $this->dataCollector->setRecordOperation(false, false);

            return;
        }

        if (!\in_array($env, $this->environments, true)) {
            LogHelper::logf(
                '[PerformanceBundle] onKernelTerminate: env=%s not in allowed environments (%s), skipping',
                $this->enableLogging,
                $env,
                implode(', ', $this->environments)
            );
            // Inform collector that environment is not allowed
            $this->dataCollector->setRecordOperation(false, false);

            return;
        }

        // Calculate request time
        $requestTime = null;
        if ($this->trackRequestTime && null !== $this->startTime) {
            $requestTime = microtime(true) - $this->startTime;
            $this->dataCollector->setRequestTime($requestTime);
            LogHelper::logf(
                '[PerformanceBundle] onKernelTerminate: Request time calculated: %s seconds',
                $this->enableLogging,
                (string) $requestTime
            );
        } else {
            LogHelper::logf(
                '[PerformanceBundle] onKernelTerminate: Request time not tracked (trackRequestTime=%s, startTime=%s)',
                $this->enableLogging,
                $this->trackRequestTime ? 'true' : 'false',
                null !== $this->startTime ? 'set' : 'null'
            );
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
            LogHelper::logf(
                '[PerformanceBundle] onKernelTerminate: Query metrics: count=%s, time=%s',
                $this->enableLogging,
                (string) $queryCount,
                (string) $queryTime
            );
        } else {
            LogHelper::log('[PerformanceBundle] onKernelTerminate: Query tracking disabled', $this->enableLogging);
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
            LogHelper::logf(
                '[PerformanceBundle] onKernelTerminate: Memory usage: %s bytes',
                $this->enableLogging,
                (string) $memoryUsage
            );
        } else {
            LogHelper::log('[PerformanceBundle] onKernelTerminate: Memory tracking not started (startMemory is null)', $this->enableLogging);
        }

        // Get HTTP method
        $httpMethod = $request->getMethod();

        // Get HTTP status code from response
        $statusCode = null;
        $response = $event->getResponse();
        if (null !== $response) {
            $statusCode = $response->getStatusCode();
        }

        // Apply sampling: skip recording if random value is above sampling rate
        if ($this->samplingRate < 1.0 && mt_rand(1, 10000) / 10000 > $this->samplingRate) {
            // Sampling: skip this request
            LogHelper::logf(
                '[PerformanceBundle] onKernelTerminate: skipping due to sampling (rate=%.2f%%)',
                $this->enableLogging,
                $this->samplingRate * 100
            );
            // Inform collector that no data was saved due to sampling
            $this->dataCollector->setRecordOperation(false, false);

            return;
        }

        // Record metrics - ensure no output is generated
        try {
            // Suppress error reporting temporarily to prevent warnings from generating output
            $errorReporting = error_reporting(0);

            // Use output buffering to catch any potential output
            // Only start a new buffer if we're not already in one (to avoid closing buffers we didn't open)
            $obLevel = ob_get_level();
            $obStarted = false;
            if (0 === $obLevel) {
                ob_start();
                $obStarted = true;
            }

            LogHelper::logf(
                '[PerformanceBundle] Attempting to save metrics: route=%s, env=%s, method=%s, statusCode=%s, requestTime=%s, queryCount=%s, queryTime=%s, memoryUsage=%s, samplingRate=%s',
                $this->enableLogging,
                $this->routeName ?? 'null',
                $env,
                $httpMethod,
                null !== $statusCode ? (string) $statusCode : 'null',
                null !== $requestTime ? (string) $requestTime : 'null',
                null !== $queryCount ? (string) $queryCount : 'null',
                null !== $queryTime ? (string) $queryTime : 'null',
                null !== $memoryUsage ? (string) $memoryUsage : 'null',
                (string) ($this->samplingRate * 100) . '%'
            );

            $result = $this->metricsService->recordMetrics(
                $this->routeName,
                $env,
                $requestTime,
                $queryCount,
                $queryTime,
                $this->routeParams,
                $memoryUsage,
                $httpMethod,
                $statusCode,
                $this->trackStatusCodes
            );

            LogHelper::logf(
                '[PerformanceBundle] recordMetrics returned: is_new=%s, was_updated=%s',
                $this->enableLogging,
                $result['is_new'] ? 'true' : 'false',
                $result['was_updated'] ? 'true' : 'false'
            );

            // Set record operation information in the collector
            // Always set this, even if result indicates no changes (was_updated = false)
            if (isset($result['is_new'], $result['was_updated'])) {
                $this->dataCollector->setRecordOperation($result['is_new'], $result['was_updated']);

                LogHelper::logf(
                    '[PerformanceBundle] Metrics saved successfully for route: %s (is_new=%s, was_updated=%s)',
                    $this->enableLogging,
                    $this->routeName ?? 'null',
                    $result['is_new'] ? 'true' : 'false',
                    $result['was_updated'] ? 'true' : 'false'
                );
            } else {
                // If result doesn't have the expected keys, log warning and assume no operation occurred
                LogHelper::logf(
                    '[PerformanceBundle] WARNING: recordMetrics returned unexpected result format for route: %s. Result keys: %s',
                    $this->enableLogging,
                    $this->routeName ?? 'null',
                    implode(', ', array_keys($result))
                );
                // Still set the operation to indicate we tried (even if it failed)
                $this->dataCollector->setRecordOperation(false, false);
            }

            // Clean output buffer only if we started it
            if ($obStarted && ob_get_level() > 0) {
                ob_end_clean();
            }

            // Restore error reporting
            error_reporting($errorReporting);
        } catch (\Exception $e) {
            // Restore error reporting
            if (isset($errorReporting)) {
                error_reporting($errorReporting);
            }

            // Clean output buffer only if we started it
            if (isset($obStarted) && $obStarted && ob_get_level() > 0) {
                ob_end_clean();
            }

            // Log the error for debugging
            LogHelper::logf(
                '[PerformanceBundle] Error saving metrics for route %s: %s (file: %s, line: %s)',
                $this->enableLogging,
                $this->routeName ?? 'null',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            );

            // Inform collector that save failed
            $this->dataCollector->setRecordOperation(false, false);

            // Silently fail to not break the application
            // In production, you might want to log this
        } finally {
            // Ensure setRecordOperation is always called, even if there was an unexpected error
            // This prevents "Unknown" status in the collector
            if (null === $this->dataCollector->wasRecordNew() && null === $this->dataCollector->wasRecordUpdated()) {
                // If we reach here and the operation status is still null, something went wrong
                // Set it to indicate we tried but failed
                $this->dataCollector->setRecordOperation(false, false);
            }

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
