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
 * @copyright 2026 Nowo.tech
 */
final class RecordMetricsMessage
{
    /**
     * Creates a new record metrics message for async processing.
     *
     * @param string      $routeName    The route name
     * @param string      $env          The environment (e.g. dev, prod)
     * @param float|null  $requestTime  Request execution time in seconds
     * @param int|null    $totalQueries Total number of database queries
     * @param float|null  $queryTime    Total query execution time in seconds
     * @param array|null  $params       Route parameters
     * @param int|null    $memoryUsage  Peak memory usage in bytes
     * @param string|null $httpMethod   HTTP method (GET, POST, PUT, DELETE, etc.)
     * @param string|null $requestId      Unique request ID for deduplication of access records
     * @param string|null $referer        HTTP Referer header (page that linked to this request)
     * @param string|null $userIdentifier Logged-in user identifier (e.g. username, email)
     * @param string|null $userId         Logged-in user ID (stringified, if available)
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
        private readonly ?string $requestId = null,
        private readonly ?string $referer = null,
        private readonly ?string $userIdentifier = null,
        private readonly ?string $userId = null,
    ) {
    }

    /** @return string The route name */
    public function getRouteName(): string
    {
        return $this->routeName;
    }

    /** @return string The environment */
    public function getEnv(): string
    {
        return $this->env;
    }

    /** @return float|null Request execution time in seconds */
    public function getRequestTime(): ?float
    {
        return $this->requestTime;
    }

    /** @return int|null Total number of database queries */
    public function getTotalQueries(): ?int
    {
        return $this->totalQueries;
    }

    /** @return float|null Total query execution time in seconds */
    public function getQueryTime(): ?float
    {
        return $this->queryTime;
    }

    /** @return array|null Route parameters */
    public function getParams(): ?array
    {
        return $this->params;
    }

    /** @return int|null Peak memory usage in bytes */
    public function getMemoryUsage(): ?int
    {
        return $this->memoryUsage;
    }

    /** @return string|null HTTP method */
    public function getHttpMethod(): ?string
    {
        return $this->httpMethod;
    }

    /** @return string|null Unique request ID for deduplication */
    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    /** @return string|null HTTP Referer header */
    public function getReferer(): ?string
    {
        return $this->referer;
    }

    /** @return string|null Logged-in user identifier (username, email, etc.) */
    public function getUserIdentifier(): ?string
    {
        return $this->userIdentifier;
    }

    /** @return string|null Logged-in user ID (stringified) */
    public function getUserId(): ?string
    {
        return $this->userId;
    }
}
