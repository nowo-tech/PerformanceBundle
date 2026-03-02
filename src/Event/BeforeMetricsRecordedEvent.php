<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched before performance metrics are recorded.
 *
 * This event allows listeners to modify metrics before they are saved to the database.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class BeforeMetricsRecordedEvent extends Event
{
    /**
     * Creates a new instance.
     *
     * @param string $routeName The route name
     * @param string $env The environment
     * @param float|null $requestTime Request execution time in seconds
     * @param int|null $totalQueries Total number of database queries
     * @param float|null $queryTime Total query execution time in seconds
     * @param array|null $params Route parameters
     * @param int|null $memoryUsage Peak memory usage in bytes
     */
    public function __construct(
        private string $routeName,
        private string $env,
        private ?float $requestTime = null,
        private ?int $totalQueries = null,
        private ?float $queryTime = null,
        private ?array $params = null,
        private ?int $memoryUsage = null,
    ) {
    }

    public function getRouteName(): string
    {
        return $this->routeName;
    }

    public function getEnv(): string
    {
        return $this->env;
    }

    public function getRequestTime(): ?float
    {
        return $this->requestTime;
    }

    public function setRequestTime(?float $requestTime): void
    {
        $this->requestTime = $requestTime;
    }

    public function getTotalQueries(): ?int
    {
        return $this->totalQueries;
    }

    public function setTotalQueries(?int $totalQueries): void
    {
        $this->totalQueries = $totalQueries;
    }

    public function getQueryTime(): ?float
    {
        return $this->queryTime;
    }

    public function setQueryTime(?float $queryTime): void
    {
        $this->queryTime = $queryTime;
    }

    public function getParams(): ?array
    {
        return $this->params;
    }

    public function setParams(?array $params): void
    {
        $this->params = $params;
    }

    public function getMemoryUsage(): ?int
    {
        return $this->memoryUsage;
    }

    public function setMemoryUsage(?int $memoryUsage): void
    {
        $this->memoryUsage = $memoryUsage;
    }
}
