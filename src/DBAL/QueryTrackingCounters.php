<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\DBAL;

use Symfony\Contracts\Service\ResetInterface;

/**
 * Request-scoped counters for DBAL query tracking (FrankenPHP worker-safe).
 *
 * Shared as a single DI service and cleared via {@see ResetInterface} / kernel.reset.
 */
final class QueryTrackingCounters implements ResetInterface
{
    private int $queryCount = 0;

    private float $totalQueryTime = 0.0;

    /**
     * @var array<string, float>
     */
    private array $queryStartTimes = [];

    private int $nextQueryId = 0;

    public function getQueryCount(): int
    {
        return $this->queryCount;
    }

    public function getTotalQueryTime(): float
    {
        return $this->totalQueryTime;
    }

    public function reset(): void
    {
        $this->queryCount      = 0;
        $this->totalQueryTime  = 0.0;
        $this->queryStartTimes = [];
        $this->nextQueryId     = 0;
    }

    public function nextQueryId(object $connection): string
    {
        return spl_object_hash($connection) . '_' . (++$this->nextQueryId);
    }

    public function startQuery(string $queryId): void
    {
        $this->queryStartTimes[$queryId] = microtime(true);
    }

    public function stopQuery(string $queryId): void
    {
        if (!isset($this->queryStartTimes[$queryId])) {
            return;
        }

        ++$this->queryCount;
        $this->totalQueryTime += microtime(true) - $this->queryStartTimes[$queryId];
        unset($this->queryStartTimes[$queryId]);
    }
}
