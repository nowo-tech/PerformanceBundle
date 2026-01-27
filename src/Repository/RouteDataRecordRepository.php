<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\Entity\RouteDataRecord;

/**
 * Repository for RouteDataRecord entities.
 *
 * @extends ServiceEntityRepository<RouteDataRecord>
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
class RouteDataRecordRepository extends ServiceEntityRepository
{
    /**
     * Constructor.
     *
     * @param ManagerRegistry $registry The Doctrine registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RouteDataRecord::class);
    }

    /**
     * Get access statistics grouped by hour of day.
     *
     * @param string                  $env       The environment (dev, test, prod)
     * @param \DateTimeImmutable|null $startDate Optional start date filter
     * @param \DateTimeImmutable|null $endDate   Optional end date filter
     *
     * @return array<int, array{hour: int, count: int, avg_response_time: float, status_codes: array<int, int>}> Statistics by hour
     */
    public function getStatisticsByHour(
        string $env,
        ?\DateTimeImmutable $startDate = null,
        ?\DateTimeImmutable $endDate = null,
    ): array {
        $qb = $this->createQueryBuilder('r')
            ->select('HOUR(r.accessedAt) as hour')
            ->addSelect('COUNT(r.id) as count')
            ->addSelect('AVG(r.responseTime) as avg_response_time')
            ->join('r.routeData', 'rd')
            ->where('rd.env = :env')
            ->setParameter('env', $env)
            ->groupBy('hour')
            ->orderBy('hour', 'ASC');

        if (null !== $startDate) {
            $qb->andWhere('r.accessedAt >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        if (null !== $endDate) {
            $qb->andWhere('r.accessedAt <= :endDate')
                ->setParameter('endDate', $endDate);
        }

        $results = $qb->getQuery()->getResult();

        // Get status code counts per hour
        $statusCodeQb = $this->createQueryBuilder('r')
            ->select('HOUR(r.accessedAt) as hour')
            ->addSelect('r.statusCode as status_code')
            ->addSelect('COUNT(r.id) as count')
            ->join('r.routeData', 'rd')
            ->where('rd.env = :env')
            ->setParameter('env', $env)
            ->andWhere('r.statusCode IS NOT NULL')
            ->groupBy('hour', 'status_code')
            ->orderBy('hour', 'ASC');

        if (null !== $startDate) {
            $statusCodeQb->andWhere('r.accessedAt >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        if (null !== $endDate) {
            $statusCodeQb->andWhere('r.accessedAt <= :endDate')
                ->setParameter('endDate', $endDate);
        }

        $statusCodeResults = $statusCodeQb->getQuery()->getResult();

        // Build status codes array by hour
        $statusCodesByHour = [];
        foreach ($statusCodeResults as $row) {
            $hour = (int) $row['hour'];
            if (!isset($statusCodesByHour[$hour])) {
                $statusCodesByHour[$hour] = [];
            }
            $statusCodesByHour[$hour][(int) $row['status_code']] = (int) $row['count'];
        }

        // Combine results
        $statistics = [];
        foreach ($results as $row) {
            $hour = (int) $row['hour'];
            $statistics[$hour] = [
                'hour' => $hour,
                'count' => (int) $row['count'],
                'avg_response_time' => null !== $row['avg_response_time'] ? (float) $row['avg_response_time'] : 0.0,
                'status_codes' => $statusCodesByHour[$hour] ?? [],
            ];
        }

        // Fill in missing hours (0-23) with zero counts
        for ($hour = 0; $hour < 24; ++$hour) {
            if (!isset($statistics[$hour])) {
                $statistics[$hour] = [
                    'hour' => $hour,
                    'count' => 0,
                    'avg_response_time' => 0.0,
                    'status_codes' => [],
                ];
            }
        }

        ksort($statistics);

        return array_values($statistics);
    }

    /**
     * Get total access count for a date range.
     *
     * @param string                  $env       The environment
     * @param \DateTimeImmutable|null $startDate Optional start date
     * @param \DateTimeImmutable|null $endDate   Optional end date
     *
     * @return int Total access count
     */
    public function getTotalAccessCount(
        string $env,
        ?\DateTimeImmutable $startDate = null,
        ?\DateTimeImmutable $endDate = null,
    ): int {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->join('r.routeData', 'rd')
            ->where('rd.env = :env')
            ->setParameter('env', $env);

        if (null !== $startDate) {
            $qb->andWhere('r.accessedAt >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        if (null !== $endDate) {
            $qb->andWhere('r.accessedAt <= :endDate')
                ->setParameter('endDate', $endDate);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Delete all records for a specific route data.
     *
     * @param int $routeDataId The route data ID
     *
     * @return int Number of deleted records
     */
    public function deleteByRouteData(int $routeDataId): int
    {
        return $this->createQueryBuilder('r')
            ->delete()
            ->where('r.routeData = :routeDataId')
            ->setParameter('routeDataId', $routeDataId)
            ->getQuery()
            ->execute();
    }

    /**
     * Delete all records for an environment.
     *
     * @param string $env The environment
     *
     * @return int Number of deleted records
     */
    public function deleteByEnvironment(string $env): int
    {
        return $this->createQueryBuilder('r')
            ->delete()
            ->join('r.routeData', 'rd')
            ->where('rd.env = :env')
            ->setParameter('env', $env)
            ->getQuery()
            ->execute();
    }
}
