<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\DataCollector;

use Exception;
use Nowo\PerformanceBundle\Repository\RouteDataRecordRepository;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use Nowo\PerformanceBundle\Service\DependencyChecker;
use Nowo\PerformanceBundle\Service\TableStatusChecker;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\KernelInterface;
use Throwable;

use function is_array;
use function sprintf;

/**
 * Data collector for Performance Bundle.
 * Collects performance metrics and displays them in the Web Profiler toolbar.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
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
     * Route data record repository (optional, for access count from records).
     */
    private ?RouteDataRecordRepository $recordRepository = null;

    /**
     * Kernel interface (optional, for environment detection).
     */
    private ?KernelInterface $kernel = null;

    /**
     * Table status checker (optional, for table existence verification).
     */
    private ?TableStatusChecker $tableStatusChecker = null;

    /**
     * Whether to run table status checks (existence/completeness). When false, no DB introspection queries are made.
     */
    private bool $checkTableStatus = true;

    /**
     * Dependency checker (optional, for checking optional dependencies).
     */
    private ?DependencyChecker $dependencyChecker = null;

    /**
     * Whether a new record was created.
     */
    private ?bool $recordWasNew = null;

    /**
     * Whether an existing record was updated.
     */
    private ?bool $recordWasUpdated = null;

    /**
     * Configured environments where tracking is enabled.
     */
    private ?array $configuredEnvironments = null;

    /**
     * Current system environment.
     */
    private ?string $currentEnvironment = null;

    /**
     * Reason why tracking is disabled (if disabled).
     */
    private ?string $disabledReason = null;

    /**
     * Creates a new instance.
     *
     * @param RouteDataRepository|null $repository The route data repository (optional)
     * @param KernelInterface|null $kernel The kernel interface (optional)
     * @param TableStatusChecker|null $tableStatusChecker The table status checker (optional)
     * @param DependencyChecker|null $dependencyChecker The dependency checker (optional)
     * @param RouteDataRecordRepository|null $recordRepository The route data record repository (optional)
     * @param bool $checkTableStatus Whether to check table existence/completeness (default true). Set false to save queries.
     */
    public function __construct(
        ?RouteDataRepository $repository = null,
        ?KernelInterface $kernel = null,
        ?TableStatusChecker $tableStatusChecker = null,
        ?DependencyChecker $dependencyChecker = null,
        ?RouteDataRecordRepository $recordRepository = null,
        bool $checkTableStatus = true,
    ) {
        $this->repository         = $repository;
        $this->kernel             = $kernel;
        $this->tableStatusChecker = $tableStatusChecker;
        $this->dependencyChecker  = $dependencyChecker;
        $this->recordRepository   = $recordRepository;
        $this->checkTableStatus   = $checkTableStatus;
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
     * @param int $queryCount The total number of queries
     * @param float $queryTime The total query execution time in seconds
     */
    public function setQueryMetrics(int $queryCount, float $queryTime): void
    {
        $this->queryCount = $queryCount;
        $this->queryTime  = $queryTime;
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
     * Set configured environments.
     *
     * @param array $environments The configured environments
     */
    public function setConfiguredEnvironments(array $environments): void
    {
        $this->configuredEnvironments = $environments;
    }

    /**
     * Set current system environment.
     *
     * @param string $environment The current environment
     */
    public function setCurrentEnvironment(string $environment): void
    {
        $this->currentEnvironment = $environment;
    }

    /**
     * Set reason why tracking is disabled.
     *
     * @param string|null $reason The reason for being disabled, or null to clear
     */
    public function setDisabledReason(?string $reason): void
    {
        $this->disabledReason = $reason;
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

    /**
     * Set record operation information.
     *
     * @param bool $isNew Whether a new record was created
     * @param bool $wasUpdated Whether an existing record was updated
     */
    public function setRecordOperation(bool $isNew, bool $wasUpdated): void
    {
        $this->recordWasNew     = $isNew;
        $this->recordWasUpdated = $wasUpdated;

        // Also update the data array if it has been initialized (collect() has been called)
        // This ensures the information is available even if setRecordOperation() is called after collect()
        if (isset($this->data) && is_array($this->data)) {
            $this->data['record_was_new']     = $isNew;
            $this->data['record_was_updated'] = $wasUpdated;
        }
    }

    public function collect(Request $request, Response $response, ?Throwable $exception = null): void
    {
        $routeName = $this->routeName ?? $request->attributes->get('_route');
        $env       = $this->kernel?->getEnvironment() ?? 'dev';

        // When tracking is disabled (bundle disabled or route ignored): avoid any expensive work
        // (no DB, no QueryTrackingMiddleware, no table/repository/dependency checks)
        if (!$this->enabled) {
            $this->data = [
                'enabled'                   => false,
                'route_name'                => $routeName,
                'request_time'              => null,
                'query_count'               => 0,
                'query_time'                => 0.0,
                'access_count'              => null,
                'ranking_by_request_time'   => null,
                'ranking_by_query_count'    => null,
                'total_routes'              => null,
                'async'                     => false,
                'table_exists'              => false,
                'table_is_complete'         => false,
                'table_name'                => null,
                'missing_columns'           => [],
                'records_table_exists'      => null,
                'records_table_is_complete' => null,
                'records_table_name'        => null,
                'missing_columns_records'   => [],
                'enable_access_records'     => false,
                'record_was_new'            => null,
                'record_was_updated'        => null,
                'configured_environments'   => $this->configuredEnvironments,
                'current_environment'       => $this->currentEnvironment ?? $env,
                'disabled_reason'           => $this->disabledReason,
                'missing_dependencies'      => [],
                'dependency_status'         => [],
            ];

            return;
        }

        $requestTime = null;
        if ($this->startTime !== null) {
            $requestTime = microtime(true) - $this->startTime;
        }

        // Try to get query metrics directly from QueryTrackingMiddleware if not already set
        // This ensures we have the latest values even if collect() is called before onKernelTerminate
        $queryCount = $this->queryCount;
        $queryTime  = $this->queryTime;

        if ($queryCount === null || $queryTime === null) {
            try {
                $queryCount = \Nowo\PerformanceBundle\DBAL\QueryTrackingMiddleware::getQueryCount();
                $queryTime  = \Nowo\PerformanceBundle\DBAL\QueryTrackingMiddleware::getTotalQueryTime();
            } catch (Exception $e) {
                // Fallback to stored values or 0
                $queryCount = $queryCount ?? 0;
                $queryTime  = $queryTime ?? 0.0;
            }
        }

        // Check if table exists and is complete
        $tableExists     = false;
        $tableIsComplete = false;
        $tableName       = null;
        $missingColumns  = [];

        $recordsTableExists     = null;
        $recordsTableIsComplete = null;
        $recordsTableName       = null;
        $missingColumnsRecords  = [];

        if ($this->tableStatusChecker !== null && $this->checkTableStatus) {
            try {
                // Single batch call to avoid N+1 (tableExists + tableIsComplete + getMissingColumns)
                $mainStatus      = $this->tableStatusChecker->getMainTableStatus();
                $tableExists     = $mainStatus['exists'];
                $tableIsComplete = $mainStatus['complete'];
                $tableName       = $mainStatus['table_name'];
                $missingColumns  = $mainStatus['missing_columns'];

                // Records table status in one batch (only when enable_access_records is true)
                $recordsStatus = $this->tableStatusChecker->getRecordsTableStatus();
                if ($recordsStatus !== null) {
                    $recordsTableExists     = $recordsStatus['exists'];
                    $recordsTableIsComplete = $recordsStatus['complete'];
                    $recordsTableName       = $recordsStatus['table_name'];
                    $missingColumnsRecords  = $recordsStatus['missing_columns'];
                }
            } catch (Exception $e) {
                // Silently fail if table check fails
            }
        }

        // Get ranking and access count information if repository is available
        // Only execute ranking queries if enabled (they can be expensive)
        $accessCount          = null;
        $rankingByRequestTime = null;
        $rankingByQueryCount  = null;
        $totalRoutes          = null;

        // Check if ranking queries are enabled (default: true for backward compatibility)
        $enableRankingQueries = true;
        try {
            $enableRankingQueries = $this->kernel?->getContainer()?->getParameter('nowo_performance.dashboard.enable_ranking_queries') ?? true;
        } catch (Exception $e) {
            // If parameter doesn't exist or container is not available, use default (true)
        }

        if ($this->repository !== null && $routeName !== null) {
            try {
                $routeData = $this->repository->findByRouteAndEnv($routeName, $env);
                if ($routeData !== null) {
                    $accessCount = $this->recordRepository !== null
                        ? $this->recordRepository->countByRouteData($routeData)
                        : null;

                    // Only execute ranking queries if enabled
                    if ($enableRankingQueries) {
                        // Pass the RouteData entity directly to avoid duplicate queries
                        $rankingByRequestTime = $this->repository->getRankingByRequestTime($routeData);
                        $rankingByQueryCount  = $this->repository->getRankingByQueryCount($routeData);
                        $totalRoutes          = $this->repository->getTotalRoutesCount($env);
                    }
                }
            } catch (Exception $e) {
                // Silently fail if repository query fails (e.g., table doesn't exist yet)
            }
        }

        // Get dependency information
        $missingDependencies = [];
        $dependencyStatus    = [];
        if ($this->dependencyChecker !== null) {
            $missingDependencies = $this->dependencyChecker->getMissingDependencies();
            $dependencyStatus    = $this->dependencyChecker->getDependencyStatus();
        }

        $this->data = [
            'enabled'                   => $this->enabled,
            'route_name'                => $routeName,
            'request_time'              => $requestTime,
            'query_count'               => $queryCount,
            'query_time'                => $queryTime,
            'access_count'              => $accessCount,
            'ranking_by_request_time'   => $rankingByRequestTime,
            'ranking_by_query_count'    => $rankingByQueryCount,
            'total_routes'              => $totalRoutes,
            'async'                     => $this->async && $this->isMessengerAvailable(),
            'table_exists'              => $tableExists,
            'table_is_complete'         => $tableIsComplete,
            'table_name'                => $tableName,
            'missing_columns'           => $missingColumns,
            'records_table_exists'      => $recordsTableExists,
            'records_table_is_complete' => $recordsTableIsComplete,
            'records_table_name'        => $recordsTableName,
            'missing_columns_records'   => $missingColumnsRecords,
            'enable_access_records'     => $this->tableStatusChecker?->isAccessRecordsEnabled() ?? false,
            // Note: record_was_new and record_was_updated are set by setRecordOperation()
            // which is called in onKernelTerminate (after collect()). These values will be
            // updated in setRecordOperation() if the array already exists.
            'record_was_new'          => $this->recordWasNew ?? null,
            'record_was_updated'      => $this->recordWasUpdated ?? null,
            'configured_environments' => $this->configuredEnvironments,
            'current_environment'     => $this->currentEnvironment ?? $env,
            'disabled_reason'         => $this->disabledReason,
            'missing_dependencies'    => $missingDependencies,
            'dependency_status'       => $dependencyStatus,
        ];
    }

    public function reset(): void
    {
        $this->data                   = [];
        $this->startTime              = null;
        $this->queryCount             = null;
        $this->queryTime              = null;
        $this->routeName              = null;
        $this->async                  = false;
        $this->enabled                = false;
        $this->recordWasNew           = null;
        $this->recordWasUpdated       = null;
        $this->configuredEnvironments = null;
        $this->currentEnvironment     = null;
        $this->disabledReason         = null;
        // enabled is set per-request in onKernelRequest; reset to false so toolbar/profiler
        // sub-requests don't show stale "enabled" from the previous (page) request
    }

    /**
     * Check if Symfony Messenger is available.
     *
     * @return bool True if Messenger is available
     */
    private function isMessengerAvailable(): bool
    {
        return interface_exists(\Symfony\Component\Messenger\MessageBusInterface::class)
            || class_exists(\Symfony\Component\Messenger\MessageBusInterface::class);
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
        // Read from property first (set in setEnabled()), then fallback to data array
        // This ensures it works even when profiler is disabled and collect() is never called
        if (isset($this->enabled)) {
            return $this->enabled;
        }

        // Fallback to data array (for when profiler is enabled and collect() has been called)
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
            $time < 1    => sprintf('%.2f ms', $time),
            $time < 1000 => sprintf('%.0f ms', $time),
            default      => sprintf('%.2f s', $time / 1000),
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
            $time < 1    => sprintf('%.2f ms', $time),
            $time < 1000 => sprintf('%.0f ms', $time),
            default      => sprintf('%.2f s', $time / 1000),
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
     * Check if the table is complete (has all required columns).
     *
     * @return bool True if the table exists and has all required columns, false otherwise
     */
    public function tableIsComplete(): bool
    {
        return $this->data['table_is_complete'] ?? false;
    }

    /**
     * Get list of missing columns in the table.
     *
     * @return array<string> List of missing column names
     */
    public function getMissingColumns(): array
    {
        return $this->data['missing_columns'] ?? [];
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

    /**
     * Check if a new record was created.
     *
     * @return bool|null True if new record was created, false if existing record was updated, null if unknown
     */
    public function wasRecordNew(): ?bool
    {
        // Check property first (set by setRecordOperation() in onKernelTerminate)
        // This is important because setRecordOperation() is called AFTER collect() is executed
        // The property will be available even after serialization
        if ($this->recordWasNew !== null) {
            return $this->recordWasNew;
        }

        // Fallback to data array (set during collect())
        return $this->data['record_was_new'] ?? null;
    }

    /**
     * Alias for wasRecordNew() for Twig property access (collector.wasRecordNew).
     *
     * @return bool|null True if new record was created, false otherwise, null if unknown
     */
    public function getWasRecordNew(): ?bool
    {
        return $this->wasRecordNew();
    }

    /**
     * Check if an existing record was updated.
     *
     * @return bool|null True if existing record was updated, false if new record was created, null if unknown
     */
    public function wasRecordUpdated(): ?bool
    {
        // Check property first (set by setRecordOperation() in onKernelTerminate)
        // This is important because setRecordOperation() is called AFTER collect() is executed
        // The property will be available even after serialization
        if ($this->recordWasUpdated !== null) {
            return $this->recordWasUpdated;
        }

        // Fallback to data array (set during collect())
        return $this->data['record_was_updated'] ?? null;
    }

    /**
     * Alias for wasRecordUpdated() for Twig property access (collector.wasRecordUpdated).
     *
     * @return bool|null True if existing record was updated, false otherwise, null if unknown
     */
    public function getWasRecordUpdated(): ?bool
    {
        return $this->wasRecordUpdated();
    }

    /**
     * Get configured environments.
     *
     * @return array|null The configured environments or null if not available
     */
    public function getConfiguredEnvironments(): ?array
    {
        return $this->data['configured_environments'] ?? null;
    }

    /**
     * Get current system environment.
     *
     * @return string|null The current environment or null if not available
     */
    public function getCurrentEnvironment(): ?string
    {
        return $this->data['current_environment'] ?? null;
    }

    /**
     * Get reason why tracking is disabled.
     *
     * @return string|null The reason for being disabled or null if enabled
     */
    public function getDisabledReason(): ?string
    {
        return $this->data['disabled_reason'] ?? null;
    }

    /**
     * Get list of missing optional dependencies.
     *
     * @return array<int, array{package: string, feature: string, message: string, install_command: string}>
     */
    public function getMissingDependencies(): array
    {
        return $this->data['missing_dependencies'] ?? [];
    }

    /**
     * Get status of optional dependencies (e.g. messenger, form).
     *
     * @return array<string, array{available: bool, package: string}>
     */
    public function getDependencyStatus(): array
    {
        return $this->data['dependency_status'] ?? [];
    }

    /**
     * Get the record operation status as a human-readable string.
     *
     * @return string Status description (e.g., "New record created", "Existing record updated", "No changes")
     */
    public function getRecordOperationStatus(): string
    {
        $isNew      = $this->wasRecordNew();
        $wasUpdated = $this->wasRecordUpdated();

        if ($isNew === true) {
            return 'New record created';
        }

        if ($wasUpdated === true) {
            return 'Existing record updated';
        }

        if ($isNew === false && $wasUpdated === false) {
            return 'No changes (metrics not worse than existing)';
        }

        return 'Unknown';
    }
}
