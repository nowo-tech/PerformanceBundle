<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\Entity\RouteData;

/**
 * Repository for RouteData entity.
 *
 * Provides custom query methods for retrieving route performance data.
 *
 * @extends ServiceEntityRepository<RouteData>
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
class RouteDataRepository extends ServiceEntityRepository
{
    /**
     * Constructor.
     *
     * @param ManagerRegistry $registry The Doctrine registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RouteData::class);
    }

    /**
     * Find route data by name and environment.
     *
     * @param string $routeName The route name
     * @param string $env The environment (dev, test, prod)
     * @return RouteData|null The route data or null if not found
     */
    public function findByRouteAndEnv(string $routeName, string $env): ?RouteData
    {
        return $this->createQueryBuilder('r')
            ->where('r.name = :name')
            ->andWhere('r.env = :env')
            ->setParameter('name', $routeName)
            ->setParameter('env', $env)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all routes for a given environment.
     *
     * Results are ordered by request time descending (worst performing first).
     *
     * @param string $env The environment (dev, test, prod)
     * @return RouteData[] Array of route data entities
     */
    public function findByEnvironment(string $env): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.env = :env')
            ->setParameter('env', $env)
            ->orderBy('r.requestTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find routes with worst performance for a given environment.
     *
     * Returns the routes with the highest request times, ordered descending.
     *
     * @param string $env The environment (dev, test, prod)
     * @param int $limit Maximum number of results to return (default: 10)
     * @return RouteData[] Array of route data entities ordered by worst performance
     */
    public function findWorstPerforming(string $env, int $limit = 10): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.env = :env')
            ->setParameter('env', $env)
            ->orderBy('r.requestTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get distinct environments from all routes.
     *
     * @return string[] Array of distinct environment names
     */
    public function getDistinctEnvironments(): array
    {
        $result = $this->createQueryBuilder('r')
            ->select('DISTINCT r.env')
            ->where('r.env IS NOT NULL')
            ->orderBy('r.env', 'ASC')
            ->getQuery()
            ->getResult();

        return array_column($result, 'env');
    }
}
