<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Model;

/**
 * DTO for record/access statistics filter form (GET).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @property \DateTimeImmutable|null $startDate      Filter start date (inclusive).
 * @property \DateTimeImmutable|null $endDate        Filter end date (inclusive).
 * @property string|null             $env            Environment (e.g. dev, prod).
 * @property string|null             $route          Route name filter (empty = all routes).
 * @property int|null                $statusCode     HTTP status code filter (e.g. 200, 404).
 * @property float|null              $minQueryTime   Minimum query time in seconds (records with query_time >= this).
 * @property float|null              $maxQueryTime   Maximum query time in seconds (records with query_time <= this).
 * @property int|null                $minMemoryUsage Minimum memory usage in bytes (records with memory_usage >= this).
 * @property int|null                $maxMemoryUsage Maximum memory usage in bytes (records with memory_usage <= this).
 */
final class RecordFilters
{
    /**
     * @param \DateTimeImmutable|null $startDate      filter start date (inclusive)
     * @param \DateTimeImmutable|null $endDate        filter end date (inclusive)
     * @param string|null             $env            Environment (e.g. dev, prod).
     * @param string|null             $route          route name filter (empty = all routes)
     * @param int|null                $statusCode     HTTP status code filter (e.g. 200, 404).
     * @param float|null              $minQueryTime   minimum query time in seconds (filter records with query_time >= this)
     * @param float|null              $maxQueryTime   maximum query time in seconds (filter records with query_time <= this)
     * @param int|null                $minMemoryUsage minimum memory usage in bytes (filter records with memory_usage >= this)
     * @param int|null                $maxMemoryUsage maximum memory usage in bytes (filter records with memory_usage <= this)
     */
    public function __construct(
        public ?\DateTimeImmutable $startDate = null,
        public ?\DateTimeImmutable $endDate = null,
        public ?string $env = null,
        public ?string $route = null,
        public ?int $statusCode = null,
        public ?float $minQueryTime = null,
        public ?float $maxQueryTime = null,
        public ?int $minMemoryUsage = null,
        public ?int $maxMemoryUsage = null,
    ) {
    }
}
