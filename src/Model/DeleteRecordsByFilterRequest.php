<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Model;

/**
 * DTO for delete-records-by-filter form (POST).
 *
 * Carries current filter state as hidden fields so the delete action applies the same criteria.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @property string      $env             Environment (e.g. dev, prod).
 * @property string      $from            Origin page identifier ('access_records' or 'access_statistics').
 * @property string|null $startDate       Filter start date (Y-m-d\TH:i format).
 * @property string|null $endDate         Filter end date (Y-m-d\TH:i format).
 * @property string|null $route           Route name filter.
 * @property string|null $statusCode      HTTP status code filter (string representation).
 * @property string|null $minQueryTime    Minimum query time (seconds, string).
 * @property string|null $maxQueryTime    Maximum query time (seconds, string).
 * @property string|null $minMemoryUsage  Minimum memory in bytes (string).
 * @property string|null $maxMemoryUsage  Maximum memory in bytes (string).
 */
final class DeleteRecordsByFilterRequest
{
    /**
     * @param string      $env             Environment (e.g. dev, prod).
     * @param string      $from            Origin page ('access_records' or 'access_statistics').
     * @param string|null $startDate       Filter start date (Y-m-d\TH:i).
     * @param string|null $endDate         Filter end date (Y-m-d\TH:i).
     * @param string|null $route           Route name filter.
     * @param string|null $statusCode      HTTP status code (string).
     * @param string|null $minQueryTime    Min query time in seconds (string).
     * @param string|null $maxQueryTime    Max query time in seconds (string).
     * @param string|null $minMemoryUsage  Min memory usage in bytes (string).
     * @param string|null $maxMemoryUsage  Max memory usage in bytes (string).
     */
    public function __construct(
        public string $env = '',
        public string $from = 'access_records',
        public ?string $startDate = null,
        public ?string $endDate = null,
        public ?string $route = null,
        public ?string $statusCode = null,
        public ?string $minQueryTime = null,
        public ?string $maxQueryTime = null,
        public ?string $minMemoryUsage = null,
        public ?string $maxMemoryUsage = null,
    ) {
    }
}
