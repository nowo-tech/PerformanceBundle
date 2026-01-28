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
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class RouteDataRepository extends ServiceEntityRepository
{
    /**
     * Creates a new instance.
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
     * @param string $env       The environment (dev, test, prod)
     *
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
     * Ordered by last accessed descending. For metrics use getRoutesWithAggregates (from records).
     *
     * @param string $env The environment (dev, test, prod)
     *
     * @return RouteData[] Array of route data entities
     */
    public function findByEnvironment(string $env): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.env = :env')
            ->setParameter('env', $env)
            ->orderBy('r.lastAccessedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find routes with worst performance for a given environment.
     *
     * Ordered by last accessed; for performance ranking use aggregates (RouteDataRecord).
     *
     * @param string $env   The environment (dev, test, prod)
     * @param int    $limit Maximum number of results to return (default: 10)
     *
     * @return RouteData[] Array of route data entities
     */
    public function findWorstPerforming(string $env, int $limit = 10): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.env = :env')
            ->setParameter('env', $env)
            ->orderBy('r.lastAccessedAt', 'DESC')
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

    /**
     * Find routes with advanced filtering options.
     *
     * @param string               $env     The environment (dev, test, prod)
     * @param array<string, mixed> $filters Filter options:
     *                                      - route_names: string[] - Array of route names to filter (OR condition)
     *                                      - route_name_pattern: string - Pattern to match route names (LIKE)
     *                                      - min_request_time: float - Minimum request time
     *                                      - max_request_time: float - Maximum request time
     *                                      - min_query_count: int - Minimum query count
     *                                      - max_query_count: int - Maximum query count
     *                                      - min_query_time: float - Minimum query time
     *                                      - max_query_time: float - Maximum query time
     *                                      - date_from: \DateTimeImmutable - Filter from date (createdAt)
     *                                      - date_to: \DateTimeImmutable - Filter to date (createdAt)
     * @param string               $sortBy  Field to sort by (default: 'requestTime')
     * @param string               $order   Sort order: 'ASC' or 'DESC' (default: 'DESC')
     * @param int|null             $limit   Maximum number of results (null for no limit)
     *
     * @return RouteData[] Array of route data entities
     */
    public function findWithFilters(
        string $env,
        array $filters = [],
        string $sortBy = 'requestTime',
        string $order = 'DESC',
        ?int $limit = null,
    ): array {
        $qb = $this->createQueryBuilder('r')
            ->where('r.env = :env')
            ->setParameter('env', $env);

        // Filter by route names (multiple routes with OR)
        if (!empty($filters['route_names']) && \is_array($filters['route_names'])) {
            $qb->andWhere('r.name IN (:route_names)')
                ->setParameter('route_names', $filters['route_names']);
        }

        // Filter by route name pattern (LIKE)
        if (!empty($filters['route_name_pattern']) && \is_string($filters['route_name_pattern'])) {
            $qb->andWhere('r.name LIKE :route_pattern')
                ->setParameter('route_pattern', '%'.$filters['route_name_pattern'].'%');
        }

        // Metric filters (request_time, total_queries, query_time) are applied via aggregates in PHP; only entity fields here
        // Filter by date range
        if (isset($filters['date_from']) && $filters['date_from'] instanceof \DateTimeImmutable) {
            $qb->andWhere('r.createdAt >= :date_from')
                ->setParameter('date_from', $filters['date_from']);
        }
        if (isset($filters['date_to']) && $filters['date_to'] instanceof \DateTimeImmutable) {
            $qb->andWhere('r.createdAt <= :date_to')
                ->setParameter('date_to', $filters['date_to']);
        }

        // Sort only by entity fields (metrics come from aggregates; use getRoutesWithAggregates for metric sort)
        $allowedSortFields = ['name', 'createdAt', 'lastAccessedAt'];
        $sortField = \in_array($sortBy, $allowedSortFields, true) ? $sortBy : 'lastAccessedAt';
        $sortOrder = 'ASC' === strtoupper($order) ? 'ASC' : 'DESC';

        $qb->orderBy('r.'.$sortField, $sortOrder);

        if (null !== $limit && $limit > 0) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Delete all route data records.
     *
     * Optionally filters by environment.
     *
     * @param string|null $env Optional environment filter (if null, deletes all)
     *
     * @return int Number of deleted records
     */
    public function deleteAll(?string $env = null): int
    {
        $qb = $this->createQueryBuilder('r')
            ->delete();

        if (null !== $env) {
            $qb->where('r.env = :env')
                ->setParameter('env', $env);
        }

        return $qb->getQuery()->execute();
    }

    /**
     * Delete a single route data record by ID.
     *
     * @param int $id The record ID
     *
     * @return bool True if deleted, false if not found
     */
    public function deleteById(int $id): bool
    {
        $routeData = $this->find($id);
        if (null === $routeData) {
            return false;
        }

        $this->getEntityManager()->remove($routeData);
        $this->getEntityManager()->flush();

        return true;
    }

    /**
     * Get the ranking position of a route by request time.
     *
     * Metrics are now in RouteDataRecord; ranking must be computed from aggregates.
     * Use PerformanceMetricsService or RouteDataRecordRepository for ranking by request time.
     *
     * @return int|null Null (ranking from records not implemented here)
     */
    public function getRankingByRequestTime(string|RouteData $routeNameOrData, string $env = ''): ?int
    {
        return null;
    }

    /**
     * Get the ranking position of a route by query count.
     *
     * Metrics are now in RouteDataRecord; ranking must be computed from aggregates.
     *
     * @return int|null Null (ranking from records not implemented here)
     */
    public function getRankingByQueryCount(string|RouteData $routeNameOrData, string $env = ''): ?int
    {
        return null;
    }

    /**
     * Get total number of routes in an environment.
     *
     * @param string $env The environment
     *
     * @return int Total number of routes
     */
    public function getTotalRoutesCount(string $env): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.env = :env')
            ->setParameter('env', $env)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get all routes for advanced statistics calculation.
     *
     * @param string $env The environment
     *
     * @return RouteData[] Array of route data entities
     */
    public function findAllForStatistics(string $env): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.env = :env')
            ->setParameter('env', $env)
            ->getQuery()
            ->getResult();
    }

    /**
     * Mark a route data record as reviewed.
     *
     * @param int         $id              The record ID
     * @param bool|null   $queriesImproved Whether queries improved
     * @param bool|null   $timeImproved    Whether time improved
     * @param string|null $reviewedBy      The reviewer username
     *
     * @return bool True if updated, false if not found
     */
    public function markAsReviewed(int $id, ?bool $queriesImproved = null, ?bool $timeImproved = null, ?string $reviewedBy = null): bool
    {
        $routeData = $this->find($id);
        if (null === $routeData) {
            return false;
        }

        $routeData->markAsReviewed($queriesImproved, $timeImproved, $reviewedBy);
        $this->getEntityManager()->flush();

        return true;
    }
}
