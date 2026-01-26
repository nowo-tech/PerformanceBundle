<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\DataCollector;

use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use Nowo\PerformanceBundle\Service\TableStatusChecker;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\KernelInterface;

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
     */
    private ?float $startTime = null;

    /**
     * Total number of database queries executed.
     */
    private ?int $queryCount = null;

    /**
     * Total database query execution time in seconds.
     */
    private ?float $queryTime = null;

    /**
     * Current route name being tracked.
     */
    private ?string $routeName = null;

    /**
     * Whether the collector is enabled for the current request.
     */
    private bool $enabled = false;

    /**
     * Whether async mode is enabled.
     */
    private bool $async = false;

    /**
     * Route data repository (optional, for ranking information).
     */
    private ?RouteDataRepository $repository = null;

    /**
     * Kernel interface (optional, for environment detection).
     */
    private ?KernelInterface $kernel = null;

    /**
     * Table status checker (optional, for table existence verification).
     */
    private ?TableStatusChecker $tableStatusChecker = null;

    /**
     * Constructor.
     *
     * @param RouteDataRepository|null $repository         The route data repository (optional)
     * @param KernelInterface|null     $kernel             The kernel interface (optional)
     * @param TableStatusChecker|null  $tableStatusChecker The table status checker (optional)
     */
    public function __construct(
        ?RouteDataRepository $repository = null,
        ?KernelInterface $kernel = null,
        ?TableStatusChecker $tableStatusChecker = null,
    ) {
        $this->repository = $repository;
        $this->kernel = $kernel;
        $this->tableStatusChecker = $tableStatusChecker;
    }

    /**
     * Set the start time for request tracking.
     *
     * @param float $startTime The start time as microtime(true)
     */
    public function setStartTime(float $startTime): void
    {
        $this->startTime = $startTime;
    }

    /**
     * Set query metrics.
     *
     * @param int   $queryCount The total number of queries
     * @param float $queryTime  The total query execution time in seconds
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
     */
    public function setRouteName(?string $routeName): void
    {
        $this->routeName = $routeName;
    }

    /**
     * Enable or disable the collector.
     *
     * @param bool $enabled Whether the collector is enabled
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    /**
     * Set async mode status.
     *
     * @param bool $async Whether async mode is enabled
     */
    public function setAsync(bool $async): void
    {
        $this->async = $async;
    }

    /**
     * Set the request time.
     *
     * @param float $requestTime The request time in seconds
     */
    public function setRequestTime(float $requestTime): void
    {
        // Request time is calculated in collect() method from startTime
    }

    /**
     * Set the query count.
     *
     * @param int $queryCount The query count
     */
    public function setQueryCount(int $queryCount): void
    {
        $this->queryCount = $queryCount;
    }

    /**
     * Set the query time.
     *
     * @param float $queryTime The query time in seconds
     */
    public function setQueryTime(float $queryTime): void
    {
        $this->queryTime = $queryTime;
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $requestTime = null;
        if (null !== $this->startTime) {
            $requestTime = microtime(true) - $this->startTime;
        }

        // Try to get query metrics directly from QueryTrackingMiddleware if not already set
        // This ensures we have the latest values even if collect() is called before onKernelTerminate
        $queryCount = $this->queryCount;
        $queryTime = $this->queryTime;

        if (null === $queryCount || null === $queryTime) {
            try {
                $queryCount = \Nowo\PerformanceBundle\DBAL\QueryTrackingMiddleware::getQueryCount();
                $queryTime = \Nowo\PerformanceBundle\DBAL\QueryTrackingMiddleware::getTotalQueryTime();
            } catch (\Exception $e) {
                // Fallback to stored values or 0
                $queryCount = $queryCount ?? 0;
                $queryTime = $queryTime ?? 0.0;
            }
        }

        $routeName = $this->routeName ?? $request->attributes->get('_route');
        $env = $this->kernel?->getEnvironment() ?? 'dev';

        // Check if table exists
        $tableExists = false;
        $tableName = null;
        if (null !== $this->tableStatusChecker) {
            try {
                $tableExists = $this->tableStatusChecker->tableExists();
                $tableName = $this->tableStatusChecker->getTableName();
            } catch (\Exception $e) {
                // Silently fail if table check fails
            }
        }

        // Get ranking and access count information if repository is available
        $accessCount = null;
        $rankingByRequestTime = null;
        $rankingByQueryCount = null;
        $totalRoutes = null;

        if (null !== $this->repository && null !== $routeName) {
            try {
                $routeData = $this->repository->findByRouteAndEnv($routeName, $env);
                if (null !== $routeData) {
                    $accessCount = $routeData->getAccessCount();
                    // Pass the RouteData entity directly to avoid duplicate queries
                    $rankingByRequestTime = $this->repository->getRankingByRequestTime($routeData);
                    $rankingByQueryCount = $this->repository->getRankingByQueryCount($routeData);
                    $totalRoutes = $this->repository->getTotalRoutesCount($env);
                }
            } catch (\Exception $e) {
                // Silently fail if repository query fails (e.g., table doesn't exist yet)
            }
        }

        $this->data = [
            'enabled' => $this->enabled,
            'route_name' => $routeName,
            'request_time' => $requestTime,
            'query_count' => $queryCount,
            'query_time' => $queryTime,
            'access_count' => $accessCount,
            'ranking_by_request_time' => $rankingByRequestTime,
            'ranking_by_query_count' => $rankingByQueryCount,
            'total_routes' => $totalRoutes,
            'async' => $this->async,
            'table_exists' => $tableExists,
            'table_name' => $tableName,
        ];
    }

    public function reset(): void
    {
        $this->data = [];
        $this->startTime = null;
        $this->queryCount = null;
        $this->queryTime = null;
        $this->routeName = null;
        $this->async = false;
    }

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
        return null !== $this->data['request_time'] ? $this->data['request_time'] * 1000 : null;
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
        if (null === $time) {
            return 'N/A';
        }

        return match (true) {
            $time < 1 => \sprintf('%.2f ms', $time),
            $time < 1000 => \sprintf('%.0f ms', $time),
            default => \sprintf('%.2f s', $time / 1000),
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
            $time < 1 => \sprintf('%.2f ms', $time),
            $time < 1000 => \sprintf('%.0f ms', $time),
            default => \sprintf('%.2f s', $time / 1000),
        };
    }

    /**
     * Get the access count for the current route.
     *
     * @return int|null The access count or null if not available
     */
    public function getAccessCount(): ?int
    {
        return $this->data['access_count'] ?? null;
    }

    /**
     * Get the ranking position by request time.
     *
     * @return int|null The ranking position (1-based) or null if not available
     */
    public function getRankingByRequestTime(): ?int
    {
        return $this->data['ranking_by_request_time'] ?? null;
    }

    /**
     * Get the ranking position by query count.
     *
     * @return int|null The ranking position (1-based) or null if not available
     */
    public function getRankingByQueryCount(): ?int
    {
        return $this->data['ranking_by_query_count'] ?? null;
    }

    /**
     * Get the total number of routes in the environment.
     *
     * @return int|null The total number of routes or null if not available
     */
    public function getTotalRoutes(): ?int
    {
        return $this->data['total_routes'] ?? null;
    }

    /**
     * Check if async mode is enabled.
     *
     * @return bool True if async mode is enabled, false otherwise
     */
    public function isAsync(): bool
    {
        return $this->data['async'] ?? false;
    }

    /**
     * Get the processing mode (sync or async).
     *
     * @return string 'async' if async mode is enabled, 'sync' otherwise
     */
    public function getProcessingMode(): string
    {
        return $this->isAsync() ? 'async' : 'sync';
    }

    /**
     * Check if the performance metrics table exists.
     *
     * @return bool True if the table exists, false otherwise
     */
    public function tableExists(): bool
    {
        return $this->data['table_exists'] ?? false;
    }

    /**
     * Get the configured table name.
     *
     * @return string|null The configured table name or null if not available
     */
    public function getTableName(): ?string
    {
        return $this->data['table_name'] ?? null;
    }
}
