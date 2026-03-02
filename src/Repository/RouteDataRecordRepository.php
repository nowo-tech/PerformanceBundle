<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Repository;

use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Entity\RouteDataRecord;

use function count;

/**
 * Repository for RouteDataRecord entities.
 *
 * @extends ServiceEntityRepository<RouteDataRecord>
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class RouteDataRecordRepository extends ServiceEntityRepository
{
    /**
     * Creates a new instance.
     *
     * @param ManagerRegistry $registry The Doctrine registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RouteDataRecord::class);
    }

    /**
     * Find one access record by request ID (for deduplication).
     *
     * @param string $requestId The unique request identifier
     *
     * @return RouteDataRecord|null The record if found
     */
    public function findOneByRequestId(string $requestId): ?RouteDataRecord
    {
        return $this->findOneBy(['requestId' => $requestId], ['id' => 'ASC']);
    }

    /**
     * Get access statistics grouped by hour of day.
     *
     * This implementation performs grouping in PHP using DateTime formatting,
     * which keeps it compatible with all supported database platforms
     * (MySQL, PostgreSQL, SQLite, etc.).
     *
     * @param string $env The environment (dev, test, prod)
     * @param DateTimeImmutable|null $startDate Optional start date filter
     * @param DateTimeImmutable|null $endDate Optional end date filter
     * @param string|null $routeName Optional route name filter
     * @param int|null $statusCode Optional status code filter
     *
     * @return array<int, array{hour: int, count: int, avg_response_time: float, status_codes: array<int, int>}> Statistics by hour
     */
    public function getStatisticsByHour(
        string $env,
        ?DateTimeImmutable $startDate = null,
        ?DateTimeImmutable $endDate = null,
        ?string $routeName = null,
        ?int $statusCode = null,
    ): array {
        $qb = $this->createQueryBuilder('r')
            ->select('partial r.{id, accessedAt, statusCode, responseTime}')
            ->join('r.routeData', 'rd')
            ->where('rd.env = :env')
            ->setParameter('env', $env)
            ->orderBy('r.accessedAt', 'ASC');

        if ($startDate !== null) {
            $qb->andWhere('r.accessedAt >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        if ($endDate !== null) {
            $qb->andWhere('r.accessedAt <= :endDate')
                ->setParameter('endDate', $endDate);
        }

        if ($routeName !== null) {
            $qb->andWhere('rd.name = :routeName')
                ->setParameter('routeName', $routeName);
        }

        if ($statusCode !== null) {
            $qb->andWhere('r.statusCode = :statusCode')
                ->setParameter('statusCode', $statusCode);
        }

        /** @var RouteDataRecord[] $records */
        $records = $qb->getQuery()->getResult();

        // Initialize statistics for each hour
        $statistics = [];
        for ($hour = 0; $hour < 24; ++$hour) {
            $statistics[$hour] = [
                'hour'              => $hour,
                'count'             => 0,
                'sum_response_time' => 0.0,
                'avg_response_time' => 0.0,
                'status_codes'      => [],
            ];
        }

        // Aggregate data in PHP
        foreach ($records as $record) {
            $hour = (int) $record->getAccessedAt()->format('G'); // 0-23

            ++$statistics[$hour]['count'];

            if ($record->getResponseTime() !== null) {
                $statistics[$hour]['sum_response_time'] += $record->getResponseTime();
            }

            if ($record->getStatusCode() !== null) {
                $code = $record->getStatusCode();
                if (!isset($statistics[$hour]['status_codes'][$code])) {
                    $statistics[$hour]['status_codes'][$code] = 0;
                }
                ++$statistics[$hour]['status_codes'][$code];
            }
        }

        // Calculate averages and clean up structure
        foreach ($statistics as $hour => $data) {
            if ($data['count'] > 0) {
                $statistics[$hour]['avg_response_time'] = $data['sum_response_time'] / $data['count'];
            } else {
                $statistics[$hour]['avg_response_time'] = 0.0;
            }

            unset($statistics[$hour]['sum_response_time']);
        }

        ksort($statistics);

        return array_values($statistics);
    }

    /**
     * Get total access count for a date range.
     *
     * @param string $env The environment
     * @param DateTimeImmutable|null $startDate Optional start date
     * @param DateTimeImmutable|null $endDate Optional end date
     * @param string|null $routeName Optional route name filter
     * @param int|null $statusCode Optional status code filter
     *
     * @return int Total access count
     */
    public function getTotalAccessCount(
        string $env,
        ?DateTimeImmutable $startDate = null,
        ?DateTimeImmutable $endDate = null,
        ?string $routeName = null,
        ?int $statusCode = null,
    ): int {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->join('r.routeData', 'rd')
            ->where('rd.env = :env')
            ->setParameter('env', $env);

        if ($startDate !== null) {
            $qb->andWhere('r.accessedAt >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        if ($endDate !== null) {
            $qb->andWhere('r.accessedAt <= :endDate')
                ->setParameter('endDate', $endDate);
        }

        if ($routeName !== null) {
            $qb->andWhere('rd.name = :routeName')
                ->setParameter('routeName', $routeName);
        }

        if ($statusCode !== null) {
            $qb->andWhere('r.statusCode = :statusCode')
                ->setParameter('statusCode', $statusCode);
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
     * Count access records for a given RouteData (replaces accessCount after entity normalization).
     *
     * @param RouteData $routeData The route data entity
     *
     * @return int Number of RouteDataRecord rows for this route
     */
    public function countByRouteData(RouteData $routeData): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.routeData = :routeData')
            ->setParameter('routeData', $routeData)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Delete all access records, optionally filtered by environment.
     *
     * @param string|null $env Environment to limit deletion (null = all environments)
     *
     * @return int Number of deleted records
     */
    public function deleteAllRecords(?string $env = null): int
    {
        if ($env !== null && $env !== '') {
            return $this->deleteByEnvironment($env);
        }

        $totalDeleted = 0;
        $batchSize    = 1000;

        while (true) {
            $qb = $this->createQueryBuilder('r')
                ->select('r.id')
                ->setMaxResults($batchSize);

            /** @var array<int, int> $ids */
            $ids = $qb->getQuery()->getSingleColumnResult();

            if (empty($ids)) {
                break;
            }

            $deleted = $this->createQueryBuilder('r')
                ->delete()
                ->where('r.id IN (:ids)')
                ->setParameter('ids', $ids)
                ->getQuery()
                ->execute();

            $totalDeleted += $deleted;
        }

        return $totalDeleted;
    }

    /**
     * Delete access records older than the given date.
     *
     * @param DateTimeImmutable $before Cutoff date (records with accessedAt < before are deleted)
     * @param string|null $env Optional environment filter (null = all environments)
     * @param int $batchSize Max IDs per batch
     *
     * @return int Number of deleted records
     */
    public function deleteOlderThan(DateTimeImmutable $before, ?string $env = null, int $batchSize = 1000): int
    {
        $totalDeleted = 0;

        while (true) {
            $qb = $this->createQueryBuilder('r')
                ->select('r.id')
                ->where('r.accessedAt < :before')
                ->setParameter('before', $before)
                ->setMaxResults($batchSize);

            if ($env !== null && $env !== '') {
                $qb->join('r.routeData', 'rd')
                    ->andWhere('rd.env = :env')
                    ->setParameter('env', $env);
            }

            /** @var array<int, int> $ids */
            $ids = $qb->getQuery()->getSingleColumnResult();

            if (empty($ids)) {
                break;
            }

            $deleted = $this->createQueryBuilder('r')
                ->delete()
                ->where('r.id IN (:ids)')
                ->setParameter('ids', $ids)
                ->getQuery()
                ->execute();

            $totalDeleted += $deleted;
        }

        return $totalDeleted;
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

    /**
     * Delete records matching the given filters.
     *
     * Uses a select-then-delete approach because DQL DELETE does not support JOIN.
     * Deletes in batches to avoid loading too many IDs into memory.
     *
     * @param string $env The environment (required)
     * @param DateTimeImmutable|null $startDate Optional start date (records with accessedAt >= startDate)
     * @param DateTimeImmutable|null $endDate Optional end date (records with accessedAt <= endDate)
     * @param string|null $routeName Optional route name (records for this route only)
     * @param int|null $statusCode Optional HTTP status code (records with this status only)
     * @param float|null $minQueryTime Optional min query time (s)
     * @param float|null $maxQueryTime Optional max query time (s)
     * @param int|null $minMemoryUsage Optional min memory (bytes)
     * @param int|null $maxMemoryUsage Optional max memory (bytes)
     * @param int $batchSize Max IDs per batch (default 1000)
     *
     * @return int Number of deleted records
     */
    public function deleteByFilter(
        string $env,
        ?DateTimeImmutable $startDate = null,
        ?DateTimeImmutable $endDate = null,
        ?string $routeName = null,
        ?int $statusCode = null,
        ?float $minQueryTime = null,
        ?float $maxQueryTime = null,
        ?int $minMemoryUsage = null,
        ?int $maxMemoryUsage = null,
        ?string $referer = null,
        ?string $user = null,
        int $batchSize = 1000,
    ): int {
        $totalDeleted = 0;

        while (true) {
            $qb = $this->createQueryBuilder('r')
                ->select('r.id')
                ->join('r.routeData', 'rd')
                ->where('rd.env = :env')
                ->setParameter('env', $env)
                ->setMaxResults($batchSize);

            $this->applyRecordFilters($qb, $startDate, $endDate, $routeName, $statusCode, $minQueryTime, $maxQueryTime, $minMemoryUsage, $maxMemoryUsage, $referer, $user);

            /** @var array<int, int> $ids */
            $ids = $qb->getQuery()->getSingleColumnResult();

            if (empty($ids)) {
                break;
            }

            $deleted = $this->createQueryBuilder('r')
                ->delete()
                ->where('r.id IN (:ids)')
                ->setParameter('ids', $ids)
                ->getQuery()
                ->execute();

            $totalDeleted += $deleted;
        }

        return $totalDeleted;
    }

    /**
     * Apply common record filters to a query builder.
     *
     * @param \Doctrine\ORM\QueryBuilder $qb Query builder
     * @param DateTimeImmutable|null $startDate Optional start date
     * @param DateTimeImmutable|null $endDate Optional end date
     * @param string|null $routeName Optional route name
     * @param int|null $statusCode Optional status code
     * @param float|null $minQueryTime Optional min query time (s)
     * @param float|null $maxQueryTime Optional max query time (s)
     * @param int|null $minMemoryUsage Optional min memory (bytes)
     * @param int|null $maxMemoryUsage Optional max memory (bytes)
     */
    private function applyRecordFilters(
        \Doctrine\ORM\QueryBuilder $qb,
        ?DateTimeImmutable $startDate = null,
        ?DateTimeImmutable $endDate = null,
        ?string $routeName = null,
        ?int $statusCode = null,
        ?float $minQueryTime = null,
        ?float $maxQueryTime = null,
        ?int $minMemoryUsage = null,
        ?int $maxMemoryUsage = null,
        ?string $referer = null,
        ?string $user = null,
    ): void {
        if ($startDate !== null) {
            $qb->andWhere('r.accessedAt >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        if ($endDate !== null) {
            $qb->andWhere('r.accessedAt <= :endDate')
                ->setParameter('endDate', $endDate);
        }

        if ($routeName !== null && $routeName !== '') {
            $qb->andWhere('rd.name = :routeName')
                ->setParameter('routeName', $routeName);
        }

        if ($statusCode !== null) {
            $qb->andWhere('r.statusCode = :statusCode')
                ->setParameter('statusCode', $statusCode);
        }

        if ($minQueryTime !== null) {
            $qb->andWhere('r.queryTime >= :minQueryTime')
                ->setParameter('minQueryTime', $minQueryTime);
        }

        if ($maxQueryTime !== null) {
            $qb->andWhere('r.queryTime <= :maxQueryTime')
                ->setParameter('maxQueryTime', $maxQueryTime);
        }

        if ($minMemoryUsage !== null) {
            $qb->andWhere('r.memoryUsage >= :minMemoryUsage')
                ->setParameter('minMemoryUsage', $minMemoryUsage);
        }

        if ($maxMemoryUsage !== null) {
            $qb->andWhere('r.memoryUsage <= :maxMemoryUsage')
                ->setParameter('maxMemoryUsage', $maxMemoryUsage);
        }

        if ($referer !== null && $referer !== '') {
            $qb->andWhere('r.referer LIKE :referer')
                ->setParameter('referer', '%' . addcslashes($referer, '%_') . '%');
        }

        if ($user !== null && $user !== '') {
            $userPattern = '%' . addcslashes($user, '%_') . '%';
            $qb->andWhere('r.userIdentifier LIKE :userFilter OR r.userId LIKE :userFilter')
                ->setParameter('userFilter', $userPattern);
        }
    }

    /**
     * Get access statistics grouped by day of week (0=Sunday, 6=Saturday).
     *
     * Grouping is done in PHP to remain database agnostic.
     *
     * @param string $env The environment
     * @param DateTimeImmutable|null $startDate Optional start date filter
     * @param DateTimeImmutable|null $endDate Optional end date filter
     * @param string|null $routeName Optional route name filter
     * @param int|null $statusCode Optional status code filter
     *
     * @return array<int, array{day_of_week: int, day_name: string, count: int, avg_response_time: float, status_codes: array<int, int>}> Statistics by day of week
     */
    public function getStatisticsByDayOfWeek(
        string $env,
        ?DateTimeImmutable $startDate = null,
        ?DateTimeImmutable $endDate = null,
        ?string $routeName = null,
        ?int $statusCode = null,
    ): array {
        $qb = $this->createQueryBuilder('r')
            ->select('partial r.{id, accessedAt, statusCode, responseTime}')
            ->join('r.routeData', 'rd')
            ->where('rd.env = :env')
            ->setParameter('env', $env)
            ->orderBy('r.accessedAt', 'ASC');

        if ($startDate !== null) {
            $qb->andWhere('r.accessedAt >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        if ($endDate !== null) {
            $qb->andWhere('r.accessedAt <= :endDate')
                ->setParameter('endDate', $endDate);
        }

        if ($routeName !== null) {
            $qb->andWhere('rd.name = :routeName')
                ->setParameter('routeName', $routeName);
        }

        if ($statusCode !== null) {
            $qb->andWhere('r.statusCode = :statusCode')
                ->setParameter('statusCode', $statusCode);
        }

        /** @var RouteDataRecord[] $records */
        $records = $qb->getQuery()->getResult();

        $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        // Initialize statistics for each day (0-6)
        $statistics = [];
        for ($day = 0; $day < 7; ++$day) {
            $statistics[$day] = [
                'day_of_week'       => $day,
                'day_name'          => $dayNames[$day],
                'count'             => 0,
                'sum_response_time' => 0.0,
                'avg_response_time' => 0.0,
                'status_codes'      => [],
            ];
        }

        // Aggregate data in PHP
        foreach ($records as $record) {
            // 'w' => numeric representation of the day of the week (0 for Sunday, 6 for Saturday)
            $day = (int) $record->getAccessedAt()->format('w');

            ++$statistics[$day]['count'];

            if ($record->getResponseTime() !== null) {
                $statistics[$day]['sum_response_time'] += $record->getResponseTime();
            }

            if ($record->getStatusCode() !== null) {
                $code = $record->getStatusCode();
                if (!isset($statistics[$day]['status_codes'][$code])) {
                    $statistics[$day]['status_codes'][$code] = 0;
                }
                ++$statistics[$day]['status_codes'][$code];
            }
        }

        // Calculate averages and clean up structure
        foreach ($statistics as $day => $data) {
            if ($data['count'] > 0) {
                $statistics[$day]['avg_response_time'] = $data['sum_response_time'] / $data['count'];
            } else {
                $statistics[$day]['avg_response_time'] = 0.0;
            }

            unset($statistics[$day]['sum_response_time']);
        }

        ksort($statistics);

        return array_values($statistics);
    }

    /**
     * Get access statistics grouped by month.
     *
     * Grouping is done in PHP to remain database agnostic.
     *
     * @param string $env The environment
     * @param DateTimeImmutable|null $startDate Optional start date filter
     * @param DateTimeImmutable|null $endDate Optional end date filter
     * @param string|null $routeName Optional route name filter
     * @param int|null $statusCode Optional status code filter
     *
     * @return array<int, array{month: int, month_name: string, count: int, avg_response_time: float, status_codes: array<int, int>}> Statistics by month
     */
    public function getStatisticsByMonth(
        string $env,
        ?DateTimeImmutable $startDate = null,
        ?DateTimeImmutable $endDate = null,
        ?string $routeName = null,
        ?int $statusCode = null,
    ): array {
        $qb = $this->createQueryBuilder('r')
            ->select('partial r.{id, accessedAt, statusCode, responseTime}')
            ->join('r.routeData', 'rd')
            ->where('rd.env = :env')
            ->setParameter('env', $env)
            ->orderBy('r.accessedAt', 'ASC');

        if ($startDate !== null) {
            $qb->andWhere('r.accessedAt >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        if ($endDate !== null) {
            $qb->andWhere('r.accessedAt <= :endDate')
                ->setParameter('endDate', $endDate);
        }

        if ($routeName !== null) {
            $qb->andWhere('rd.name = :routeName')
                ->setParameter('routeName', $routeName);
        }

        if ($statusCode !== null) {
            $qb->andWhere('r.statusCode = :statusCode')
                ->setParameter('statusCode', $statusCode);
        }

        /** @var RouteDataRecord[] $records */
        $records = $qb->getQuery()->getResult();

        $monthNames = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
        ];

        // Initialize statistics for each month (1-12)
        $statistics = [];
        for ($month = 1; $month <= 12; ++$month) {
            $statistics[$month] = [
                'month'             => $month,
                'month_name'        => $monthNames[$month],
                'count'             => 0,
                'sum_response_time' => 0.0,
                'avg_response_time' => 0.0,
                'status_codes'      => [],
            ];
        }

        // Aggregate data in PHP
        foreach ($records as $record) {
            $month = (int) $record->getAccessedAt()->format('n'); // 1-12

            ++$statistics[$month]['count'];

            if ($record->getResponseTime() !== null) {
                $statistics[$month]['sum_response_time'] += $record->getResponseTime();
            }

            if ($record->getStatusCode() !== null) {
                $code = $record->getStatusCode();
                if (!isset($statistics[$month]['status_codes'][$code])) {
                    $statistics[$month]['status_codes'][$code] = 0;
                }
                ++$statistics[$month]['status_codes'][$code];
            }
        }

        // Calculate averages and clean up structure
        foreach ($statistics as $month => $data) {
            if ($data['count'] > 0) {
                $statistics[$month]['avg_response_time'] = $data['sum_response_time'] / $data['count'];
            } else {
                $statistics[$month]['avg_response_time'] = 0.0;
            }

            unset($statistics[$month]['sum_response_time']);
        }

        ksort($statistics);

        return array_values($statistics);
    }

    /**
     * Get heatmap data: access count by day of week and hour.
     *
     * Grouping is done in PHP to remain database agnostic.
     *
     * @param string $env The environment
     * @param DateTimeImmutable|null $startDate Optional start date filter
     * @param DateTimeImmutable|null $endDate Optional end date filter
     * @param string|null $routeName Optional route name filter
     * @param int|null $statusCode Optional status code filter
     *
     * @return array<int, array<int, int>> Heatmap data [day_of_week][hour] => count
     */
    public function getHeatmapData(
        string $env,
        ?DateTimeImmutable $startDate = null,
        ?DateTimeImmutable $endDate = null,
        ?string $routeName = null,
        ?int $statusCode = null,
    ): array {
        $qb = $this->createQueryBuilder('r')
            ->select('partial r.{id, accessedAt, statusCode, responseTime}')
            ->join('r.routeData', 'rd')
            ->where('rd.env = :env')
            ->setParameter('env', $env)
            ->orderBy('r.accessedAt', 'ASC');

        if ($startDate !== null) {
            $qb->andWhere('r.accessedAt >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        if ($endDate !== null) {
            $qb->andWhere('r.accessedAt <= :endDate')
                ->setParameter('endDate', $endDate);
        }

        if ($routeName !== null) {
            $qb->andWhere('rd.name = :routeName')
                ->setParameter('routeName', $routeName);
        }

        if ($statusCode !== null) {
            $qb->andWhere('r.statusCode = :statusCode')
                ->setParameter('statusCode', $statusCode);
        }

        /** @var RouteDataRecord[] $records */
        $records = $qb->getQuery()->getResult();

        // Initialize heatmap with zeros
        $heatmap = [];
        for ($day = 0; $day < 7; ++$day) {
            $heatmap[$day] = [];
            for ($hour = 0; $hour < 24; ++$hour) {
                $heatmap[$day][$hour] = 0;
            }
        }

        // Fill in actual data
        foreach ($records as $record) {
            $day  = (int) $record->getAccessedAt()->format('w'); // 0-6
            $hour = (int) $record->getAccessedAt()->format('G'); // 0-23
            ++$heatmap[$day][$hour];
        }

        return $heatmap;
    }

    /**
     * Get paginated access records.
     *
     * @param string $env The environment
     * @param int $page Page number (1-based)
     * @param int $perPage Records per page
     * @param DateTimeImmutable|null $startDate Optional start date filter
     * @param DateTimeImmutable|null $endDate Optional end date filter
     * @param string|null $routeName Optional route name filter
     * @param int|null $statusCode Optional status code filter
     * @param float|null $minQueryTime Optional min query time (s)
     * @param float|null $maxQueryTime Optional max query time (s)
     * @param int|null $minMemoryUsage Optional min memory (bytes)
     * @param int|null $maxMemoryUsage Optional max memory (bytes)
     * @param string|null $referer Optional referer filter (partial match)
     * @param string|null $user Optional user filter (partial match on user_identifier or user_id)
     *
     * @return array{records: array, total: int, page: int, per_page: int, total_pages: int}
     */
    public function getPaginatedRecords(
        string $env,
        int $page = 1,
        int $perPage = 50,
        ?DateTimeImmutable $startDate = null,
        ?DateTimeImmutable $endDate = null,
        ?string $routeName = null,
        ?int $statusCode = null,
        ?float $minQueryTime = null,
        ?float $maxQueryTime = null,
        ?int $minMemoryUsage = null,
        ?int $maxMemoryUsage = null,
        ?string $referer = null,
        ?string $user = null,
        ?string $sortBy = null,
        string $order = 'DESC',
    ): array {
        $sortFieldMap = [
            'accessed_at'   => 'r.accessedAt',
            'route'         => 'rd.name',
            'path'          => 'r.routePath',
            'status_code'   => 'r.statusCode',
            'response_time' => 'r.responseTime',
            'total_queries' => 'r.totalQueries',
            'query_time'    => 'r.queryTime',
            'memory_usage'  => 'r.memoryUsage',
        ];
        $sortField = $sortFieldMap[$sortBy ?? ''] ?? 'r.accessedAt';
        $sortOrder = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        $qb = $this->createQueryBuilder('r')
            ->join('r.routeData', 'rd')
            ->addSelect('rd')
            ->where('rd.env = :env')
            ->setParameter('env', $env)
            ->orderBy($sortField, $sortOrder);

        $this->applyRecordFilters($qb, $startDate, $endDate, $routeName, $statusCode, $minQueryTime, $maxQueryTime, $minMemoryUsage, $maxMemoryUsage, $referer, $user);

        // Get total count
        $totalQb = clone $qb;
        $total   = (int) $totalQb->select('COUNT(r.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Get paginated results
        $offset  = ($page - 1) * $perPage;
        $records = $qb->setFirstResult($offset)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        $totalPages = (int) ceil($total / $perPage);

        return [
            'records'     => $records,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * Get access records for export (same filters as getPaginatedRecords, optional limit).
     *
     * @param string $env The environment
     * @param DateTimeImmutable|null $startDate Optional start date filter
     * @param DateTimeImmutable|null $endDate Optional end date filter
     * @param string|null $routeName Optional route name filter
     * @param int|null $statusCode Optional status code filter
     * @param float|null $minQueryTime Optional min query time (s)
     * @param float|null $maxQueryTime Optional max query time (s)
     * @param int|null $minMemoryUsage Optional min memory (bytes)
     * @param int|null $maxMemoryUsage Optional max memory (bytes)
     * @param string|null $referer Optional referer filter (partial match)
     * @param string|null $user Optional user filter (partial match)
     * @param int $limit Maximum records to return (default 50_000)
     *
     * @return array{records: RouteDataRecord[], total: int}
     */
    public function getRecordsForExport(
        string $env,
        ?DateTimeImmutable $startDate = null,
        ?DateTimeImmutable $endDate = null,
        ?string $routeName = null,
        ?int $statusCode = null,
        ?float $minQueryTime = null,
        ?float $maxQueryTime = null,
        ?int $minMemoryUsage = null,
        ?int $maxMemoryUsage = null,
        ?string $referer = null,
        ?string $user = null,
        int $limit = 50_000,
    ): array {
        $qb = $this->createQueryBuilder('r')
            ->join('r.routeData', 'rd')
            ->addSelect('rd')
            ->where('rd.env = :env')
            ->setParameter('env', $env)
            ->orderBy('r.accessedAt', 'DESC');

        $this->applyRecordFilters($qb, $startDate, $endDate, $routeName, $statusCode, $minQueryTime, $maxQueryTime, $minMemoryUsage, $maxMemoryUsage, $referer, $user);

        $countQb = clone $qb;
        $total   = (int) $countQb->select('COUNT(r.id)')->getQuery()->getSingleScalarResult();

        $qb->setMaxResults($limit);
        /** @var RouteDataRecord[] $records */
        $records = $qb->getQuery()->getResult();

        return ['records' => $records, 'total' => $total];
    }

    /**
     * Get aggregates per route_data_id from records (for normalized RouteData without metric columns).
     *
     * @param int[] $routeDataIds RouteData entity IDs
     *
     * @return array<int, array{request_time: float|null, total_queries: int|null, query_time: float|null, memory_usage: int|null, access_count: int, status_codes: array<int, int>}> Map route_data_id => aggregates
     */
    public function getAggregatesForRouteDataIds(array $routeDataIds): array
    {
        if (empty($routeDataIds)) {
            return [];
        }

        $conn           = $this->getEntityManager()->getConnection();
        $table          = $this->getClassMetadata()->getTableName();
        $platform       = $conn->getDatabasePlatform();
        $idsPlaceholder = implode(',', array_fill(0, count($routeDataIds), '?'));

        $sql = 'SELECT route_data_id, ' .
            'MAX(response_time) AS request_time, MAX(total_queries) AS total_queries, ' .
            'MAX(query_time) AS query_time, MAX(memory_usage) AS memory_usage, COUNT(*) AS access_count ' .
            "FROM {$platform->quoteIdentifier($table)} WHERE route_data_id IN ({$idsPlaceholder}) GROUP BY route_data_id";
        $result = $conn->executeQuery($sql, $routeDataIds, array_fill(0, count($routeDataIds), ParameterType::INTEGER));
        $rows   = $result->fetchAllAssociative();

        $aggregates = [];
        foreach ($rows as $row) {
            $id              = (int) $row['route_data_id'];
            $aggregates[$id] = [
                'request_time'  => $row['request_time'] !== null ? (float) $row['request_time'] : null,
                'total_queries' => $row['total_queries'] !== null ? (int) $row['total_queries'] : null,
                'query_time'    => $row['query_time'] !== null ? (float) $row['query_time'] : null,
                'memory_usage'  => $row['memory_usage'] !== null ? (int) $row['memory_usage'] : null,
                'access_count'  => (int) $row['access_count'],
                'status_codes'  => [],
            ];
        }

        $sqlStatus = "SELECT route_data_id, status_code, COUNT(*) AS cnt FROM {$platform->quoteIdentifier($table)} " .
            "WHERE route_data_id IN ({$idsPlaceholder}) AND status_code IS NOT NULL GROUP BY route_data_id, status_code";
        $resultStatus = $conn->executeQuery($sqlStatus, $routeDataIds, array_fill(0, count($routeDataIds), ParameterType::INTEGER));
        foreach ($resultStatus->fetchAllAssociative() as $row) {
            $id = (int) $row['route_data_id'];
            if (isset($aggregates[$id])) {
                $aggregates[$id]['status_codes'][(int) $row['status_code']] = (int) $row['cnt'];
            }
        }

        return $aggregates;
    }
}
