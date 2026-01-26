<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\EventSubscriber;

/**
 * Custom SQL logger for tracking query count and execution time.
 * 
 * Note: In DBAL 3.x, SQLLogger was removed. This class tracks queries
 * using a simple counter and timer approach that works with DBAL 3.x.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
class QueryLogger
{
    /**
     * Total number of queries executed.
     *
     * @var int
     */
    private int $queryCount = 0;

    /**
     * Total execution time for all queries in seconds.
     *
     * @var float
     */
    private float $totalQueryTime = 0.0;

    /**
     * Start times for queries being tracked.
     *
     * @var array<string, float>
     */
    private array $queryStartTimes = [];

    /**
     * Record the start of a query execution.
     *
     * @param string $queryId Unique identifier for the query
     * @return void
     */
    public function startQuery(string $queryId): void
    {
        $this->queryStartTimes[$queryId] = microtime(true);
    }

    /**
     * Record the end of a query execution.
     *
     * Calculates the execution time and updates the total query time and count.
     *
     * @param string $queryId Unique identifier for the query
     * @return void
     */
    public function stopQuery(string $queryId): void
    {
        if (!isset($this->queryStartTimes[$queryId])) {
            return;
        }

        $this->queryCount++;
        $this->totalQueryTime += microtime(true) - $this->queryStartTimes[$queryId];
        unset($this->queryStartTimes[$queryId]);
    }

    /**
     * Get the total number of queries executed.
     *
     * @return int The query count
     */
    public function getQueryCount(): int
    {
        return $this->queryCount;
    }

    /**
     * Get the total execution time for all queries.
     *
     * @return float The total query time in seconds
     */
    public function getTotalQueryTime(): float
    {
        return $this->totalQueryTime;
    }

    /**
     * Reset all query tracking metrics.
     *
     * Clears the query count, total time, and start times.
     *
     * @return void
     */
    public function reset(): void
    {
        $this->queryCount = 0;
        $this->totalQueryTime = 0.0;
        $this->queryStartTimes = [];
    }
}
