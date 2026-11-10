<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Service;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Entity\RouteDataRecord;
use Nowo\PerformanceBundle\Event\AfterMetricsRecordedEvent;
use Nowo\PerformanceBundle\Event\BeforeMetricsRecordedEvent;
use Nowo\PerformanceBundle\Message\RecordMetricsMessage;
use Nowo\PerformanceBundle\MessageBus\MessageBusInterface;
use Nowo\PerformanceBundle\Model\RouteDataWithAggregates;
use Nowo\PerformanceBundle\Repository\RouteDataRecordRepository;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Throwable;

use function in_array;
use function sprintf;

/**
 * Service for managing route performance metrics.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class PerformanceMetricsService
{
    /**
     * The entity manager for the configured connection.
     */
    private readonly EntityManagerInterface $entityManager;

    /**
     * The repository for RouteData entities.
     */
    private readonly RouteDataRepository $repository;

    /**
     * The repository for RouteDataRecord entities.
     */
    private readonly RouteDataRecordRepository $recordRepository;

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
     */
    private ?MessageBusInterface $messageBus = null;

    /**
     * Private entity manager of the write path (created on first write, dropped after an error).
     */
    private ?EntityManagerInterface $writeEntityManager = null;

    /**
     * Creates a new instance.
     *
     * @param ManagerRegistry $registry The Doctrine registry
     * @param string $connectionName The name of the Doctrine connection to use
     * @param bool $async Whether to use async mode
     * @param MetricsEntityManagerFactory|null $writeEntityManagerFactory Creates the private write entity manager
     */
    public function __construct(
        private readonly ManagerRegistry $registry,
        #[Autowire('%nowo_performance.connection%')]
        private readonly string $connectionName,
        #[Autowire('%nowo_performance.async%')]
        private readonly bool $async = false,
        /**
         * Whether access records are enabled.
         */
        #[Autowire('%nowo_performance.enable_access_records%')]
        private readonly bool $enableAccessRecords = false,
        /**
         * Whether logging is enabled.
         */
        #[Autowire('%nowo_performance.enable_logging%')]
        private readonly bool $enableLogging = true,
        private readonly ?LoggerInterface $logger = null,
        private readonly ClockInterface $clock = new NativeClock(),
        private readonly ?MetricsEntityManagerFactory $writeEntityManagerFactory = null,
    ) {
        $manager = $this->registry->getManager($this->connectionName);
        if (!$manager instanceof EntityManagerInterface) {
            throw new LogicException('Expected EntityManagerInterface from registry.');
        }
        $this->entityManager = $manager;
        $repository          = $this->entityManager->getRepository(RouteData::class);
        if (!$repository instanceof RouteDataRepository) {
            throw new LogicException('Expected RouteDataRepository.');
        }
        $this->repository = $repository;
        $recordRepo       = $this->entityManager->getRepository(RouteDataRecord::class);
        if (!$recordRepo instanceof RouteDataRecordRepository) {
            throw new LogicException('Expected RouteDataRecordRepository.');
        }
        $this->recordRepository = $recordRepo;
    }

    /**
     * Get the DBAL connection used by the metrics service.
     */
    public function getConnection(): Connection
    {
        return $this->entityManager->getConnection();
    }

    /**
     * Get the entity manager of the configured connection (reads, dashboard actions).
     *
     * Metrics recorded by recordMetrics() / recordMetricsSync() are written through a private entity manager on the
     * same connection instead, so they never flush this manager's pending changes.
     */
    public function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    /**
     * Set the cache service (optional, for cache invalidation).
     *
     * @param PerformanceCacheService|null $cacheService The cache service
     */
    #[Required]
    public function setCacheService(?PerformanceCacheService $cacheService): void
    {
        // @igor-ignore - Service coordinates I/O or request-scoped work; not unsafe worker singleton state.
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
        // @igor-ignore - Service coordinates I/O or request-scoped work; not unsafe worker singleton state.
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Set the message bus (optional, for async processing).
     *
     * This method is called automatically by Symfony's dependency injection
     * only if Messenger is installed. If Messenger is not available, this
     * method will not be called and $messageBus will remain null.
     *
     * @param MessageBusInterface|null $messageBus The message bus (e.g. Symfony Messenger)
     */
    public function setMessageBus(?MessageBusInterface $messageBus): void
    {
        // @igor-ignore - Service coordinates I/O or request-scoped work; not unsafe worker singleton state.
        $this->messageBus = $messageBus;
    }

    /**
     * Record or update route performance metrics.
     *
     * @param string $routeName The route name
     * @param string $env The environment (dev, test, prod)
     * @param float|null $requestTime Request execution time in seconds
     * @param int|null $totalQueries Total number of database queries
     * @param float|null $queryTime Total query execution time in seconds
     * @param array<string, mixed>|null $params Route parameters
     * @param int|null $memoryUsage Peak memory usage in bytes
     * @param string|null $httpMethod HTTP method (GET, POST, PUT, DELETE, etc.)
     * @param int|null $statusCode HTTP status code (200, 404, 500, etc.)
     * @param array<int> $trackStatusCodes List of status codes to track
     * @param string|null $requestId Unique request ID to avoid duplicate access records for the same request
     * @param string|null $referer HTTP Referer header (page that linked to this request)
     * @param string|null $userIdentifier Logged-in user identifier (e.g. username, email)
     * @param string|null $userId Logged-in user ID (stringified, if available)
     * @param string|null $routePath Request path including query string (e.g. /user/123?page=2) for access records
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
        $this->bundleLog(
            'debug',
            '[PerformanceBundle] recordMetrics: START - route=%s, env=%s, async=%s, requestTime=%s, totalQueries=%s',
            $routeName,
            $env,
            $this->async ? 'true' : 'false',
            $requestTime !== null ? (string) $requestTime : 'null',
            $totalQueries !== null ? (string) $totalQueries : 'null',
        );

        // Dispatch before event to allow modification of metrics
        if ($this->eventDispatcher instanceof EventDispatcherInterface) {
            $beforeEvent = new BeforeMetricsRecordedEvent(
                $routeName,
                $env,
                $requestTime,
                $totalQueries,
                $queryTime,
                $params,
                $memoryUsage,
            );
            $this->eventDispatcher->dispatch($beforeEvent);

            // Use modified values from event
            $requestTime  = $beforeEvent->getRequestTime();
            $totalQueries = $beforeEvent->getTotalQueries();
            $queryTime    = $beforeEvent->getQueryTime();
            $params       = $beforeEvent->getParams();
            $memoryUsage  = $beforeEvent->getMemoryUsage();
            // Note: httpMethod is not modifiable via event, use original value
        }

        // If async mode is enabled and message bus is available, dispatch message
        $messageBus = $this->messageBus;
        if ($this->async && $messageBus instanceof MessageBusInterface) {
            $this->bundleLog('debug', '[PerformanceBundle] recordMetrics: Dispatching async message');
            $message = new RecordMetricsMessage(
                $routeName,
                $env,
                $requestTime,
                $totalQueries,
                $queryTime,
                $params,
                $memoryUsage,
                $httpMethod,
                $statusCode,
                $requestId,
                $referer,
                $userIdentifier,
                $userId,
                $routePath,
            );
            $messageBus->dispatch($message);

            // In async mode, we can't know if it was new or updated until the message is processed
            return ['is_new' => false, 'was_updated' => false];
        }

        // Otherwise, record synchronously
        $this->bundleLog('debug', '[PerformanceBundle] recordMetrics: Recording synchronously');

        return $this->recordMetricsSync($routeName, $env, $requestTime, $totalQueries, $queryTime, $params, $memoryUsage, $httpMethod, $statusCode, $requestId, $referer, $userIdentifier, $userId, $routePath);
    }

    /**
     * Record metrics synchronously (used by sync mode and async message handler).
     *
     * @param string $routeName The route name
     * @param string $env The environment
     * @param float|null $requestTime Request execution time in seconds
     * @param int|null $totalQueries Total number of database queries
     * @param float|null $queryTime Total query execution time in seconds
     * @param array<string, mixed>|null $params Route parameters
     * @param int|null $memoryUsage Peak memory usage in bytes
     * @param string|null $httpMethod HTTP method (GET, POST, PUT, DELETE, etc.)
     * @param int|null $statusCode HTTP status code (200, 404, 500, etc.)
     * @param string|null $requestId Unique request ID to avoid duplicate access records for the same request
     * @param string|null $referer HTTP Referer header
     * @param string|null $userIdentifier Logged-in user identifier (e.g. username, email)
     * @param string|null $userId Logged-in user ID (stringified, if available)
     * @param string|null $routePath Request path including query string (e.g. /user/123?page=2) for access records
     *
     * @return array{is_new: bool, was_updated: bool} Information about the operation
     */
    public function recordMetricsSync(
        string $routeName,
        string $env,
        ?float $requestTime = null,
        ?int $totalQueries = null,
        ?float $queryTime = null,
        ?array $params = null,
        ?int $memoryUsage = null,
        ?string $httpMethod = null,
        ?int $statusCode = null,
        ?string $requestId = null,
        ?string $referer = null,
        ?string $userIdentifier = null,
        ?string $userId = null,
        ?string $routePath = null,
    ): array {
        $isNew        = false;
        $wasUpdated   = false;
        $accessRecord = null;

        $this->bundleLog(
            'debug',
            '[PerformanceBundle] recordMetricsSync: Looking for existing record - route=%s, env=%s',
            $routeName,
            $env,
        );

        // A private entity manager on the configured connection: its unit of work only holds the bundle's entities,
        // so the flush below never commits a change the host left pending, and a closed host manager is irrelevant.
        $entityManager = $this->writeEntityManager();
        $routeData     = $this->findRouteDataForWrite($entityManager, $routeName, $env);

        if (!$routeData instanceof RouteData) {
            $this->bundleLog(
                'debug',
                '[PerformanceBundle] recordMetricsSync: No existing record found, creating new - route=%s, env=%s',
                $routeName,
                $env,
            );
            // Create new RouteData (identity + lastAccessedAt only; metrics live in RouteDataRecord)
            $routeData = new RouteData();
            $routeData->setName($routeName);
            $routeData->setEnv($env);
            $routeData->setHttpMethod($httpMethod);
            $routeData->setParams($params !== null ? SensitiveDataRedactor::redactParams($params) : null);
            $routeData->setLastAccessedAt($this->clock->now());

            $entityManager->persist($routeData);
            $isNew = true;
        } else {
            $this->bundleLog(
                'debug',
                '[PerformanceBundle] recordMetricsSync: Existing record found - route=%s, env=%s',
                $routeName,
                $env,
            );
            $routeData->setLastAccessedAt($this->clock->now());
            $wasUpdated = true;
        }

        try {
            $this->bundleLog(
                'debug',
                '[PerformanceBundle] Before flush: route=%s, env=%s, isNew=%s, entityManagerOpen=%s',
                $routeName,
                $env,
                $isNew ? 'true' : 'false',
                $entityManager->isOpen() ? 'true' : 'false',
            );

            // Create access record if enabled (one per request when requestId is set)
            if ($this->enableAccessRecords) {
                $skipAccessRecord = false;
                // Skip if this route has "save access records" disabled
                if (!$routeData->getSaveAccessRecords()) {
                    $skipAccessRecord = true;
                }
                if (!$skipAccessRecord && $requestId !== null && $requestId !== '') {
                    if ($this->accessRecordExistsForWrite($entityManager, $requestId)) {
                        $skipAccessRecord = true;
                    }
                }

                if (!$skipAccessRecord) {
                    $accessRecord = new RouteDataRecord();
                    $accessRecord->setRouteData($routeData);
                    $accessRecord->setAccessedAt($this->clock->now());
                    $accessRecord->setStatusCode($statusCode);

                    // responseTime in RouteDataRecord is the per-request duration,
                    // while RouteData::requestTime is an aggregate/representative value.
                    // We store the raw per-request time here for temporal analysis.
                    $accessRecord->setResponseTime($requestTime);
                    $accessRecord->setTotalQueries($totalQueries);
                    $accessRecord->setQueryTime($queryTime);
                    $accessRecord->setMemoryUsage($memoryUsage);
                    if ($requestId !== null && $requestId !== '') {
                        $accessRecord->setRequestId($requestId);
                    }
                    if ($referer !== null && $referer !== '') {
                        $accessRecord->setReferer(SensitiveDataRedactor::redactUrl($referer));
                    }
                    if ($userIdentifier !== null && $userIdentifier !== '') {
                        $accessRecord->setUserIdentifier($userIdentifier);
                    }
                    if ($userId !== null && $userId !== '') {
                        $accessRecord->setUserId($userId);
                    }
                    if ($params !== null && $params !== []) {
                        $accessRecord->setRouteParams(SensitiveDataRedactor::redactParams($params));
                    }
                    if ($routePath !== null && $routePath !== '') {
                        $accessRecord->setRoutePath(SensitiveDataRedactor::redactUrl($routePath));
                    }

                    $entityManager->persist($accessRecord);
                }
            }

            // Suppress any potential output from Doctrine ("@" is restored by the engine even on exceptions)
            @$entityManager->flush();

            $this->bundleLog(
                'debug',
                '[PerformanceBundle] After flush SUCCESS: route=%s, env=%s, isNew=%s',
                $routeName,
                $env,
                $isNew ? 'true' : 'false',
            );

            // Invalidate cache for this environment after update
            if ($this->cacheService instanceof PerformanceCacheService) {
                $this->cacheService->invalidateStatistics($env);
            }

            if ($this->eventDispatcher instanceof EventDispatcherInterface) {
                $afterEvent = new AfterMetricsRecordedEvent($routeData, $isNew, $requestTime, $totalQueries, $memoryUsage);
                $this->eventDispatcher->dispatch($afterEvent);
            }

            // The private manager only holds the bundle's entities: clearing it keeps a long-running worker from
            // accumulating one record per request or serving a stale RouteData changed by another worker.
            $entityManager->clear();

            $this->bundleLog(
                'debug',
                '[PerformanceBundle] recordMetricsSync: SUCCESS - route=%s, env=%s, isNew=%s, wasUpdated=%s',
                $routeName,
                $env,
                $isNew ? 'true' : 'false',
                $wasUpdated ? 'true' : 'false',
            );

            return ['is_new' => $isNew, 'was_updated' => $wasUpdated];
        } catch (Throwable $e) {
            $this->bundleLog(
                'debug',
                '[PerformanceBundle] recordMetricsSync: ERROR - route=%s, env=%s, exception=%s, message=%s',
                $routeName,
                $env,
                $e::class,
                $e->getMessage(),
            );

            // Drop the private manager after an error (it may be closed or hold half-written entities); the next
            // recording creates a fresh one. The host's entity manager is never reset by the bundle.
            // @igor-ignore - Private unit of work of the write path, cleared after every recording and dropped on error.
            $this->writeEntityManager = null;

            // Log the error for debugging
            $this->bundleLog(
                'debug',
                '[PerformanceBundle] Error in flush: route=%s, env=%s, error=%s',
                $routeName,
                $env,
                $e->getMessage(),
            );

            // Re-throw to let the subscriber handle it
            throw $e;
        }
    }

    /**
     * Get route data by name and environment.
     *
     * @param string $routeName The route name
     * @param string $env The environment (dev, test, prod)
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
        if ($routes === []) {
            return [];
        }
        $ids = array_values(array_filter(
            array_map(static fn (RouteData $r): ?int => $r->getId(), $routes),
            static fn (?int $id): bool => $id !== null,
        ));
        if ($ids === []) {
            return [];
        }
        $aggregates     = $this->recordRepository->getAggregatesForRouteDataIds($ids);
        $withAggregates = [];
        foreach ($routes as $route) {
            $id = $route->getId();
            if ($id === null) {
                continue;
            }
            $agg = $aggregates[$id] ?? [
                'request_time'  => null,
                'total_queries' => null,
                'query_time'    => null,
                'memory_usage'  => null,
                'access_count'  => 0,
                'status_codes'  => [],
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
        $sortByEntity     = in_array($sortBy, $entitySortFields, true) ? $sortBy : 'lastAccessedAt';
        $routes           = $this->repository->findWithFilters($env, $filters, $sortByEntity, $order, $limit);
        if ($routes === []) {
            return [];
        }
        $ids = array_values(array_filter(
            array_map(static fn (RouteData $r): ?int => $r->getId(), $routes),
            static fn (?int $id): bool => $id !== null,
        ));
        if ($ids === []) {
            return [];
        }
        $aggregates     = $this->recordRepository->getAggregatesForRouteDataIds($ids);
        $withAggregates = [];
        foreach ($routes as $route) {
            $id = $route->getId();
            if ($id === null) {
                continue;
            }
            $agg = $aggregates[$id] ?? [
                'request_time'  => null,
                'total_queries' => null,
                'query_time'    => null,
                'memory_usage'  => null,
                'access_count'  => 0,
                'status_codes'  => [],
            ];
            $withAggregates[] = new RouteDataWithAggregates($route, $agg);
        }
        if (!in_array($sortBy, $entitySortFields, true)) {
            $desc = strtoupper($order) === 'DESC';
            usort($withAggregates, static function (RouteDataWithAggregates $a, RouteDataWithAggregates $b) use ($sortBy, $desc): int {
                $av = match ($sortBy) {
                    'requestTime'  => $a->getRequestTime() ?? 0.0,
                    'totalQueries' => $a->getTotalQueries() ?? 0,
                    'queryTime'    => $a->getQueryTime() ?? 0.0,
                    'accessCount'  => $a->getAccessCount(),
                    'memoryUsage'  => $a->getMemoryUsage() ?? 0,
                    default        => 0,
                };
                $bv = match ($sortBy) {
                    'requestTime'  => $b->getRequestTime() ?? 0.0,
                    'totalQueries' => $b->getTotalQueries() ?? 0,
                    'queryTime'    => $b->getQueryTime() ?? 0.0,
                    'accessCount'  => $b->getAccessCount(),
                    'memoryUsage'  => $b->getMemoryUsage() ?? 0,
                    default        => 0,
                };

                return $desc ? $bv <=> $av : $av <=> $bv;
            });
        }

        return $withAggregates;
    }

    /**
     * Get worst performing routes for an environment.
     *
     * Returns routes ordered by request time descending (worst first).
     *
     * @param string $env The environment (dev, test, prod)
     * @param int $limit Maximum number of results to return (default: 10)
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
     * Returns the open private entity manager metrics are written with, creating it when needed.
     */
    private function writeEntityManager(): EntityManagerInterface
    {
        if ($this->writeEntityManager instanceof EntityManagerInterface && $this->writeEntityManager->isOpen()) {
            return $this->writeEntityManager;
        }

        $factory = $this->writeEntityManagerFactory ?? new MetricsEntityManagerFactory();

        // @igor-ignore - Private unit of work of the write path, cleared after every recording and dropped on error.
        $this->writeEntityManager = $factory->create($this->entityManager);

        return $this->writeEntityManager;
    }

    /**
     * Loads the RouteData of a route and environment through the private write manager.
     */
    private function findRouteDataForWrite(EntityManagerInterface $entityManager, string $routeName, string $env): ?RouteData
    {
        $routeData = $entityManager->createQueryBuilder()
            ->select('r')
            ->from(RouteData::class, 'r')
            ->where('r.name = :name')
            ->andWhere('r.env = :env')
            ->setParameter('name', $routeName)
            ->setParameter('env', $env)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $routeData instanceof RouteData ? $routeData : null;
    }

    /**
     * Tells whether an access record already exists for the request id (private write manager).
     */
    private function accessRecordExistsForWrite(EntityManagerInterface $entityManager, string $requestId): bool
    {
        $count = $entityManager->createQueryBuilder()
            ->select('COUNT(rec.id)')
            ->from(RouteDataRecord::class, 'rec')
            ->where('rec.requestId = :requestId')
            ->setParameter('requestId', $requestId)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
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
