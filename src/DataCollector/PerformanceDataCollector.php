<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\DataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Throwable;

/**
 * Data collector for Performance Bundle.
 * Collects performance metrics and displays them in the Web Profiler toolbar.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
class PerformanceDataCollector extends DataCollector
{
    /**
     * Request start time for timing calculation.
     *
     * @var float|null
     */
    private ?float $startTime = null;

    /**
     * Total number of database queries executed.
     *
     * @var int|null
     */
    private ?int $queryCount = null;

    /**
     * Total database query execution time in seconds.
     *
     * @var float|null
     */
    private ?float $queryTime = null;

    /**
     * Current route name being tracked.
     *
     * @var string|null
     */
    private ?string $routeName = null;

    /**
     * Whether the collector is enabled for the current request.
     *
     * @var bool
     */
    private bool $enabled = false;

    /**
     * Constructor.
     */
    public function __construct()
    {
    }

    /**
     * Set the start time for request tracking.
     *
     * @param float $startTime The start time as microtime(true)
     * @return void
     */
    public function setStartTime(float $startTime): void
    {
        $this->startTime = $startTime;
    }

    /**
     * Set query metrics.
     *
     * @param int $queryCount The total number of queries
     * @param float $queryTime The total query execution time in seconds
     * @return void
     */
    public function setQueryMetrics(int $queryCount, float $queryTime): void
    {
        $this->queryCount = $queryCount;
        $this->queryTime = $queryTime;
    }

    /**
     * Set the route name.
     *
     * @param string|null $routeName The route name
     * @return void
     */
    public function setRouteName(?string $routeName): void
    {
        $this->routeName = $routeName;
    }

    /**
     * Enable or disable the collector.
     *
     * @param bool $enabled Whether the collector is enabled
     * @return void
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    /**
     * Set the request time.
     *
     * @param float $requestTime The request time in seconds
     * @return void
     */
    public function setRequestTime(float $requestTime): void
    {
        // Request time is calculated in collect() method from startTime
    }

    /**
     * Set the query count.
     *
     * @param int $queryCount The query count
     * @return void
     */
    public function setQueryCount(int $queryCount): void
    {
        $this->queryCount = $queryCount;
    }

    /**
     * Set the query time.
     *
     * @param float $queryTime The query time in seconds
     * @return void
     */
    public function setQueryTime(float $queryTime): void
    {
        $this->queryTime = $queryTime;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, ?Throwable $exception = null): void
    {
        $requestTime = null;
        if ($this->startTime !== null) {
            $requestTime = microtime(true) - $this->startTime;
        }

        $this->data = [
            'enabled' => $this->enabled,
            'route_name' => $this->routeName ?? $request->attributes->get('_route'),
            'request_time' => $requestTime,
            'query_count' => $this->queryCount ?? 0,
            'query_time' => $this->queryTime ?? 0.0,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function reset(): void
    {
        $this->data = [];
        $this->startTime = null;
        $this->queryCount = null;
        $this->queryTime = null;
        $this->routeName = null;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'performance';
    }

    /**
     * Check if the collector is enabled.
     *
     * @return bool True if enabled, false otherwise
     */
    public function isEnabled(): bool
    {
        return $this->data['enabled'] ?? false;
    }

    /**
     * Get the route name.
     *
     * @return string|null The route name or null if not available
     */
    public function getRouteName(): ?string
    {
        return $this->data['route_name'] ?? null;
    }

    /**
     * Get the request time in milliseconds.
     *
     * @return float|null The request time in milliseconds or null if not available
     */
    public function getRequestTime(): ?float
    {
        return $this->data['request_time'] !== null ? $this->data['request_time'] * 1000 : null;
    }

    /**
     * Get the query count.
     *
     * @return int The total number of queries executed
     */
    public function getQueryCount(): int
    {
        return $this->data['query_count'] ?? 0;
    }

    /**
     * Get the total query time in milliseconds.
     *
     * @return float The total query execution time in milliseconds
     */
    public function getQueryTime(): float
    {
        return ($this->data['query_time'] ?? 0.0) * 1000;
    }

    /**
     * Get formatted request time string.
     *
     * Formats the request time in a human-readable format (ms or s).
     *
     * @return string Formatted time string (e.g., "123.45 ms" or "1.23 s")
     */
    public function getFormattedRequestTime(): string
    {
        $time = $this->getRequestTime();
        if ($time === null) {
            return 'N/A';
        }

        return match (true) {
            $time < 1 => sprintf('%.2f ms', $time),
            $time < 1000 => sprintf('%.0f ms', $time),
            default => sprintf('%.2f s', $time / 1000),
        };
    }

    /**
     * Get formatted query time string.
     *
     * Formats the query time in a human-readable format (ms or s).
     *
     * @return string Formatted time string (e.g., "45.67 ms" or "0.12 s")
     */
    public function getFormattedQueryTime(): string
    {
        $time = $this->getQueryTime();
        return match (true) {
            $time < 1 => sprintf('%.2f ms', $time),
            $time < 1000 => sprintf('%.0f ms', $time),
            default => sprintf('%.2f s', $time / 1000),
        };
    }
}
