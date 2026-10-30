<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\EventSubscriber;

use Exception;
use Nowo\PerformanceBundle\DataCollector\PerformanceDataCollector;
use Nowo\PerformanceBundle\DBAL\QueryTrackingCounters;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;
use WeakMap;

use function in_array;
use function is_array;
use function is_object;
use function is_string;
use function max;
use function sprintf;

use const FNM_NOESCAPE;

/**
 * Event subscriber for tracking route performance metrics.
 *
 * Listens to kernel request and terminate events to collect and store
 * performance metrics for routes including request time, query count, and query time.
 *
 * Sub-requests (render(controller()), ESI, the ErrorListener error page) never touch the
 * main request's state. With track_sub_requests they are measured with their own state
 * (kernel.response of the sub-request) and recorded at kernel.terminate.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class PerformanceMetricsSubscriber implements EventSubscriberInterface, ResetInterface
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
     * Current route name being tracked.
     */
    private ?string $routeName = null;

    /**
     * Route parameters for the current request.
     *
     * @var array<string, mixed>|null
     */
    private ?array $routeParams = null;

    /**
     * Unique request ID for this HTTP request (used to avoid duplicate access records).
     */
    private ?string $requestId = null;

    /**
     * In-flight sub-request measurements (track_sub_requests), keyed by sub-request.
     *
     * @var WeakMap<Request, array{env: string, route: string, params: array<string, mixed>, startTime: float|null, startMemory: int, queryCount: int, queryTime: float}>
     */
    private WeakMap $subRequestStates;

    /**
     * Finished sub-request measurements waiting to be recorded at kernel.terminate.
     *
     * @var list<array{route: string, env: string, requestTime: float|null, queryCount: int|null, queryTime: float|null, params: array<string, mixed>, memoryUsage: int, method: string, statusCode: int, referer: string|null, routePath: string}>
     */
    private array $pendingSubRequestMetrics = [];

    /**
     * Creates a new instance.
     *
     * @param PerformanceMetricsService $metricsService Service for recording metrics
     * @param PerformanceDataCollector $dataCollector Data collector for WebProfiler
     * @param array<string> $environments Allowed environments
     * @param array<string> $ignoreRoutes Route name patterns to ignore
     */
    public function __construct(
        private readonly PerformanceMetricsService $metricsService,
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
        /** @var array<int> */
        #[Autowire('%nowo_performance.track_status_codes%')]
        private readonly array $trackStatusCodes = [200, 404, 500, 503],
        #[Autowire('%nowo_performance.enable_logging%')]
        private readonly bool $enableLogging = true,
        #[Autowire('%nowo_performance.track_user%')]
        private readonly bool $trackUser = false,
        private readonly ?RequestStack $requestStack = null,
        /**
         * Security helper (`security.helper`, optional), wired in services.yaml: `#[Autowire(service: '?…')]` is not an
         * optional reference (the id keeps the `?`) and compiled to null.
         */
        private readonly ?object $security = null,
        /**
         * Stopwatch for the query-time fallback. Not wired by the bundle (services.yaml passes null): the fallback
         * resets it, so it must never be the shared debug stopwatch of the profiler.
         */
        private readonly ?Stopwatch $stopwatch = null,
        /**
         * Kernel interface for getting environment (autowired by type).
         */
        private readonly ?KernelInterface $kernel = null,
        private readonly ?QueryTrackingCounters $queryCounters = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->subRequestStates = new WeakMap();
        // @igor-ignore - Event subscriber clears request/runtime bridges; not cross-request leak.
        $this->dataCollector->setEnabled($enabled);
        // @igor-ignore - Event subscriber clears request/runtime bridges; not cross-request leak.
        $this->dataCollector->setAsync($async);
    }

    /**
     * Get the subscribed kernel events.
     *
     * Registered solely via EventSubscriberInterface / kernel.event_subscriber
     * (do not also add #[AsEventListener] on these methods — that double-registers).
     *
     * @return array<string, array{0: string, 1: int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST   => ['onKernelRequest', 31],
            KernelEvents::RESPONSE  => ['onKernelResponse', -1024],
            KernelEvents::TERMINATE => ['onKernelTerminate', -1024],
        ];
    }

    /**
     * Clears per-request state (kernel.reset) so a long-running worker never carries it over.
     */
    public function reset(): void
    {
        $this->startTime                = null;
        $this->startMemory              = null;
        $this->routeName                = null;
        $this->routeParams              = null;
        $this->requestId                = null;
        $this->subRequestStates         = new WeakMap();
        $this->pendingSubRequestMetrics = [];
    }

    /**
     * Handle the kernel request event.
     *
     * Initializes performance tracking for the current request if enabled
     * and the environment is configured for tracking.
     *
     * Priority must be lower than RouterListener (32) so _route is set before we check ignore_routes.
     * Otherwise _route is always null and ignore_routes never applies.
     *
     * @param RequestEvent $event The request event
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Sub-requests (render(controller()), ESI, the ErrorListener error page) must not overwrite the
        // main request's route, timers or collector: return before touching any of that state.
        if (!$event->isMainRequest()) {
            $this->startSubRequestTracking($request);

            return;
        }

        // Long-running runtimes (FrankenPHP worker) may not reset services between requests:
        // start every main request with a clean collector state.
        $this->dataCollector->reset();
        // @igor-ignore - Event subscriber clears request/runtime bridges; not cross-request leak.
        $this->dataCollector->setAsync($this->async);
        // @igor-ignore - Event subscriber clears request/runtime bridges; not cross-request leak.
        $this->subRequestStates = new WeakMap();
        // @igor-ignore - Event subscriber clears request/runtime bridges; not cross-request leak.
        $this->pendingSubRequestMetrics = [];

        // Minimal setup for collector (route + env) so toolbar can show "disabled" with context
        $env = $this->detectEnvironment($request);
        // @igor-ignore - Event subscriber clears request/runtime bridges; not cross-request leak.
        $this->dataCollector->setConfiguredEnvironments($this->environments);
        // @igor-ignore - Event subscriber clears request/runtime bridges; not cross-request leak.
        $this->dataCollector->setCurrentEnvironment($env);
        // @igor-ignore - Event subscriber clears request/runtime bridges; not cross-request leak.
        $this->routeName = $request->attributes->get('_route');
        // @igor-ignore - Event subscriber clears request/runtime bridges; not cross-request leak.
        $this->dataCollector->setRouteName($this->routeName);

        // When bundle is disabled: avoid any further work (no metrics, no query tracking, no DB)
        if (!$this->enabled) {
            $this->bundleLog('debug', '[PerformanceBundle] Tracking disabled: enabled=false');
            // @igor-ignore - Event subscriber clears request/runtime bridges; not cross-request leak.
            $this->dataCollector->setEnabled(false);
            // @igor-ignore - Event subscriber clears request/runtime bridges; not cross-request leak.
            $this->dataCollector->setDisabledReason('Bundle is disabled in configuration (nowo_performance.enabled: false)');

            return;
        }

        $this->bundleLog(
            'debug',
            '[PerformanceBundle] Environment detection: kernel=%s, detected_env=%s, allowed=%s',
            $this->kernel instanceof KernelInterface ? $this->kernel->getEnvironment() : 'null',
            $env,
            implode(', ', $this->environments),
        );

        if (!in_array($env, $this->environments, true)) {
            $this->bundleLog('debug', '[PerformanceBundle] Tracking disabled: env=%s not in allowed environments: %s', $env, implode(', ', $this->environments));
            // @igor-ignore - Event subscriber clears request/runtime bridges; not cross-request leak.
            $this->dataCollector->setEnabled(false);
            // @igor-ignore - Event subscriber clears request/runtime bridges; not cross-request leak.
            $this->dataCollector->setDisabledReason(sprintf('Environment "%s" is not in allowed environments: %s', $env, implode(', ', $this->environments)));
            $this->bundleLog(
                'debug',
                '[PerformanceBundle] DataCollector setEnabled(false) - reason: Environment not allowed, isEnabled()=%s',
                $this->dataCollector->isEnabled() ? 'true' : 'false',
            );

            return;
        }

        // @igor-ignore - Event subscriber clears request/runtime bridges; not cross-request leak.
        $this->dataCollector->setEnabled(true);
        // @igor-ignore - Event subscriber clears request/runtime bridges; not cross-request leak.
        $this->dataCollector->setDisabledReason(null); // Clear any previous reason
        $this->bundleLog(
            'debug',
            '[PerformanceBundle] DataCollector setEnabled(true) - isEnabled()=%s',
            $this->dataCollector->isEnabled() ? 'true' : 'false',
        );

        $this->bundleLog(
            'debug',
            '[PerformanceBundle] onKernelRequest: Collector enabled, route=%s, env=%s, trackQueries=%s, trackRequestTime=%s, trackSubRequests=%s',
            $this->routeName ?? 'null',
            $env,
            $this->trackQueries ? 'true' : 'false',
            $this->trackRequestTime ? 'true' : 'false',
            $this->trackSubRequests ? 'true' : 'false',
        );

        // When route is ignored: stop all tracking for this request (no metrics, no query tracking, no DB)
        if ($this->routeName !== null && $this->isRouteIgnored($this->routeName)) {
            // @igor-ignore - Event subscriber clears request/runtime bridges; not cross-request leak.
            $this->dataCollector->setEnabled(false);
            // @igor-ignore - Event subscriber clears request/runtime bridges; not cross-request leak.
            $this->dataCollector->setDisabledReason(sprintf('Route "%s" is in ignore_routes list', $this->routeName));
            // @igor-ignore - Event subscriber clears request/runtime bridges; not cross-request leak.
            $this->dataCollector->setRecordOperation(false, false);

            return;
        }

        // Only log if route name is available (to reduce noise from asset/profiler routes)
        if ($this->routeName !== null) {
            $this->bundleLog('debug', '[PerformanceBundle] Tracking enabled: route="%s", env=%s, request_type=main', $this->routeName, $env);
        }

        // Start timing
        if ($this->trackRequestTime) {
            // @igor-ignore - Event subscriber clears request/runtime bridges; not cross-request leak.
            $this->startTime = microtime(true);
            // @igor-ignore - Event subscriber clears request/runtime bridges; not cross-request leak.
            $this->dataCollector->setStartTime($this->startTime);
        }

        // Track initial memory usage. The peak is process-wide, so it must be reset per main request
        // or a long-running worker would report the highest peak since it started.
        memory_reset_peak_usage();
        // @igor-ignore - Event subscriber clears request/runtime bridges; not cross-request leak.
        $this->startMemory = memory_get_usage(true);

        // Unique request ID for deduplication: one access record per main request
        // @igor-ignore - Event subscriber clears request/runtime bridges; not cross-request leak.
        $this->requestId = bin2hex(random_bytes(16));
        $request->attributes->set('_performance_request_id', $this->requestId);

        // Track queries if enabled
        if ($this->trackQueries) {
            $this->startQueryTracking();
        }

        // Get route parameters
        // @igor-ignore - Event subscriber clears request/runtime bridges; not cross-request leak.
        $this->routeParams = $request->attributes->get('_route_params', []);
    }

    /**
     * Handle the kernel response event: finishes the measurement of a tracked sub-request.
     *
     * Main requests are measured at kernel.terminate; this only snapshots sub-requests (their own
     * timers, query deltas and status code) and queues them for recording at kernel.terminate, so
     * no database write happens while the main response is being rendered.
     *
     * @param ResponseEvent $event The response event
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        if ($event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!isset($this->subRequestStates[$request])) {
            return;
        }

        $state = $this->subRequestStates[$request];
        unset($this->subRequestStates[$request]);

        $queryCount = null;
        $queryTime  = null;
        if ($this->trackQueries && $this->queryCounters instanceof QueryTrackingCounters) {
            $queryCount = max(0, $this->queryCounters->getQueryCount() - $state['queryCount']);
            $queryTime  = max(0.0, $this->queryCounters->getTotalQueryTime() - $state['queryTime']);
        }

        $queryString = $request->getQueryString();

        // @igor-ignore - Event subscriber clears request/runtime bridges; not cross-request leak.
        $this->pendingSubRequestMetrics[] = [
            'route'       => $state['route'],
            'env'         => $state['env'],
            'requestTime' => $state['startTime'] !== null ? microtime(true) - $state['startTime'] : null,
            'queryCount'  => $queryCount,
            'queryTime'   => $queryTime,
            'params'      => $state['params'],
            'memoryUsage' => max(0, memory_get_peak_usage(true) - $state['startMemory']),
            'method'      => $request->getMethod(),
            'statusCode'  => $event->getResponse()->getStatusCode(),
            'referer'     => $request->headers->get('Referer'),
            'routePath'   => $request->getPathInfo() . (is_string($queryString) && $queryString !== '' ? '?' . $queryString : ''),
        ];
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
        $this->recordPendingSubRequests();

        // When bundle is disabled: exit immediately without any work (no DB, no reflection, no logging)
        if (!$this->enabled) {
            // @igor-ignore - Event subscriber clears request/runtime bridges; not cross-request leak.
            $this->dataCollector->setRecordOperation(false, false);

            return;
        }

        if (!$this->dataCollector->isEnabled()) {
            // @igor-ignore - Event subscriber clears request/runtime bridges; not cross-request leak.
            $this->dataCollector->setRecordOperation(false, false);

            return;
        }

        $request = $event->getRequest();

        $env = $this->detectEnvironment($request);

        // Get route name here, as it should be resolved by now.
        // If request no longer has _route at terminate (route lost), clear and skip saving.
        $routeNameFromRequest = $request->attributes->get('_route');
        // @igor-ignore - Event subscriber clears request/runtime bridges; not cross-request leak.
        $this->routeName = $routeNameFromRequest === null ? null : $this->routeName ?? $routeNameFromRequest;
        // @igor-ignore - Event subscriber clears request/runtime bridges; not cross-request leak.
        $this->dataCollector->setRouteName($this->routeName);

        $this->bundleLog(
            'debug',
            '[PerformanceBundle] onKernelTerminate: Route check - storedRoute=%s, requestRoute=%s',
            $this->routeName ?? 'null',
            $routeNameFromRequest ?? 'null',
        );

        if ($this->routeName === null) {
            $this->bundleLog('debug', '[PerformanceBundle] onKernelTerminate: routeName is null, skipping');
            // Inform collector that no route name was available
            // @igor-ignore - Event subscriber clears request/runtime bridges; not cross-request leak.
            $this->dataCollector->setRecordOperation(false, false);

            return;
        }

        if (!in_array($env, $this->environments, true)) {
            $this->bundleLog(
                'debug',
                '[PerformanceBundle] onKernelTerminate: env=%s not in allowed environments (%s), skipping',
                $env,
                implode(', ', $this->environments),
            );
            // Inform collector that environment is not allowed
            // @igor-ignore - Event subscriber clears request/runtime bridges; not cross-request leak.
            $this->dataCollector->setRecordOperation(false, false);

            return;
        }

        // Calculate request time
        $requestTime = null;
        if ($this->trackRequestTime && $this->startTime !== null) {
            $requestTime = microtime(true) - $this->startTime;
            // @igor-ignore - Event subscriber clears request/runtime bridges; not cross-request leak.
            $this->dataCollector->setRequestTime($requestTime);
            $this->bundleLog(
                'debug',
                '[PerformanceBundle] onKernelTerminate: Request time calculated: %s seconds',
                (string) $requestTime,
            );
        } else {
            $this->bundleLog(
                'debug',
                '[PerformanceBundle] onKernelTerminate: Request time not tracked (trackRequestTime=%s, startTime=%s)',
                $this->trackRequestTime ? 'true' : 'false',
                $this->startTime !== null ? 'set' : 'null',
            );
        }

        // Get query metrics BEFORE stopping query tracking
        $queryCount = null;
        $queryTime  = null;
        if ($this->trackQueries) {
            $metrics    = $this->getQueryMetrics($request);
            $queryCount = $metrics['count'];
            $queryTime  = $metrics['time'];
            // @igor-ignore - Event subscriber clears request/runtime bridges; not cross-request leak.
            $this->dataCollector->setQueryCount($queryCount);
            // @igor-ignore - Event subscriber clears request/runtime bridges; not cross-request leak.
            $this->dataCollector->setQueryTime($queryTime);
            $this->bundleLog(
                'debug',
                '[PerformanceBundle] onKernelTerminate: Query metrics: count=%s, time=%s',
                (string) $queryCount,
                (string) $queryTime,
            );
        } else {
            $this->bundleLog('debug', '[PerformanceBundle] onKernelTerminate: Query tracking disabled');
        }

        // Calculate peak memory usage
        $memoryUsage = null;
        if ($this->startMemory !== null) {
            $peakMemory  = memory_get_peak_usage(true);
            $memoryUsage = $peakMemory - $this->startMemory;
            // Ensure non-negative (in case memory was freed)
            if ($memoryUsage < 0) {
                $memoryUsage = $peakMemory;
            }
            $this->bundleLog(
                'debug',
                '[PerformanceBundle] onKernelTerminate: Memory usage: %s bytes',
                (string) $memoryUsage,
            );
        } else {
            $this->bundleLog('debug', '[PerformanceBundle] onKernelTerminate: Memory tracking not started (startMemory is null)');
        }

        // Get HTTP method
        $httpMethod = $request->getMethod();

        // Get HTTP status code from response
        $statusCode = null;
        $response   = $event->getResponse();
        $statusCode = $response->getStatusCode();

        // Apply sampling: skip recording if random value is above sampling rate
        if ($this->samplingRate < 1.0 && mt_rand(1, 10000) / 10000 > $this->samplingRate) {
            // Sampling: skip this request
            $this->bundleLog(
                'debug',
                '[PerformanceBundle] onKernelTerminate: skipping due to sampling (rate=%.2f%%)',
                $this->samplingRate * 100,
            );
            // Inform collector that no data was saved due to sampling
            // @igor-ignore - Event subscriber clears request/runtime bridges; not cross-request leak.
            $this->dataCollector->setRecordOperation(false, false);

            return;
        }

        // Record metrics - ensure no output is generated (best effort, see openOutputBuffer()).
        $obStarted = $this->openOutputBuffer();

        try {
            $this->bundleLog(
                'debug',
                '[PerformanceBundle] Attempting to save metrics: route=%s, env=%s, method=%s, statusCode=%s, requestTime=%s, queryCount=%s, queryTime=%s, memoryUsage=%s, samplingRate=%s',
                $this->routeName ?? 'null',
                $env,
                $httpMethod,
                (string) $statusCode,
                $requestTime !== null ? (string) $requestTime : 'null',
                $queryCount !== null ? (string) $queryCount : 'null',
                $queryTime !== null ? (string) $queryTime : 'null',
                $memoryUsage !== null ? (string) $memoryUsage : 'null',
                ($this->samplingRate * 100) . '%',
            );

            $referer = $request->headers->get('Referer');

            [$userIdentifier, $userId] = $this->resolveUser();

            $pathInfo    = $request->getPathInfo();
            $queryString = $request->getQueryString();
            $routePath   = $pathInfo . (is_string($queryString) && $queryString !== '' ? '?' . $queryString : '');

            // "@" silences warnings (no output) and, unlike error_reporting(0), the engine restores the
            // process-wide level even when an exception is thrown, so a long-running worker never keeps it.
            $result = @$this->metricsService->recordMetrics(
                (string) $this->routeName, // non-null: checked at the start of onKernelTerminate()
                $env,
                $requestTime,
                $queryCount,
                $queryTime,
                $this->routeParams,
                $memoryUsage,
                $httpMethod,
                $statusCode,
                $this->trackStatusCodes,
                $this->requestId,
                $referer,
                $userIdentifier,
                $userId,
                $routePath,
            );

            $this->bundleLog(
                'debug',
                '[PerformanceBundle] recordMetrics returned: is_new=%s, was_updated=%s',
                ($result['is_new'] ?? false) ? 'true' : 'false',
                ($result['was_updated'] ?? false) ? 'true' : 'false',
            );

            // Set record operation information in the collector
            // Always set this, even if result indicates no changes (was_updated = false)
            if (isset($result['is_new'], $result['was_updated'])) {
                // @igor-ignore - Event subscriber clears request/runtime bridges; not cross-request leak.
                $this->dataCollector->setRecordOperation($result['is_new'], $result['was_updated']);

                $this->bundleLog(
                    'debug',
                    '[PerformanceBundle] Metrics saved successfully for route: %s (is_new=%s, was_updated=%s)',
                    $this->routeName ?? 'null',
                    $result['is_new'] ? 'true' : 'false',
                    $result['was_updated'] ? 'true' : 'false',
                );
            } else {
                // If result doesn't have the expected keys, log warning and assume no operation occurred
                $this->bundleLog(
                    'debug',
                    '[PerformanceBundle] WARNING: recordMetrics returned unexpected result format for route: %s. Result keys: %s',
                    $this->routeName ?? 'null',
                    implode(', ', array_keys($result)),
                );
                // Still set the operation to indicate we tried (even if it failed)
                // @igor-ignore - Event subscriber clears request/runtime bridges; not cross-request leak.
                $this->dataCollector->setRecordOperation(false, false);
            }
        } catch (Throwable $e) {
            // Log the error for debugging
            $this->bundleLog(
                'debug',
                '[PerformanceBundle] Error saving metrics for route %s: %s (file: %s, line: %s)',
                $this->routeName ?? 'null',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
            );

            $msg        = $e->getMessage();
            $schemaHint = str_contains($msg, 'Unknown column') || str_contains($msg, 'Column not found')
                || str_contains($msg, 'total_queries')
                || str_contains($msg, "Field 'id' doesn't have a default value")
                || str_contains($msg, "doesn't have a default value");
            if ($this->enableLogging && $schemaHint) {
                $this->bundleLog(
                    'debug',
                    '[PerformanceBundle] Hint: If routes_data_records has schema issues (missing columns, id without AUTO_INCREMENT), run: php bin/console nowo:performance:sync-schema',
                );
            }

            // Inform collector that save failed
            // @igor-ignore - Event subscriber clears request/runtime bridges; not cross-request leak.
            $this->dataCollector->setRecordOperation(false, false);

            // Silently fail to not break the application
            // In production, you might want to log this
        } finally {
            // Output buffers are process-wide: always close ours, otherwise a long-running worker keeps it.
            if ($obStarted && ob_get_level() > 0) {
                ob_end_clean();
            }

            // Ensure setRecordOperation is always called, even if there was an unexpected error
            // This prevents "Unknown" status in the collector
            if ($this->dataCollector->wasRecordNew() === null && $this->dataCollector->wasRecordUpdated() === null) {
                // If we reach here and the operation status is still null, something went wrong
                // Set it to indicate we tried but failed
                // @igor-ignore - Event subscriber clears request/runtime bridges; not cross-request leak.
                $this->dataCollector->setRecordOperation(false, false);
            }

            // Stop query tracking
            if ($this->trackQueries) {
                $this->stopQueryTracking();
            }
        }
    }

    /**
     * Opens an output buffer to swallow stray output while metrics are recorded, when that is possible.
     *
     * Only opens a buffer when none is active (never closes buffers we did not open). After the
     * response has been sent the output layer may already be shut down (FrankenPHP worker mode):
     * ob_start() then fails with "Failed to create buffer", a notice that a debug error handler
     * turns into an exception. That must never prevent the metrics from being recorded, so the
     * failure is silenced and recording continues without a buffer.
     *
     * @return bool True when a buffer was opened (and must be closed by the caller)
     */
    private function openOutputBuffer(): bool
    {
        if (ob_get_level() !== 0) {
            return false;
        }

        try {
            $started = @$this->startOutputBuffer();
        } catch (Throwable) {
            $started = false;
        }

        return $started && ob_get_level() > 0;
    }

    /**
     * Low-level ob_start() call (seam for tests emulating a runtime whose output layer is closed).
     */
    protected function startOutputBuffer(): bool
    {
        return ob_start();
    }

    /**
     * Starts the separate measurement of a sub-request when track_sub_requests is enabled.
     *
     * Never touches the main request's fields nor the data collector.
     */
    private function startSubRequestTracking(Request $request): void
    {
        if (!$this->enabled || !$this->trackSubRequests) {
            return;
        }

        $env = $this->detectEnvironment($request);
        if (!in_array($env, $this->environments, true)) {
            return;
        }

        $routeName = $request->attributes->get('_route');
        if (!is_string($routeName) || $routeName === '' || $this->isRouteIgnored($routeName)) {
            return;
        }

        $params = $request->attributes->get('_route_params', []);
        /** @var array<string, mixed> $routeParams */
        $routeParams = is_array($params) ? $params : [];

        // @igor-ignore - Event subscriber clears request/runtime bridges; not cross-request leak.
        $this->subRequestStates[$request] = [
            'env'         => $env,
            'route'       => $routeName,
            'params'      => $routeParams,
            'startTime'   => $this->trackRequestTime ? microtime(true) : null,
            'startMemory' => memory_get_usage(true),
            'queryCount'  => $this->queryCounters?->getQueryCount() ?? 0,
            'queryTime'   => $this->queryCounters?->getTotalQueryTime() ?? 0.0,
        ];

        $this->bundleLog('debug', '[PerformanceBundle] Tracking enabled: route="%s", env=%s, request_type=sub', $routeName, $env);
    }

    /**
     * Records the sub-request measurements queued during the main request.
     *
     * Each sub-request gets its own request ID so its access record never collides with the main one.
     */
    private function recordPendingSubRequests(): void
    {
        $pending = $this->pendingSubRequestMetrics;
        // @igor-ignore - Event subscriber clears request/runtime bridges; not cross-request leak.
        $this->pendingSubRequestMetrics = [];

        if ($pending === []) {
            return;
        }

        [$userIdentifier, $userId] = $this->resolveUser();

        foreach ($pending as $metrics) {
            if ($this->samplingRate < 1.0 && mt_rand(1, 10000) / 10000 > $this->samplingRate) {
                continue;
            }

            try {
                @$this->metricsService->recordMetrics(
                    $metrics['route'],
                    $metrics['env'],
                    $metrics['requestTime'],
                    $metrics['queryCount'],
                    $metrics['queryTime'],
                    $metrics['params'],
                    $metrics['memoryUsage'],
                    $metrics['method'],
                    $metrics['statusCode'],
                    $this->trackStatusCodes,
                    bin2hex(random_bytes(16)),
                    $metrics['referer'],
                    $userIdentifier,
                    $userId,
                    $metrics['routePath'],
                );
            } catch (Throwable $e) {
                $this->bundleLog(
                    'debug',
                    '[PerformanceBundle] Error saving sub-request metrics for route %s: %s',
                    $metrics['route'],
                    $e->getMessage(),
                );
            }
        }
    }

    /**
     * Resolves the current user's identifier and ID when track_user is enabled.
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function resolveUser(): array
    {
        $userIdentifier = null;
        $userId         = null;
        if ($this->trackUser && $this->security !== null && method_exists($this->security, 'getUser')) {
            $user = $this->security->getUser();
            if ($user !== null && is_object($user)) {
                $userIdentifier = method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : null;
                if (method_exists($user, 'getId')) {
                    $id     = $user->getId();
                    $userId = $id === null ? null : (string) $id;
                }
            }
        }

        return [$userIdentifier, $userId];
    }

    /**
     * Detects the current environment (kernel, then APP_ENV from request/server/env, then "dev").
     */
    private function detectEnvironment(Request $request): string
    {
        if ($this->kernel instanceof KernelInterface) {
            return $this->kernel->getEnvironment();
        }
        if ($request->server->has('APP_ENV')) {
            return (string) $request->server->get('APP_ENV');
        }
        // @igor-ignore - Event subscriber clears request/runtime bridges; not cross-request leak.
        if (isset($_SERVER['APP_ENV'])) {
            // @igor-ignore - Event subscriber clears request/runtime bridges; not cross-request leak.
            return (string) $_SERVER['APP_ENV'];
        }
        $appEnv = getenv('APP_ENV');

        return $appEnv !== false && $appEnv !== '' ? $appEnv : 'dev';
    }

    /**
     * Start tracking database queries.
     *
     * Initializes the stopwatch for the current request.
     */
    private function startQueryTracking(): void
    {
        if ($this->stopwatch instanceof Stopwatch) {
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
        if ($this->stopwatch instanceof Stopwatch && $this->stopwatch->isStarted('doctrine.queries')) {
            $this->stopwatch->stop('doctrine.queries');
        }
    }

    /**
     * Get query metrics from QueryTrackingMiddleware, Doctrine DataCollector, or fallback.
     *
     * @param Request|null $request The request object (optional, will use RequestStack if not provided)
     *
     * @return array{count: int, time: float} Array with query count and total time
     */
    private function getQueryMetrics(?Request $request = null): array
    {
        // Priority 1: metrics from QueryTrackingMiddleware (most reliable for our use case). Only when it reports 0/0
        // (it might not be wired) and there is a request, fall back to the profiler / stopwatch below.
        $queryCount = $this->queryCounters?->getQueryCount() ?? 0;
        $queryTime  = $this->queryCounters?->getTotalQueryTime() ?? 0.0;
        if ($queryCount !== 0 || $queryTime !== 0.0 || !$request instanceof Request) {
            return ['count' => $queryCount, 'time' => $queryTime];
        }

        // Priority 2: Doctrine DataCollector of the request profile (request attributes)
        try {
            // Try multiple ways to get the profiler from request
            $profilerProfile = $request->attributes->get('_profiler');

            // If not in attributes, try to get from parent request (for sub-requests)
            if ($profilerProfile === null && $this->requestStack instanceof RequestStack) {
                $parentRequest = $this->requestStack->getParentRequest();
                if ($parentRequest instanceof Request) {
                    $profilerProfile = $parentRequest->attributes->get('_profiler');
                }
            }

            // Also try _profiler_profile (alternative attribute name)
            $profilerProfile ??= $request->attributes->get('_profiler_profile');

            if ($profilerProfile !== null && is_object($profilerProfile)) {
                // Try to get DoctrineDataCollector
                $doctrineCollector = null;

                // Method 1: Direct get() call
                if (method_exists($profilerProfile, 'get')) {
                    $doctrineCollector = $profilerProfile->get('doctrine');
                }

                // Method 2: getCollector() method
                if ($doctrineCollector === null && method_exists($profilerProfile, 'getCollector')) {
                    $doctrineCollector = $profilerProfile->getCollector('doctrine');
                }

                // Method 3: getCollectors() and find 'db' or 'doctrine'
                if ($doctrineCollector === null && method_exists($profilerProfile, 'getCollectors')) {
                    $collectors        = $profilerProfile->getCollectors();
                    $doctrineCollector = $collectors['db'] ?? $collectors['doctrine'] ?? null;
                }

                if ($doctrineCollector instanceof DoctrineDataCollector) {
                    // Get query count and time from Doctrine DataCollector
                    $queryCount = $doctrineCollector->getQueryCount();
                    $queryTime  = $doctrineCollector->getTime() / 1000.0; // Convert from milliseconds to seconds

                    // If we got valid data, return it
                    if ($queryCount > 0 || $queryTime > 0) {
                        return ['count' => $queryCount, 'time' => $queryTime];
                    }
                }
            }
        } catch (Exception) {
            // Silently fail and try fallback
        }

        // Priority 3: Fallback to Stopwatch for time tracking only
        // Note: Stopwatch won't give us accurate query count
        if ($this->stopwatch instanceof Stopwatch) {
            try {
                if ($this->stopwatch->isStarted('doctrine.queries')) {
                    $event     = $this->stopwatch->getEvent('doctrine.queries');
                    $queryTime = $event->getDuration() / 1000.0;
                    // Convert from milliseconds to seconds

                }
            } catch (Exception) {
                // Silently fail
            }
        }

        return ['count' => $queryCount, 'time' => $queryTime];
    }

    /**
     * Check if the route is ignored (literal name or glob pattern).
     *
     * - Literal: exact match, or sub-routes by prefix (e.g. "_wdt" matches "_wdt", "_wdt_open").
     * - Pattern: if the entry contains * or ?, it is treated as a glob (e.g. "_wdt*", "*_profiler*").
     *
     * @param string|null $routeName The route name from the request
     *
     * @return bool True if the route should not be tracked
     */
    private function isRouteIgnored(?string $routeName): bool
    {
        if ($routeName === null || $routeName === '') {
            return false;
        }
        foreach ($this->ignoreRoutes as $ignored) {
            $ignored = (string) $ignored;
            if ($ignored === '') {
                continue;
            }
            // Pattern: entry contains glob wildcards
            if (str_contains($ignored, '*') || str_contains($ignored, '?')) {
                if (fnmatch($ignored, $routeName, FNM_NOESCAPE)) {
                    return true;
                }
                continue;
            }
            // Literal: exact match or sub-route by prefix (_wdt -> _wdt_open)
            if ($routeName === $ignored) {
                return true;
            }
            if (str_starts_with($routeName, $ignored . '_')) {
                return true;
            }
        }

        // Symfony's WebProfilerBundle uses route names web_profiler_wdt and web_profiler_profiler
        // (path /_wdt and /_profiler). If the user has _wdt or _profiler in ignore_routes,
        // treat these routes as ignored even though their names don't start with _wdt/_profiler.
        if (str_starts_with($routeName, 'web_profiler_')) {
            foreach ($this->ignoreRoutes as $ignored) {
                $ignored = (string) $ignored;
                if ($ignored === '') {
                    continue;
                }
                $ig = strtolower($ignored);
                // (a glob entry matching the route already returned true in the loop above)
                if ($ig === '_wdt' || str_starts_with($ig, '_wdt') || $ig === '_profiler' || str_starts_with($ig, '_profiler') || str_starts_with($ig, 'web_profiler')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Logs through the injected PSR-3 logger when bundle logging is enabled (REQ-OBS-001 / REQ-DI-001).
     *
     * @param string $level PSR-3 level
     * @param string $format sprintf() format (used verbatim when no arguments are given)
     * @param mixed ...$args sprintf() arguments
     */
    private function bundleLog(string $level, string $format, mixed ...$args): void
    {
        if (!$this->enableLogging) {
            return;
        }

        $this->logger?->log($level, $args === [] ? $format : sprintf($format, ...$args), ['bundle' => 'performance']);
    }
}
