<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Event\AfterMetricsRecordedEvent;
use Nowo\PerformanceBundle\Event\BeforeMetricsRecordedEvent;
use Nowo\PerformanceBundle\Message\RecordMetricsMessage;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Service for managing route performance metrics.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
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
     * Constructor.
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
    ) {
        $this->entityManager = $registry->getManager($connectionName);
        $this->repository = $this->entityManager->getRepository(RouteData::class);
        $this->async = $async;
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
     * @param string      $routeName    The route name
     * @param string      $env          The environment (dev, test, prod)
     * @param float|null  $requestTime  Request execution time in seconds
     * @param int|null    $totalQueries Total number of database queries
     * @param float|null  $queryTime    Total query execution time in seconds
     * @param array|null  $params       Route parameters
     * @param int|null    $memoryUsage  Peak memory usage in bytes
     * @param string|null $httpMethod   HTTP method (GET, POST, PUT, DELETE, etc.)
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
    ): void {
        // Dispatch before event to allow modification of metrics
        if (null !== $this->eventDispatcher) {
            $beforeEvent = new BeforeMetricsRecordedEvent(
                $routeName,
                $env,
                $requestTime,
                $totalQueries,
                $queryTime,
                $params,
                $memoryUsage
            );
            $this->eventDispatcher->dispatch($beforeEvent);

            // Use modified values from event
            $requestTime = $beforeEvent->getRequestTime();
            $totalQueries = $beforeEvent->getTotalQueries();
            $queryTime = $beforeEvent->getQueryTime();
            $params = $beforeEvent->getParams();
            $memoryUsage = $beforeEvent->getMemoryUsage();
        }

        // If async mode is enabled and message bus is available, dispatch message
        if ($this->async && null !== $this->messageBus) {
            $message = new RecordMetricsMessage(
                $routeName,
                $env,
                $requestTime,
                $totalQueries,
                $queryTime,
                $params,
                $memoryUsage,
                $httpMethod
            );
            $this->messageBus->dispatch($message);

            return;
        }

        // Otherwise, record synchronously
        $this->recordMetricsSync($routeName, $env, $requestTime, $totalQueries, $queryTime, $params, $memoryUsage, $httpMethod);
    }

    /**
     * Record metrics synchronously (internal method).
     *
     * @param string      $routeName    The route name
     * @param string      $env          The environment
     * @param float|null  $requestTime  Request execution time in seconds
     * @param int|null    $totalQueries Total number of database queries
     * @param float|null  $queryTime    Total query execution time in seconds
     * @param array|null  $params       Route parameters
     * @param int|null    $memoryUsage  Peak memory usage in bytes
     * @param string|null $httpMethod   HTTP method (GET, POST, PUT, DELETE, etc.)
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
    ): void {
        $isNew = false;
        $routeData = $this->repository->findByRouteAndEnv($routeName, $env);

        if (null === $routeData) {
            // Create new record (accessCount defaults to 1)
            $routeData = new RouteData();
            $routeData->setName($routeName);
            $routeData->setEnv($env);
            $routeData->setRequestTime($requestTime);
            $routeData->setTotalQueries($totalQueries);
            $routeData->setQueryTime($queryTime);
            $routeData->setParams($params);
            $routeData->setMemoryUsage($memoryUsage);
            $routeData->setHttpMethod($httpMethod);
            // accessCount is already initialized to 1 in the entity

            $this->entityManager->persist($routeData);
            $isNew = true;
        } else {
            // Always increment access count when route is accessed
            $routeData->incrementAccessCount();

            // Update metrics if they are worse (higher time or more queries)
            if ($routeData->shouldUpdate($requestTime, $totalQueries)) {
                if (null !== $requestTime && (null === $routeData->getRequestTime() || $requestTime > $routeData->getRequestTime())) {
                    $routeData->setRequestTime($requestTime);
                }

                if (null !== $totalQueries && (null === $routeData->getTotalQueries() || $totalQueries > $routeData->getTotalQueries())) {
                    $routeData->setTotalQueries($totalQueries);
                }

                if (null !== $queryTime) {
                    $routeData->setQueryTime($queryTime);
                }

                if (null !== $params) {
                    $routeData->setParams($params);
                }

                // Update memory usage if it's higher (worse)
                if (null !== $memoryUsage && (null === $routeData->getMemoryUsage() || $memoryUsage > $routeData->getMemoryUsage())) {
                    $routeData->setMemoryUsage($memoryUsage);
                }

                // Update HTTP method if provided
                if (null !== $httpMethod) {
                    $routeData->setHttpMethod($httpMethod);
                }
            }
        }

        try {
            if (\function_exists('error_log')) {
                error_log(\sprintf(
                    '[PerformanceBundle] Before flush: route=%s, env=%s, isNew=%s',
                    $routeName,
                    $env,
                    $isNew ? 'true' : 'false'
                ));
            }

            // Suppress any potential output from Doctrine
            $errorReporting = error_reporting(0);
            $this->entityManager->flush();
            error_reporting($errorReporting);

            if (\function_exists('error_log')) {
                error_log(\sprintf(
                    '[PerformanceBundle] After flush SUCCESS: route=%s, env=%s, isNew=%s',
                    $routeName,
                    $env,
                    $isNew ? 'true' : 'false'
                ));
            }

            // Invalidate cache for this environment after update
            if (null !== $this->cacheService) {
                $this->cacheService->invalidateStatistics($env);
            }

            // Dispatch after event
            if (null !== $this->eventDispatcher) {
                $afterEvent = new AfterMetricsRecordedEvent($routeData, $isNew);
                $this->eventDispatcher->dispatch($afterEvent);
            }
        } catch (\Exception $e) {
            // Restore error reporting
            if (isset($errorReporting)) {
                error_reporting($errorReporting);
            }

            // Log the error for debugging
            if (\function_exists('error_log')) {
                error_log(\sprintf(
                    '[PerformanceBundle] Error in flush: route=%s, env=%s, error=%s',
                    $routeName,
                    $env,
                    $e->getMessage()
                ));
            }

            // Re-throw to let the subscriber handle it
            throw $e;
        }
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
}
