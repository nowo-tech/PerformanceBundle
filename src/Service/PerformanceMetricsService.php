<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

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
     *
     * @var EntityManagerInterface
     */
    private readonly EntityManagerInterface $entityManager;

    /**
     * The repository for RouteData entities.
     *
     * @var RouteDataRepository
     */
    private readonly RouteDataRepository $repository;

    /**
     * Constructor.
     *
     * @param ManagerRegistry $registry The Doctrine registry
     * @param string $connectionName The name of the Doctrine connection to use
     */
    public function __construct(
        ManagerRegistry $registry,
        #[Autowire('%nowo_performance.connection%')]
        string $connectionName
    ) {
        $this->entityManager = $registry->getManager($connectionName);
        $this->repository = $this->entityManager->getRepository(RouteData::class);
    }

    /**
     * Record or update route performance metrics.
     *
     * @param string $routeName The route name
     * @param string $env The environment (dev, test, prod)
     * @param float|null $requestTime Request execution time in seconds
     * @param int|null $totalQueries Total number of database queries
     * @param float|null $queryTime Total query execution time in seconds
     * @param array|null $params Route parameters
     */
    public function recordMetrics(
        string $routeName,
        string $env,
        ?float $requestTime = null,
        ?int $totalQueries = null,
        ?float $queryTime = null,
        ?array $params = null
    ): void {
        $routeData = $this->repository->findByRouteAndEnv($routeName, $env);

        if ($routeData === null) {
            // Create new record
            $routeData = new RouteData();
            $routeData->setName($routeName);
            $routeData->setEnv($env);
            $routeData->setRequestTime($requestTime);
            $routeData->setTotalQueries($totalQueries);
            $routeData->setQueryTime($queryTime);
            $routeData->setParams($params);

            $this->entityManager->persist($routeData);
        } elseif ($routeData->shouldUpdate($requestTime, $totalQueries)) {
            // Update if metrics are worse (higher time or more queries)
            if ($requestTime !== null && ($routeData->getRequestTime() === null || $requestTime > $routeData->getRequestTime())) {
                $routeData->setRequestTime($requestTime);
            }

            if ($totalQueries !== null && ($routeData->getTotalQueries() === null || $totalQueries > $routeData->getTotalQueries())) {
                $routeData->setTotalQueries($totalQueries);
            }

            if ($queryTime !== null) {
                $routeData->setQueryTime($queryTime);
            }

            if ($params !== null) {
                $routeData->setParams($params);
            }
        }

        try {
            // Suppress any potential output from Doctrine
            $errorReporting = error_reporting(0);
            $this->entityManager->flush();
            error_reporting($errorReporting);
        } catch (\Exception $e) {
            // Restore error reporting
            if (isset($errorReporting)) {
                error_reporting($errorReporting);
            }
            // Silently fail to not break the application
            // In production, you might want to log this
        }
    }

    /**
     * Get route data by name and environment.
     *
     * @param string $routeName The route name
     * @param string $env The environment (dev, test, prod)
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
     * @param string $env The environment (dev, test, prod)
     * @param int $limit Maximum number of results to return (default: 10)
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
