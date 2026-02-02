<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Entity\RouteDataRecord;
use Nowo\PerformanceBundle\Event\AfterMetricsRecordedEvent;
use Nowo\PerformanceBundle\Event\BeforeMetricsRecordedEvent;
use Nowo\PerformanceBundle\Helper\LogHelper;
use Nowo\PerformanceBundle\Message\RecordMetricsMessage;
use Nowo\PerformanceBundle\Model\RouteDataWithAggregates;
use Nowo\PerformanceBundle\Repository\RouteDataRecordRepository;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Service for managing route performance metrics.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class PerformanceMetricsService
{
    /**
     * The Doctrine registry.
     */
    private readonly ManagerRegistry $registry;

    /**
     * The connection name.
     */
    private readonly string $connectionName;

    /**
     * The entity manager for the configured connection.
     */
    private EntityManagerInterface $entityManager;

    /**
     * The repository for RouteData entities.
     */
    private RouteDataRepository $repository;

    /**
     * The repository for RouteDataRecord entities.
     */
    private RouteDataRecordRepository $recordRepository;

    /**
     * Whether access records are enabled.
     */
    private readonly bool $enableAccessRecords;

    /**
     * Cache service for performance metrics (optional).
     */
    private ?PerformanceCacheService $cacheService = null;

    /**
     * Event dispatcher (optional).
     */
    private ?EventDispatcherInterface $eventDispatcher = null;

    /**
     * Message bus for async processing (optional).
     *
     * @var object|null MessageBusInterface from Symfony Messenger (if available)
     */
    private ?object $messageBus = null;

    /**
     * Whether to use async mode.
     */
    private readonly bool $async;

    /**
     * Whether logging is enabled.
     */
    private readonly bool $enableLogging;

    /**
     * Creates a new instance.
     *
     * @param ManagerRegistry $registry       The Doctrine registry
     * @param string          $connectionName The name of the Doctrine connection to use
     * @param bool            $async          Whether to use async mode
     */
    public function __construct(
        ManagerRegistry $registry,
        #[Autowire('%nowo_performance.connection%')]
        string $connectionName,
        #[Autowire('%nowo_performance.async%')]
        bool $async = false,
        #[Autowire('%nowo_performance.enable_access_records%')]
        bool $enableAccessRecords = false,
        #[Autowire('%nowo_performance.enable_logging%')]
        bool $enableLogging = true,
    ) {
        $this->registry = $registry;
        $this->connectionName = $connectionName;
        $this->entityManager = $registry->getManager($connectionName);
        $this->repository = $this->entityManager->getRepository(RouteData::class);
        $this->recordRepository = $this->entityManager->getRepository(RouteDataRecord::class);
        $this->async = $async;
        $this->enableAccessRecords = $enableAccessRecords;
        $this->enableLogging = $enableLogging;
    }

    /**
     * Set the cache service (optional, for cache invalidation).
     *
     * @param PerformanceCacheService|null $cacheService The cache service
     */
    #[Required]
    public function setCacheService(?PerformanceCacheService $cacheService): void
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Set the event dispatcher (optional, for event dispatching).
     *
     * @param EventDispatcherInterface|null $eventDispatcher The event dispatcher
     */
    #[Required]
    public function setEventDispatcher(?EventDispatcherInterface $eventDispatcher): void
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Set the message bus (optional, for async processing).
     *
     * This method is called automatically by Symfony's dependency injection
     * only if Messenger is installed. If Messenger is not available, this
     * method will not be called and $messageBus will remain null.
     *
     * @param object|null $messageBus The message bus (MessageBusInterface from Symfony Messenger)
     */
    public function setMessageBus(?object $messageBus): void
    {
        $this->messageBus = $messageBus;
    }

    /**
     * Record or update route performance metrics.
     *
     * @param string      $routeName        The route name
     * @param string      $env              The environment (dev, test, prod)
     * @param float|null  $requestTime      Request execution time in seconds
     * @param int|null    $totalQueries     Total number of database queries
     * @param float|null  $queryTime        Total query execution time in seconds
     * @param array|null  $params           Route parameters
     * @param int|null    $memoryUsage      Peak memory usage in bytes
     * @param string|null $httpMethod       HTTP method (GET, POST, PUT, DELETE, etc.)
     * @param int|null    $statusCode       HTTP status code (200, 404, 500, etc.)
     * @param array<int>  $trackStatusCodes List of status codes to track
     * @param string|null $requestId        Unique request ID to avoid duplicate access records for the same request
     * @param string|null $referer          HTTP Referer header (page that linked to this request)
     * @param string|null $userIdentifier   Logged-in user identifier (e.g. username, email)
     * @param string|null $userId           Logged-in user ID (stringified, if available)
     * @param string|null $routePath        Request path including query string (e.g. /user/123?page=2) for access records
     *
     * @return array{is_new: bool, was_updated: bool} Information about the operation
     */
    public function recordMetrics(
        string $routeName,
        string $env,
        ?float $requestTime = null,
        ?int $totalQueries = null,
        ?float $queryTime = null,
        ?array $params = null,
        ?int $memoryUsage = null,
        ?string $httpMethod = null,
        ?int $statusCode = null,
        array $trackStatusCodes = [],
        ?string $requestId = null,
        ?string $referer = null,
        ?string $userIdentifier = null,
        ?string $userId = null,
        ?string $routePath = null,
    ): array {
        LogHelper::logf(
            '[PerformanceBundle] recordMetrics: START - route=%s, env=%s, async=%s, requestTime=%s, totalQueries=%s',
            $this->enableLogging,
            $routeName,
            $env,
            $this->async ? 'true' : 'false',
            null !== $requestTime ? (string) $requestTime : 'null',
            null !== $totalQueries ? (string) $totalQueries : 'null'
        );

        // Dispatch before event to allow modification of metrics
        if (null !== $this->eventDispatcher) {
            $beforeEvent = new BeforeMetricsRecordedEvent(
                $routeName,
                $env,
                $requestTime,
                $totalQueries,
                $queryTime,
                $params,
                $memoryUsage,
                $httpMethod
            );
            $this->eventDispatcher->dispatch($beforeEvent);

            // Use modified values from event
            $requestTime = $beforeEvent->getRequestTime();
            $totalQueries = $beforeEvent->getTotalQueries();
            $queryTime = $beforeEvent->getQueryTime();
            $params = $beforeEvent->getParams();
            $memoryUsage = $beforeEvent->getMemoryUsage();
            // Note: httpMethod is not modifiable via event, use original value
        }

        // If async mode is enabled and message bus is available, dispatch message
        if ($this->async && null !== $this->messageBus) {
            LogHelper::log('[PerformanceBundle] recordMetrics: Dispatching async message', $this->enableLogging);
            $message = new RecordMetricsMessage(
                $routeName,
                $env,
                $requestTime,
                $totalQueries,
                $queryTime,
                $params,
                $memoryUsage,
                $httpMethod,
                $requestId,
                $referer,
                $userIdentifier,
                $userId,
                $routePath
            );
            $this->messageBus->dispatch($message);

            // In async mode, we can't know if it was new or updated until the message is processed
            return ['is_new' => false, 'was_updated' => false];
        }

        // Otherwise, record synchronously
        LogHelper::log('[PerformanceBundle] recordMetrics: Recording synchronously', $this->enableLogging);

        return $this->recordMetricsSync($routeName, $env, $requestTime, $totalQueries, $queryTime, $params, $memoryUsage, $httpMethod, $statusCode, $trackStatusCodes, $requestId, $referer, $userIdentifier, $userId, $routePath);
    }

    /**
     * Record metrics synchronously (internal method).
     *
     * @param string      $routeName        The route name
     * @param string      $env              The environment
     * @param float|null  $requestTime      Request execution time in seconds
     * @param int|null    $totalQueries     Total number of database queries
     * @param float|null  $queryTime        Total query execution time in seconds
     * @param array|null  $params           Route parameters
     * @param int|null    $memoryUsage      Peak memory usage in bytes
     * @param string|null $httpMethod       HTTP method (GET, POST, PUT, DELETE, etc.)
     * @param int|null    $statusCode       HTTP status code (200, 404, 500, etc.)
     * @param array<int>  $trackStatusCodes List of status codes to track
     * @param string|null $requestId        Unique request ID to avoid duplicate access records for the same request
     * @param string|null $referer          HTTP Referer header
     * @param string|null $userIdentifier   Logged-in user identifier (e.g. username, email)
     * @param string|null $userId           Logged-in user ID (stringified, if available)
     * @param string|null $routePath        Request path including query string (e.g. /user/123?page=2) for access records
     *
     * @return array{is_new: bool, was_updated: bool} Information about the operation
     */
    private function recordMetricsSync(
        string $routeName,
        string $env,
        ?float $requestTime = null,
        ?int $totalQueries = null,
        ?float $queryTime = null,
        ?array $params = null,
        ?int $memoryUsage = null,
        ?string $httpMethod = null,
        ?int $statusCode = null,
        array $trackStatusCodes = [],
        ?string $requestId = null,
        ?string $referer = null,
        ?string $userIdentifier = null,
        ?string $userId = null,
        ?string $routePath = null,
    ): array {
        $isNew = false;
        $wasUpdated = false;

        LogHelper::logf(
            '[PerformanceBundle] recordMetricsSync: Looking for existing record - route=%s, env=%s',
            $this->enableLogging,
            $routeName,
            $env
        );

        $routeData = $this->repository->findByRouteAndEnv($routeName, $env);

        if (null === $routeData) {
            LogHelper::logf(
                '[PerformanceBundle] recordMetricsSync: No existing record found, creating new - route=%s, env=%s',
                $this->enableLogging,
                $routeName,
                $env
            );
            // Create new RouteData (identity + lastAccessedAt only; metrics live in RouteDataRecord)
            $routeData = new RouteData();
            $routeData->setName($routeName);
            $routeData->setEnv($env);
            $routeData->setHttpMethod($httpMethod);
            $routeData->setParams($params);
            $routeData->setLastAccessedAt(new \DateTimeImmutable());

            $this->entityManager->persist($routeData);
            $isNew = true;
        } else {
            LogHelper::logf(
                '[PerformanceBundle] recordMetricsSync: Existing record found - route=%s, env=%s',
                $this->enableLogging,
                $routeName,
                $env
            );
            $routeData->setLastAccessedAt(new \DateTimeImmutable());
            $wasUpdated = true;
        }

        try {
            LogHelper::logf(
                '[PerformanceBundle] Before flush: route=%s, env=%s, isNew=%s, entityManagerOpen=%s',
                $this->enableLogging,
                $routeName,
                $env,
                $isNew ? 'true' : 'false',
                $this->entityManager->isOpen() ? 'true' : 'false'
            );

            // Ensure EntityManager is open before flush
            if (!$this->entityManager->isOpen()) {
                $this->resetEntityManager();
            }

            // Suppress any potential output from Doctrine
            $errorReporting = error_reporting(0);
            $this->entityManager->flush();
            error_reporting($errorReporting);

            LogHelper::logf(
                '[PerformanceBundle] After flush SUCCESS: route=%s, env=%s, isNew=%s',
                $this->enableLogging,
                $routeName,
                $env,
                $isNew ? 'true' : 'false'
            );

            // Invalidate cache for this environment after update
            if (null !== $this->cacheService) {
                $this->cacheService->invalidateStatistics($env);
            }

            // Create access record if enabled (one per request when requestId is set)
            if ($this->enableAccessRecords) {
                $skipAccessRecord = false;
                // Skip if this route has "save access records" disabled
                if (!$routeData->getSaveAccessRecords()) {
                    $skipAccessRecord = true;
                }
                if (!$skipAccessRecord && null !== $requestId && '' !== $requestId) {
                    $existing = $this->recordRepository->findOneByRequestId($requestId);
                    if (null !== $existing) {
                        $skipAccessRecord = true;
                    }
                }

                if (!$skipAccessRecord) {
                    $accessRecord = new RouteDataRecord();
                    $accessRecord->setRouteData($routeData);
                    $accessRecord->setAccessedAt(new \DateTimeImmutable());
                    $accessRecord->setStatusCode($statusCode);

                    // responseTime in RouteDataRecord is the per-request duration,
                    // while RouteData::requestTime is an aggregate/representative value.
                    // We store the raw per-request time here for temporal analysis.
                    $accessRecord->setResponseTime($requestTime);
                    $accessRecord->setTotalQueries($totalQueries);
                    $accessRecord->setQueryTime($queryTime);
                    $accessRecord->setMemoryUsage($memoryUsage);
                    if (null !== $requestId && '' !== $requestId) {
                        $accessRecord->setRequestId($requestId);
                    }
                    if (null !== $referer && '' !== $referer) {
                        $accessRecord->setReferer($referer);
                    }
                    if (null !== $userIdentifier && '' !== $userIdentifier) {
                        $accessRecord->setUserIdentifier($userIdentifier);
                    }
                    if (null !== $userId && '' !== $userId) {
                        $accessRecord->setUserId($userId);
                    }
                    if (null !== $params && [] !== $params) {
                        $accessRecord->setRouteParams($params);
                    }
                    if (null !== $routePath && '' !== $routePath) {
                        $accessRecord->setRoutePath($routePath);
                    }

                    $this->entityManager->persist($accessRecord);

                    $err = error_reporting(0);
                    $this->entityManager->flush();
                    error_reporting($err);
                }
            }

            if (null !== $this->eventDispatcher) {
                $afterEvent = new AfterMetricsRecordedEvent($routeData, $isNew, $requestTime, $totalQueries, $memoryUsage);
                $this->eventDispatcher->dispatch($afterEvent);
            }

            LogHelper::logf(
                '[PerformanceBundle] recordMetricsSync: SUCCESS - route=%s, env=%s, isNew=%s, wasUpdated=%s',
                $this->enableLogging,
                $routeName,
                $env,
                $isNew ? 'true' : 'false',
                $wasUpdated ? 'true' : 'false'
            );

            return ['is_new' => $isNew, 'was_updated' => $wasUpdated];
        } catch (\Exception $e) {
            // Restore error reporting
            if (isset($errorReporting)) {
                error_reporting($errorReporting);
            }

            LogHelper::logf(
                '[PerformanceBundle] recordMetricsSync: ERROR - route=%s, env=%s, exception=%s, message=%s',
                $this->enableLogging,
                $routeName,
                $env,
                $e::class,
                $e->getMessage()
            );

            // Always reset EntityManager after an error (it may be closed)
            // This ensures subsequent operations can proceed
            $this->resetEntityManager();

            // Log the error for debugging
            LogHelper::logf(
                '[PerformanceBundle] Error in flush: route=%s, env=%s, error=%s, entityManagerOpen=%s',
                $this->enableLogging,
                $routeName,
                $env,
                $e->getMessage(),
                $this->entityManager->isOpen() ? 'true' : 'false'
            );

            // Re-throw to let the subscriber handle it
            throw $e;
        }

        // This should never be reached, but return default values if it does
        return ['is_new' => false, 'was_updated' => false];
    }

    /**
     * Get route data by name and environment.
     *
     * @param string $routeName The route name
     * @param string $env       The environment (dev, test, prod)
     *
     * @return RouteData|null The route data or null if not found
     */
    public function getRouteData(string $routeName, string $env): ?RouteData
    {
        return $this->repository->findByRouteAndEnv($routeName, $env);
    }

    /**
     * Get all routes for an environment.
     *
     * @return RouteData[]
     */
    public function getRoutesByEnvironment(string $env): array
    {
        return $this->repository->findByEnvironment($env);
    }

    /**
     * Get routes for an environment with aggregates from RouteDataRecord (for dashboard/listings).
     *
     * Returns RouteDataWithAggregates[] sorted by request_time descending (worst first).
     *
     * @return RouteDataWithAggregates[]
     */
    public function getRoutesWithAggregates(string $env): array
    {
        $routes = $this->repository->findByEnvironment($env);
        if (empty($routes)) {
            return [];
        }
        $ids = array_map(static fn (RouteData $r) => $r->getId(), array_filter($routes, static fn (RouteData $r) => null !== $r->getId()));
        if (empty($ids)) {
            return [];
        }
        $aggregates = $this->recordRepository->getAggregatesForRouteDataIds($ids);
        $withAggregates = [];
        foreach ($routes as $route) {
            $id = $route->getId();
            if (null === $id) {
                continue;
            }
            $agg = $aggregates[$id] ?? [
                'request_time' => null,
                'total_queries' => null,
                'query_time' => null,
                'memory_usage' => null,
                'access_count' => 0,
                'status_codes' => [],
            ];
            $withAggregates[] = new RouteDataWithAggregates($route, $agg);
        }
        usort($withAggregates, static function (RouteDataWithAggregates $a, RouteDataWithAggregates $b): int {
            $at = $a->getRequestTime() ?? 0.0;
            $bt = $b->getRequestTime() ?? 0.0;

            return $bt <=> $at;
        });

        return $withAggregates;
    }

    /**
     * Get routes with aggregates and optional filters/sort (for dashboard, export).
     *
     * Metric sort (requestTime, totalQueries, etc.) is applied in PHP from aggregates.
     *
     * @param array<string, mixed> $filters Filters (route_names, route_name_pattern, date_from, date_to)
     *
     * @return RouteDataWithAggregates[]
     */
    public function getRoutesWithAggregatesFiltered(
        string $env,
        array $filters = [],
        string $sortBy = 'requestTime',
        string $order = 'DESC',
        ?int $limit = null,
    ): array {
        $entitySortFields = ['name', 'createdAt', 'lastAccessedAt'];
        $sortByEntity = \in_array($sortBy, $entitySortFields, true) ? $sortBy : 'lastAccessedAt';
        $routes = $this->repository->findWithFilters($env, $filters, $sortByEntity, $order, $limit);
        if (empty($routes)) {
            return [];
        }
        $ids = array_map(static fn (RouteData $r) => $r->getId(), array_filter($routes, static fn (RouteData $r) => null !== $r->getId()));
        if (empty($ids)) {
            return [];
        }
        $aggregates = $this->recordRepository->getAggregatesForRouteDataIds($ids);
        $withAggregates = [];
        foreach ($routes as $route) {
            $id = $route->getId();
            if (null === $id) {
                continue;
            }
            $agg = $aggregates[$id] ?? [
                'request_time' => null,
                'total_queries' => null,
                'query_time' => null,
                'memory_usage' => null,
                'access_count' => 0,
                'status_codes' => [],
            ];
            $withAggregates[] = new RouteDataWithAggregates($route, $agg);
        }
        if (!\in_array($sortBy, $entitySortFields, true)) {
            $desc = 'DESC' === strtoupper($order);
            usort($withAggregates, static function (RouteDataWithAggregates $a, RouteDataWithAggregates $b) use ($sortBy, $desc): int {
                $av = match ($sortBy) {
                    'requestTime' => $a->getRequestTime() ?? 0.0,
                    'totalQueries' => $a->getTotalQueries() ?? 0,
                    'queryTime' => $a->getQueryTime() ?? 0.0,
                    'accessCount' => $a->getAccessCount(),
                    'memoryUsage' => $a->getMemoryUsage() ?? 0,
                    default => 0,
                };
                $bv = match ($sortBy) {
                    'requestTime' => $b->getRequestTime() ?? 0.0,
                    'totalQueries' => $b->getTotalQueries() ?? 0,
                    'queryTime' => $b->getQueryTime() ?? 0.0,
                    'accessCount' => $b->getAccessCount(),
                    'memoryUsage' => $b->getMemoryUsage() ?? 0,
                    default => 0,
                };

                return $desc ? (int) ($bv <=> $av) : (int) ($av <=> $bv);
            });
        }

        return $withAggregates;
    }

    /**
     * Get worst performing routes for an environment.
     *
     * Returns routes ordered by request time descending (worst first).
     *
     * @param string $env   The environment (dev, test, prod)
     * @param int    $limit Maximum number of results to return (default: 10)
     *
     * @return RouteData[] Array of route data entities
     */
    public function getWorstPerformingRoutes(string $env, int $limit = 10): array
    {
        return $this->repository->findWorstPerforming($env, $limit);
    }

    /**
     * Get the repository instance.
     *
     * @return RouteDataRepository The repository
     */
    public function getRepository(): RouteDataRepository
    {
        return $this->repository;
    }

    /**
     * Reset the EntityManager if it's closed.
     *
     * This method should be called after an exception to ensure
     * the EntityManager is in a valid state for subsequent operations.
     */
    private function resetEntityManager(): void
    {
        if (!$this->entityManager->isOpen()) {
            $this->registry->resetManager($this->connectionName);
            $this->entityManager = $this->registry->getManager($this->connectionName);
            $this->repository = $this->entityManager->getRepository(RouteData::class);
            $this->recordRepository = $this->entityManager->getRepository(RouteDataRecord::class);

            LogHelper::log('[PerformanceBundle] EntityManager reset after being closed', $this->enableLogging);
        }
    }
}
