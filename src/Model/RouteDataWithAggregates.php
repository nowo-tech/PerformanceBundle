<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Model;

use DateTimeImmutable;
use Nowo\PerformanceBundle\Entity\RouteData;

/**
 * Route data with aggregates computed from RouteDataRecord.
 *
 * Exposes the same metric getters as RouteData had before normalization,
 * reading from aggregates (max request_time, total_queries, access_count, status_codes, etc.).
 *
 * @see docs/ENTITY_NORMALIZATION_PLAN.md
 */
final class RouteDataWithAggregates
{
    /**
     * @param array{request_time: float|null, total_queries: int|null, query_time: float|null, memory_usage: int|null, access_count: int, status_codes: array<int, int>} $aggregates
     */
    public function __construct(
        private readonly RouteData $routeData,
        private readonly array $aggregates,
    ) {
    }

    public function getRouteData(): RouteData
    {
        return $this->routeData;
    }

    /** Delegate to RouteData. */
    public function getId(): ?int
    {
        return $this->routeData->getId();
    }

    public function getEnv(): ?string
    {
        return $this->routeData->getEnv();
    }

    public function getName(): ?string
    {
        return $this->routeData->getName();
    }

    public function getHttpMethod(): ?string
    {
        return $this->routeData->getHttpMethod();
    }

    public function getParams(): ?array
    {
        return $this->routeData->getParams();
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->routeData->getCreatedAt();
    }

    public function getLastAccessedAt(): ?DateTimeImmutable
    {
        return $this->routeData->getLastAccessedAt();
    }

    public function isReviewed(): bool
    {
        return $this->routeData->isReviewed();
    }

    public function getReviewedAt(): ?DateTimeImmutable
    {
        return $this->routeData->getReviewedAt();
    }

    public function getQueriesImproved(): ?bool
    {
        return $this->routeData->getQueriesImproved();
    }

    public function getTimeImproved(): ?bool
    {
        return $this->routeData->getTimeImproved();
    }

    public function getReviewedBy(): ?string
    {
        return $this->routeData->getReviewedBy();
    }

    public function getAccessRecords(): \Doctrine\Common\Collections\Collection
    {
        return $this->routeData->getAccessRecords();
    }

    /** From aggregates (max response_time from records). */
    public function getRequestTime(): ?float
    {
        return $this->aggregates['request_time'] ?? null;
    }

    /** From aggregates (max total_queries from records). */
    public function getTotalQueries(): ?int
    {
        return $this->aggregates['total_queries'] ?? null;
    }

    /** From aggregates (max query_time from records). */
    public function getQueryTime(): ?float
    {
        return $this->aggregates['query_time'] ?? null;
    }

    /** From aggregates (max memory_usage from records). */
    public function getMemoryUsage(): ?int
    {
        return $this->aggregates['memory_usage'] ?? null;
    }

    /** From aggregates (count of records). */
    public function getAccessCount(): int
    {
        return $this->aggregates['access_count'] ?? 0;
    }

    /** From aggregates (counts per status code from records). */
    public function getStatusCodes(): ?array
    {
        $codes = $this->aggregates['status_codes'] ?? [];

        return empty($codes) ? null : $codes;
    }

    public function getStatusCodeCount(int $statusCode): int
    {
        $codes = $this->aggregates['status_codes'] ?? [];

        return $codes[$statusCode] ?? 0;
    }

    public function getStatusCodeRatio(int $statusCode): float
    {
        $codes = $this->aggregates['status_codes'] ?? [];
        if (empty($codes)) {
            return 0.0;
        }
        $total = array_sum($codes);
        if ($total === 0) {
            return 0.0;
        }

        return (($codes[$statusCode] ?? 0) / $total) * 100.0;
    }

    public function getTotalResponses(): int
    {
        $codes = $this->aggregates['status_codes'] ?? [];

        return empty($codes) ? 0 : (int) array_sum($codes);
    }

    public function __toString(): string
    {
        return $this->routeData->__toString();
    }
}
