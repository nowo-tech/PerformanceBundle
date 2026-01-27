<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Message;

/**
 * Message for recording performance metrics asynchronously.
 *
 * This message is dispatched to the message queue to record
 * performance metrics in the background.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
final class RecordMetricsMessage
{
    /**
     * Constructor.
     *
     * @param string      $routeName    The route name
     * @param string      $env          The environment
     * @param float|null  $requestTime  Request execution time in seconds
     * @param int|null    $totalQueries Total number of database queries
     * @param float|null  $queryTime    Total query execution time in seconds
     * @param array|null  $params       Route parameters
     * @param int|null    $memoryUsage  Peak memory usage in bytes
     * @param string|null $httpMethod   HTTP method (GET, POST, PUT, DELETE, etc.)
     */
    public function __construct(
        private readonly string $routeName,
        private readonly string $env,
        private readonly ?float $requestTime = null,
        private readonly ?int $totalQueries = null,
        private readonly ?float $queryTime = null,
        private readonly ?array $params = null,
        private readonly ?int $memoryUsage = null,
        private readonly ?string $httpMethod = null,
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

    public function getTotalQueries(): ?int
    {
        return $this->totalQueries;
    }

    public function getQueryTime(): ?float
    {
        return $this->queryTime;
    }

    public function getParams(): ?array
    {
        return $this->params;
    }

    public function getMemoryUsage(): ?int
    {
        return $this->memoryUsage;
    }

    public function getHttpMethod(): ?string
    {
        return $this->httpMethod;
    }
}
