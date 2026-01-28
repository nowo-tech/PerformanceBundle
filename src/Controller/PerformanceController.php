<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Controller;

use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Event\AfterRecordDeletedEvent;
use Nowo\PerformanceBundle\Event\AfterRecordReviewedEvent;
use Nowo\PerformanceBundle\Event\AfterRecordsClearedEvent;
use Nowo\PerformanceBundle\Event\BeforeRecordDeletedEvent;
use Nowo\PerformanceBundle\Event\BeforeRecordReviewedEvent;
use Nowo\PerformanceBundle\Event\BeforeRecordsClearedEvent;
use Nowo\PerformanceBundle\Form\ClearPerformanceDataType;
use Nowo\PerformanceBundle\Form\DeleteRecordsByFilterType;
use Nowo\PerformanceBundle\Form\DeleteRecordType;
use Nowo\PerformanceBundle\Form\PerformanceFiltersType;
use Nowo\PerformanceBundle\Form\RecordFiltersType;
use Nowo\PerformanceBundle\Form\ReviewRouteDataType;
use Nowo\PerformanceBundle\Form\StatisticsEnvFilterType;
use Nowo\PerformanceBundle\Model\ClearPerformanceDataRequest;
use Nowo\PerformanceBundle\Model\DeleteRecordsByFilterRequest;
use Nowo\PerformanceBundle\Model\RecordFilters;
use Nowo\PerformanceBundle\Model\RouteDataWithAggregates;
use Nowo\PerformanceBundle\Model\StatisticsEnvFilter;
use Nowo\PerformanceBundle\Service\DependencyChecker;
use Nowo\PerformanceBundle\Service\PerformanceAnalysisService;
use Nowo\PerformanceBundle\Service\PerformanceCacheService;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use Nowo\PerformanceBundle\Service\TableStatusChecker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Controller for displaying performance metrics.
 *
 * This controller provides a web interface to view and filter performance data.
 * The route path and prefix can be configured via bundle configuration.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class PerformanceController extends AbstractController
{
    /**
     * Creates a new instance.
     *
     * @param PerformanceMetricsService $metricsService Service for retrieving metrics
     * @param bool                      $enabled        Whether the performance dashboard is enabled
     * @param array                     $requiredRoles  Required roles to access the dashboard
     */
    public function __construct(
        private readonly PerformanceMetricsService $metricsService,
        private readonly ?PerformanceAnalysisService $analysisService,
        #[Autowire('%nowo_performance.dashboard.enabled%')]
        private readonly bool $enabled,
        #[Autowire('%nowo_performance.dashboard.roles%')]
        private readonly array $requiredRoles = [],
        #[Autowire('%nowo_performance.dashboard.template%')]
        private readonly string $template = 'bootstrap',
        private readonly ?PerformanceCacheService $cacheService = null,
        private readonly ?DependencyChecker $dependencyChecker = null,
        private readonly ?TableStatusChecker $tableStatusChecker = null,
        #[Autowire('%nowo_performance.dashboard.enable_record_management%')]
        private readonly bool $enableRecordManagement = false,
        #[Autowire('%nowo_performance.dashboard.enable_review_system%')]
        private readonly bool $enableReviewSystem = false,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        #[Autowire('%nowo_performance.thresholds.request_time.warning%')]
        private readonly float $requestTimeWarning = 0.5,
        #[Autowire('%nowo_performance.thresholds.request_time.critical%')]
        private readonly float $requestTimeCritical = 1.0,
        #[Autowire('%nowo_performance.thresholds.query_count.warning%')]
        private readonly int $queryCountWarning = 20,
        #[Autowire('%nowo_performance.thresholds.query_count.critical%')]
        private readonly int $queryCountCritical = 50,
        #[Autowire('%nowo_performance.thresholds.memory_usage.warning%')]
        private readonly float $memoryUsageWarning = 20.0,
        #[Autowire('%nowo_performance.thresholds.memory_usage.critical%')]
        private readonly float $memoryUsageCritical = 50.0,
        #[Autowire('%nowo_performance.dashboard.date_formats.datetime%')]
        private readonly string $dateTimeFormat = 'Y-m-d H:i:s',
        #[Autowire('%nowo_performance.dashboard.date_formats.date%')]
        private readonly string $dateFormat = 'Y-m-d H:i',
        #[Autowire('%nowo_performance.dashboard.auto_refresh_interval%')]
        private readonly int $autoRefreshInterval = 0,
        #[Autowire('%nowo_performance.track_status_codes%')]
        private readonly array $trackStatusCodes = [200, 404, 500, 503],
        private readonly ?\Nowo\PerformanceBundle\Repository\RouteDataRecordRepository $recordRepository = null,
        #[Autowire('%nowo_performance.enable_access_records%')]
        private readonly bool $enableAccessRecords = false,
        #[Autowire('%nowo_performance.enabled%')]
        private readonly bool $bundleEnabled = true,
        #[Autowire('%nowo_performance.environments%')]
        private readonly array $allowedEnvironments = ['dev', 'test'],
        #[Autowire('%nowo_performance.connection%')]
        private readonly string $connectionName = 'default',
        #[Autowire('%nowo_performance.track_queries%')]
        private readonly bool $trackQueries = true,
        #[Autowire('%nowo_performance.track_request_time%')]
        private readonly bool $trackRequestTime = true,
        #[Autowire('%nowo_performance.track_sub_requests%')]
        private readonly bool $trackSubRequests = false,
        #[Autowire('%nowo_performance.ignore_routes%')]
        private readonly array $ignoreRoutes = [],
        #[Autowire('%nowo_performance.async%')]
        private readonly bool $async = false,
        #[Autowire('%nowo_performance.sampling_rate%')]
        private readonly float $samplingRate = 1.0,
        #[Autowire('%nowo_performance.enable_logging%')]
        private readonly bool $enableLogging = true,
    ) {
    }

    /**
     * Display performance metrics dashboard.
     *
     * Shows a list of all tracked routes with filtering capabilities.
     *
     * The view can be customized in two ways:
     * 1. Override the complete template: `templates/bundles/NowoPerformanceBundle/Performance/index.html.twig`
     * 2. Override individual components:
     *    - `templates/bundles/NowoPerformanceBundle/Performance/components/_statistics.html.twig`
     *    - `templates/bundles/NowoPerformanceBundle/Performance/components/_filters.html.twig`
     *    - `templates/bundles/NowoPerformanceBundle/Performance/components/_routes_table.html.twig`
     *
     * @param Request $request The HTTP request
     *
     * @return Response The HTTP response
     */
    #[Route(
        path: '',
        name: 'nowo_performance.index',
        methods: ['GET']
    )]
    public function index(Request $request): Response
    {
        if (!$this->enabled) {
            throw $this->createNotFoundException('Performance dashboard is disabled.');
        }

        // Check role requirements if configured
        if (!empty($this->requiredRoles)) {
            $hasAccess = false;
            foreach ($this->requiredRoles as $role) {
                if ($this->isGranted($role)) {
                    $hasAccess = true;
                    break;
                }
            }

            if (!$hasAccess) {
                throw $this->createAccessDeniedException('You do not have permission to access the performance dashboard.');
            }
        }

        // Get available environments (with caching)
        try {
            $environments = $this->getAvailableEnvironments();
        } catch (\Exception $e) {
            $environments = ['dev', 'test', 'prod'];
        }

        // Create and handle form
        $form = $this->createForm(PerformanceFiltersType::class, null, [
            'environments' => $environments,
            'current_env' => $request->query->get('env') ?? $this->getParameter('kernel.environment'),
            'current_route' => $request->query->get('route'),
            'current_sort_by' => $request->query->get('sort', 'requestTime'),
            'current_order' => $request->query->get('order', 'DESC'),
            'current_limit' => (int) $request->query->get('limit', 100),
            'current_min_request_time' => $request->query->get('min_request_time') ? (float) $request->query->get('min_request_time') : null,
            'current_max_request_time' => $request->query->get('max_request_time') ? (float) $request->query->get('max_request_time') : null,
            'current_min_query_count' => $request->query->get('min_query_count') ? (int) $request->query->get('min_query_count') : null,
            'current_max_query_count' => $request->query->get('max_query_count') ? (int) $request->query->get('max_query_count') : null,
            'current_date_from' => $request->query->get('date_from') ? new \DateTimeImmutable($request->query->get('date_from')) : null,
            'current_date_to' => $request->query->get('date_to') ? new \DateTimeImmutable($request->query->get('date_to')) : null,
        ]);

        $form->handleRequest($request);

        // Get form data before creating view to ensure form is fully processed
        $formData = $form->getData();

        // Use form data that was already retrieved
        $env = $formData['env'] ?? $this->getParameter('kernel.environment');
        $routeName = $formData['route'] ?? null;
        $sortBy = $formData['sort'] ?? 'requestTime';
        $order = $formData['order'] ?? 'DESC';
        $limit = (int) ($formData['limit'] ?? 100);

        // Build filters array for advanced filtering
        $filters = [];
        if (null !== $routeName && '' !== $routeName) {
            $filters['route_name_pattern'] = $routeName;
        }
        if (isset($formData['min_request_time']) && null !== $formData['min_request_time']) {
            $filters['min_request_time'] = (float) $formData['min_request_time'];
        }
        if (isset($formData['max_request_time']) && null !== $formData['max_request_time']) {
            $filters['max_request_time'] = (float) $formData['max_request_time'];
        }
        if (isset($formData['min_query_count']) && null !== $formData['min_query_count']) {
            $filters['min_query_count'] = (int) $formData['min_query_count'];
        }
        if (isset($formData['max_query_count']) && null !== $formData['max_query_count']) {
            $filters['max_query_count'] = (int) $formData['max_query_count'];
        }
        if (isset($formData['date_from']) && $formData['date_from'] instanceof \DateTimeImmutable) {
            $filters['date_from'] = $formData['date_from'];
        }
        if (isset($formData['date_to']) && $formData['date_to'] instanceof \DateTimeImmutable) {
            $filters['date_to'] = $formData['date_to'];
        }

        // Get routes with aggregates (normalized: metrics from RouteDataRecord)
        try {
            $routes = $this->metricsService->getRoutesWithAggregatesFiltered($env, $filters, $sortBy, $order, $limit);
        } catch (\Exception $e) {
            $routes = [];
        }

        // Calculate statistics (with caching)
        try {
            $stats = null;
            if (null !== $this->cacheService) {
                $stats = $this->cacheService->getCachedStatistics($env);
            }
            if (null === $stats) {
                if (empty($filters) && (null === $limit || $limit >= 1000)) {
                    $allRoutes = $routes;
                } else {
                    $allRoutes = $this->metricsService->getRoutesWithAggregates($env);
                }
                $stats = $this->calculateStats($allRoutes);
                if (null !== $this->cacheService) {
                    $this->cacheService->cacheStatistics($env, $stats);
                }
            }
        } catch (\Exception $e) {
            $stats = $this->calculateStats([]);
        }

        // Check dependencies
        $useComponents = false;
        $missingDependencies = [];
        $dependencyStatus = [];
        if (null !== $this->dependencyChecker) {
            $useComponents = $this->dependencyChecker->isTwigComponentAvailable();
            $missingDependencies = $this->dependencyChecker->getMissingDependencies();
            $dependencyStatus = $this->dependencyChecker->getDependencyStatus();
        }

        // Create review forms for each route if review system is enabled
        $reviewForms = [];
        if ($this->enableReviewSystem) {
            foreach ($routes as $route) {
                $routeData = $route instanceof RouteDataWithAggregates ? $route->getRouteData() : $route;
                if ($routeData instanceof RouteData && !$routeData->isReviewed()) {
                    $reviewForm = $this->createForm(ReviewRouteDataType::class, null, [
                        'csrf_token_id' => 'review_performance_record_'.$routeData->getId(),
                    ]);
                    $reviewForms[$routeData->getId()] = $reviewForm->createView();
                }
            }
        }

        // Clear performance data form (FormType, no raw inputs)
        $clearForm = $this->createForm(ClearPerformanceDataType::class, new ClearPerformanceDataRequest($env));
        $clearFormView = $clearForm->createView();

        // Delete-record forms per route (FormType with CSRF only)
        $deleteForms = [];
        if ($this->enableRecordManagement) {
            foreach ($routes as $route) {
                $routeId = $route instanceof RouteDataWithAggregates ? $route->getId() : $route->getId();
                if (null !== $routeId) {
                    $deleteForm = $this->createForm(DeleteRecordType::class, null, [
                        'csrf_token_id' => 'delete_performance_record_'.$routeId,
                        'submit_attr_class' => 'tailwind' === $this->template ? 'inline-flex items-center px-2 py-1 text-xs font-medium rounded bg-red-100 text-red-700 hover:bg-red-200' : 'btn btn-danger btn-sm',
                    ]);
                    $deleteForms[$routeId] = $deleteForm->createView();
                }
            }
        }

        // Create a fresh form instance and view to avoid "field already rendered" errors
        // This ensures we have a clean form state for rendering
        $formForView = $this->createForm(PerformanceFiltersType::class, null, [
            'environments' => $environments,
            'current_env' => $request->query->get('env') ?? $this->getParameter('kernel.environment'),
            'current_route' => $request->query->get('route'),
            'current_sort_by' => $request->query->get('sort', 'requestTime'),
            'current_order' => $request->query->get('order', 'DESC'),
            'current_limit' => (int) $request->query->get('limit', 100),
            'current_min_request_time' => $request->query->get('min_request_time') ? (float) $request->query->get('min_request_time') : null,
            'current_max_request_time' => $request->query->get('max_request_time') ? (float) $request->query->get('max_request_time') : null,
            'current_min_query_count' => $request->query->get('min_query_count') ? (int) $request->query->get('min_query_count') : null,
            'current_max_query_count' => $request->query->get('max_query_count') ? (int) $request->query->get('max_query_count') : null,
            'current_date_from' => $request->query->get('date_from') ? new \DateTimeImmutable($request->query->get('date_from')) : null,
            'current_date_to' => $request->query->get('date_to') ? new \DateTimeImmutable($request->query->get('date_to')) : null,
        ]);
        $formForView->handleRequest($request);
        $formView = $formForView->createView();

        return $this->render('@NowoPerformanceBundle/Performance/index.html.twig', [
            'routes' => $routes,
            'stats' => $stats,
            'environment' => $env,
            'currentRoute' => $routeName,
            'sortBy' => $sortBy,
            'order' => $order,
            'limit' => $limit,
            'environments' => $environments,
            'template' => $this->template,
            'form' => $formView,
            'reviewForms' => $reviewForms,
            'clearForm' => $clearFormView,
            'deleteForms' => $deleteForms,
            'useComponents' => $useComponents,
            'missingDependencies' => $missingDependencies,
            'dependencyStatus' => $dependencyStatus,
            'enableRecordManagement' => $this->enableRecordManagement,
            'enableAccessRecords' => $this->enableAccessRecords,
            'dateTimeFormat' => $this->dateTimeFormat,
            'dateFormat' => $this->dateFormat,
            'enableReviewSystem' => $this->enableReviewSystem,
            'thresholds' => [
                'request_time' => [
                    'warning' => $this->requestTimeWarning,
                    'critical' => $this->requestTimeCritical,
                ],
                'query_count' => [
                    'warning' => $this->queryCountWarning,
                    'critical' => $this->queryCountCritical,
                ],
                'memory_usage' => [
                    'warning' => $this->memoryUsageWarning,
                    'critical' => $this->memoryUsageCritical,
                ],
            ],
            'autoRefreshInterval' => $this->autoRefreshInterval,
            'trackStatusCodes' => $this->trackStatusCodes,
        ]);
    }

    /**
     * Get sort value for a route (RouteDataWithAggregates for metric fields).
     *
     * @param RouteData|RouteDataWithAggregates $route  The route or route with aggregates
     * @param string                            $sortBy The field to sort by
     *
     * @return float|int|string|null The sort value
     */
    private function getSortValue(RouteData|RouteDataWithAggregates $route, string $sortBy): float|int|string|null
    {
        return match ($sortBy) {
            'name' => $route->getName() ?? '',
            'requestTime' => $route instanceof RouteDataWithAggregates ? ($route->getRequestTime() ?? 0.0) : 0.0,
            'queryTime' => $route instanceof RouteDataWithAggregates ? ($route->getQueryTime() ?? 0.0) : 0.0,
            'totalQueries' => $route instanceof RouteDataWithAggregates ? ($route->getTotalQueries() ?? 0) : 0,
            'accessCount' => $route instanceof RouteDataWithAggregates ? $route->getAccessCount() : 1,
            'env' => $route->getEnv() ?? '',
            'createdAt' => ($dt = $route->getCreatedAt()) ? (float) $dt->getTimestamp() : 0.0,
            'lastAccessedAt' => ($dt = $route->getLastAccessedAt()) ? (float) $dt->getTimestamp() : 0.0,
            default => $route instanceof RouteDataWithAggregates ? ($route->getRequestTime() ?? 0.0) : 0.0,
        };
    }

    /**
     * Calculate statistics for routes (RouteDataWithAggregates; metrics from aggregates).
     *
     * @param array<RouteDataWithAggregates> $routes Routes with aggregates
     *
     * @return array<string, mixed> Statistics array
     */
    private function calculateStats(array $routes): array
    {
        if (empty($routes)) {
            return [
                'total_routes' => 0,
                'total_queries' => 0,
                'avg_request_time' => 0.0,
                'avg_query_time' => 0.0,
                'max_request_time' => 0.0,
                'max_query_time' => 0.0,
                'max_queries' => 0,
            ];
        }

        $requestTimes = array_values(array_filter(array_map(static fn (RouteDataWithAggregates $r) => $r->getRequestTime(), $routes), static fn ($v) => null !== $v));
        $queryTimes = array_values(array_filter(array_map(static fn (RouteDataWithAggregates $r) => $r->getQueryTime(), $routes), static fn ($v) => null !== $v));
        $queryCounts = array_values(array_filter(array_map(static fn (RouteDataWithAggregates $r) => $r->getTotalQueries(), $routes), static fn ($v) => null !== $v));

        return [
            'total_routes' => \count($routes),
            'total_queries' => array_sum($queryCounts),
            'avg_request_time' => [] !== $requestTimes ? array_sum($requestTimes) / \count($requestTimes) : 0.0,
            'avg_query_time' => [] !== $queryTimes ? array_sum($queryTimes) / \count($queryTimes) : 0.0,
            'max_request_time' => [] !== $requestTimes ? max($requestTimes) : 0.0,
            'max_query_time' => [] !== $queryTimes ? max($queryTimes) : 0.0,
            'max_queries' => [] !== $queryCounts ? max($queryCounts) : 0,
        ];
    }

    /**
     * Calculate advanced statistics for routes (RouteDataWithAggregates).
     *
     * @param array<RouteDataWithAggregates> $routes Routes with aggregates
     *
     * @return array<string, mixed> Advanced statistics array
     */
    private function calculateAdvancedStats(array $routes): array
    {
        if (empty($routes)) {
            return [
                'request_time' => $this->getEmptyStats(),
                'query_time' => $this->getEmptyStats(),
                'query_count' => $this->getEmptyStats(),
                'memory_usage' => $this->getEmptyStats(),
                'access_count' => $this->getEmptyStats(),
            ];
        }

        $requestTimes = array_values(array_filter(array_map(static fn (RouteDataWithAggregates $r) => $r->getRequestTime(), $routes), static fn ($v) => null !== $v));
        $queryTimes = array_values(array_filter(array_map(static fn (RouteDataWithAggregates $r) => $r->getQueryTime(), $routes), static fn ($v) => null !== $v));
        $queryCounts = array_values(array_filter(array_map(static fn (RouteDataWithAggregates $r) => $r->getTotalQueries(), $routes), static fn ($v) => null !== $v));
        $memoryUsages = array_values(array_filter(array_map(static fn (RouteDataWithAggregates $r) => $r->getMemoryUsage() ? $r->getMemoryUsage() / 1024 / 1024 : null, $routes), static fn ($v) => null !== $v)); // Convert to MB
        $accessCounts = array_values(array_filter(array_map(static fn (RouteDataWithAggregates $r) => $r->getAccessCount(), $routes), static fn ($v) => null !== $v));

        return [
            'request_time' => $this->calculateDetailedStats($requestTimes, 'Request Time', 's'),
            'query_time' => $this->calculateDetailedStats($queryTimes, 'Query Time', 's'),
            'query_count' => $this->calculateDetailedStats($queryCounts, 'Query Count', ''),
            'memory_usage' => $this->calculateDetailedStats($memoryUsages, 'Memory Usage', 'MB'),
            'access_count' => $this->calculateDetailedStats($accessCounts, 'Access Count', ''),
        ];
    }

    /**
     * Calculate detailed statistics for a metric.
     *
     * @param array  $values Array of numeric values
     * @param string $label  Metric label
     * @param string $unit   Unit of measurement
     *
     * @return array<string, mixed> Detailed statistics
     */
    private function calculateDetailedStats(array $values, string $label, string $unit): array
    {
        if (empty($values)) {
            return $this->getEmptyStats();
        }

        sort($values);
        $count = \count($values);
        $sum = array_sum($values);
        $mean = $sum / $count;

        // Median
        $median = 0 === $count % 2
            ? ($values[($count / 2) - 1] + $values[$count / 2]) / 2
            : $values[floor($count / 2)];

        // Mode (most frequent value, rounded to 2 decimals)
        // Convert to strings to work with array_count_values (only accepts strings and integers)
        $rounded = array_map(static fn ($v) => (string) round($v, 2), $values);
        $frequencies = array_count_values($rounded);
        arsort($frequencies);
        $mode = (float) key($frequencies);

        // Standard deviation
        $variance = array_sum(array_map(static fn ($v) => ($v - $mean) ** 2, $values)) / $count;
        $stdDev = sqrt($variance);

        // Percentiles
        $percentiles = [];
        foreach ([25, 50, 75, 90, 95, 99] as $p) {
            $index = ($p / 100) * ($count - 1);
            $lower = floor($index);
            $upper = ceil($index);
            if ($lower === $upper) {
                $percentiles[$p] = $values[$lower];
            } else {
                $weight = $index - $lower;
                $percentiles[$p] = $values[$lower] * (1 - $weight) + $values[$upper] * $weight;
            }
        }

        // Min/Max
        $min = min($values);
        $max = max($values);
        $range = $max - $min;

        // Outliers (values beyond Q3 + 1.5*IQR or Q1 - 1.5*IQR)
        $q1 = $percentiles[25];
        $q3 = $percentiles[75];
        $iqr = $q3 - $q1;
        $lowerBound = $q1 - 1.5 * $iqr;
        $upperBound = $q3 + 1.5 * $iqr;
        $outliers = array_filter($values, static fn ($v) => $v < $lowerBound || $v > $upperBound);

        // Distribution buckets for histogram
        $buckets = 10;
        $distribution = array_fill(0, $buckets, 0);

        // Handle case where all values are the same (avoid division by zero)
        if (0.0 === $max - $min || $max === $min) {
            // All values are the same, put them all in the first bucket
            $distribution[0] = $count;
            $bucketLabels = array_fill(0, $buckets, round($min, 2));
        } else {
            $bucketSize = ($max - $min) / $buckets;
            foreach ($values as $value) {
                $bucketIndex = min(floor(($value - $min) / $bucketSize), $buckets - 1);
                ++$distribution[(int) $bucketIndex];
            }
            $bucketLabels = array_map(static fn ($i) => round($min + ($i * $bucketSize), 2), range(0, $buckets - 1));
        }

        return [
            'label' => $label,
            'unit' => $unit,
            'count' => $count,
            'mean' => round($mean, 4),
            'median' => round($median, 4),
            'mode' => round((float) $mode, 4),
            'std_dev' => round($stdDev, 4),
            'min' => round($min, 4),
            'max' => round($max, 4),
            'range' => round($range, 4),
            'percentiles' => array_map(static fn ($v) => round($v, 4), $percentiles),
            'outliers_count' => \count($outliers),
            'outliers' => array_map(static fn ($v) => round($v, 4), array_values($outliers)),
            'distribution' => $distribution,
            'bucket_labels' => $bucketLabels,
        ];
    }

    /**
     * Get empty statistics structure.
     *
     * @return array<string, mixed> Empty statistics
     */
    private function getEmptyStats(): array
    {
        return [
            'label' => '',
            'unit' => '',
            'count' => 0,
            'mean' => 0.0,
            'median' => 0.0,
            'mode' => 0.0,
            'std_dev' => 0.0,
            'min' => 0.0,
            'max' => 0.0,
            'range' => 0.0,
            'percentiles' => [],
            'outliers_count' => 0,
            'outliers' => [],
            'distribution' => [],
            'bucket_labels' => [],
        ];
    }

    /**
     * Display advanced statistics and analytics.
     *
     * Shows detailed statistical analysis with charts to identify optimization targets.
     *
     * @param Request $request The HTTP request
     *
     * @return Response The HTTP response
     */
    #[Route(
        path: '/statistics',
        name: 'nowo_performance.statistics',
        methods: ['GET']
    )]
    public function statistics(Request $request): Response
    {
        if (!$this->enabled) {
            throw $this->createNotFoundException('Performance dashboard is disabled.');
        }

        // Check role requirements if configured
        if (!empty($this->requiredRoles)) {
            $hasAccess = false;
            foreach ($this->requiredRoles as $role) {
                if ($this->isGranted($role)) {
                    $hasAccess = true;
                    break;
                }
            }

            if (!$hasAccess) {
                throw $this->createAccessDeniedException('You do not have permission to access the performance statistics.');
            }
        }

        // Environment selector form (FormType) – GET
        try {
            $environments = $this->getAvailableEnvironments();
        } catch (\Exception $e) {
            $environments = ['dev', 'test', 'prod'];
        }
        $envFilter = new StatisticsEnvFilter($request->query->get('env') ?? $this->getParameter('kernel.environment'));
        $envForm = $this->createForm(StatisticsEnvFilterType::class, $envFilter, [
            'environments' => $environments,
            'attr_class' => 'tailwind' === $this->template ? 'mt-1 block w-48 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500' : 'form-select',
        ]);
        $envForm->handleRequest($request);
        $env = $envForm->getData()->env ?? $this->getParameter('kernel.environment');

        try {
            $routes = $this->metricsService->getRoutesWithAggregates($env);
        } catch (\Exception $e) {
            $routes = [];
        }

        $advancedStats = $this->calculateAdvancedStats($routes);

        // Get routes needing attention (outliers and worst performers)
        $routesNeedingAttention = $this->getRoutesNeedingAttention($routes, $advancedStats);

        // Advanced performance analysis
        $correlations = [];
        $efficiency = [];
        $recommendations = [];
        $trafficDistribution = [];

        if (null !== $this->analysisService) {
            $correlations = $this->analysisService->analyzeCorrelations($routes);
            $efficiency = $this->analysisService->analyzeEfficiency($routes);
            $recommendations = $this->analysisService->generateRecommendations($routes, $advancedStats);
            $trafficDistribution = $this->analysisService->analyzeTrafficDistribution($routes);
        }

        return $this->render('@NowoPerformanceBundle/Performance/statistics.html.twig', [
            'advanced_stats' => $advancedStats,
            'routes_needing_attention' => $routesNeedingAttention,
            'correlations' => $correlations,
            'efficiency' => $efficiency,
            'recommendations' => $recommendations,
            'traffic_distribution' => $trafficDistribution,
            'environment' => $env,
            'environments' => $environments,
            'template' => $this->template,
            'total_routes' => \count($routes),
            'envForm' => $envForm->createView(),
        ]);
    }

    /**
     * Get routes that need attention (outliers and worst performers).
     *
     * @param array<RouteDataWithAggregates> $routes        Routes with aggregates
     * @param array                          $advancedStats Advanced statistics
     *
     * @return array<string, mixed> Routes needing attention grouped by reason
     */
    private function getRoutesNeedingAttention(array $routes, array $advancedStats): array
    {
        $result = [
            'slow_request_time' => [],
            'high_query_count' => [],
            'high_memory' => [],
            'outliers' => [],
        ];

        if (empty($routes)) {
            return $result;
        }

        $requestTimeStats = $advancedStats['request_time'];
        $queryCountStats = $advancedStats['query_count'];
        $memoryStats = $advancedStats['memory_usage'];

        foreach ($routes as $route) {
            // Slow request time (above 95th percentile)
            if (null !== $route->getRequestTime() && isset($requestTimeStats['percentiles'][95])) {
                if ($route->getRequestTime() > $requestTimeStats['percentiles'][95]) {
                    $result['slow_request_time'][] = [
                        'route' => $route,
                        'value' => $route->getRequestTime(),
                        'percentile' => 95,
                    ];
                }
            }

            // High query count (above 95th percentile)
            if (null !== $route->getTotalQueries() && isset($queryCountStats['percentiles'][95])) {
                if ($route->getTotalQueries() > $queryCountStats['percentiles'][95]) {
                    $result['high_query_count'][] = [
                        'route' => $route,
                        'value' => $route->getTotalQueries(),
                        'percentile' => 95,
                    ];
                }
            }

            // High memory usage (above 95th percentile)
            if (null !== $route->getMemoryUsage() && isset($memoryStats['percentiles'][95])) {
                $memoryMB = $route->getMemoryUsage() / 1024 / 1024;
                if ($memoryMB > $memoryStats['percentiles'][95]) {
                    $result['high_memory'][] = [
                        'route' => $route,
                        'value' => $memoryMB,
                        'percentile' => 95,
                    ];
                }
            }

            // Outliers
            $isOutlier = false;
            if (null !== $route->getRequestTime() && \in_array(round($route->getRequestTime(), 4), $requestTimeStats['outliers'] ?? [], true)) {
                $isOutlier = true;
            }
            if (null !== $route->getTotalQueries() && \in_array($route->getTotalQueries(), $queryCountStats['outliers'] ?? [], true)) {
                $isOutlier = true;
            }

            if ($isOutlier) {
                $result['outliers'][] = [
                    'route' => $route,
                    'reasons' => [],
                ];
            }
        }

        // Sort by value descending
        usort($result['slow_request_time'], static fn ($a, $b) => $b['value'] <=> $a['value']);
        usort($result['high_query_count'], static fn ($a, $b) => $b['value'] <=> $a['value']);
        usort($result['high_memory'], static fn ($a, $b) => $b['value'] <=> $a['value']);

        return $result;
    }

    /**
     * Get available environments from routes (with caching).
     *
     * @return string[] Array of environment names
     */
    protected function getAvailableEnvironments(): array
    {
        // Always use configured allowed environments as the primary source
        // This ensures all configured environments are available in the filter
        if (!empty($this->allowedEnvironments)) {
            $environments = $this->allowedEnvironments;
        } else {
            // If no allowed environments configured, try to get from database
            try {
                $environments = $this->metricsService->getRepository()->getDistinctEnvironments();

                // If still empty, add current environment as fallback
                if (empty($environments)) {
                    try {
                        $currentEnv = $this->getParameter('kernel.environment');
                        if (null !== $currentEnv) {
                            $environments = [$currentEnv];
                        }
                    } catch (\Exception $e) {
                        // Ignore if parameter is not available
                    }
                }

                // Final fallback to default environments
                if (empty($environments)) {
                    $environments = ['dev', 'test', 'prod'];
                }
            } catch (\Exception $e) {
                // Fallback to default environments if repository query fails
                $environments = ['dev', 'test', 'prod'];
            }
        }

        // Cache the result
        if (null !== $this->cacheService) {
            $this->cacheService->cacheEnvironments($environments);
        }

        return $environments;
    }

    /**
     * Export performance metrics to CSV.
     *
     * @param Request $request The HTTP request
     *
     * @return StreamedResponse The CSV file response
     */
    #[Route(
        path: '/export/csv',
        name: 'nowo_performance.export_csv',
        methods: ['GET']
    )]
    public function exportCsv(Request $request): StreamedResponse
    {
        if (!$this->enabled) {
            throw $this->createNotFoundException('Performance dashboard is disabled.');
        }

        // Check role requirements if configured
        if (!empty($this->requiredRoles)) {
            $hasAccess = false;
            foreach ($this->requiredRoles as $role) {
                if ($this->isGranted($role)) {
                    $hasAccess = true;
                    break;
                }
            }

            if (!$hasAccess) {
                throw $this->createAccessDeniedException('You do not have permission to export performance data.');
            }
        }

        $env = $request->query->get('env') ?? $this->getParameter('kernel.environment');
        $filters = $this->buildFiltersFromRequest($request);

        $response = new StreamedResponse(function () use ($env, $filters) {
            $handle = fopen('php://output', 'w');

            // Add BOM for Excel compatibility
            fprintf($handle, "\xEF\xBB\xBF");

            // CSV headers
            fputcsv($handle, [
                'Route Name',
                'HTTP Method',
                'Environment',
                'Request Time (s)',
                'Query Time (s)',
                'Total Queries',
                'Memory Usage (bytes)',
                'Access Count',
                'Last Accessed At',
                'Created At',
                'Last Accessed At',
            ]);

            try {
                $routes = $this->metricsService->getRoutesWithAggregatesFiltered($env, $filters, 'requestTime', 'DESC', null);
            } catch (\Exception $e) {
                $routes = [];
            }

            foreach ($routes as $route) {
                fputcsv($handle, [
                    $route->getName() ?? '',
                    $route->getHttpMethod() ?? '',
                    $route->getEnv() ?? '',
                    $route->getRequestTime() ?? 0.0,
                    $route->getQueryTime() ?? 0.0,
                    $route->getTotalQueries() ?? 0,
                    $route->getMemoryUsage() ?? 0,
                    $route->getAccessCount() ?? 1,
                    $route->getLastAccessedAt()?->format('Y-m-d H:i:s') ?? '',
                    $route->getCreatedAt()?->format('Y-m-d H:i:s') ?? '',
                    $route->getLastAccessedAt()?->format('Y-m-d H:i:s') ?? '',
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', \sprintf(
            'attachment; filename="performance_metrics_%s_%s.csv"',
            $env,
            date('Y-m-d_His')
        ));

        return $response;
    }

    /**
     * Export performance metrics to JSON.
     *
     * @param Request $request The HTTP request
     *
     * @return Response The JSON file response
     */
    #[Route(
        path: '/export/json',
        name: 'nowo_performance.export_json',
        methods: ['GET']
    )]
    public function exportJson(Request $request): Response
    {
        if (!$this->enabled) {
            throw $this->createNotFoundException('Performance dashboard is disabled.');
        }

        // Check role requirements if configured
        if (!empty($this->requiredRoles)) {
            $hasAccess = false;
            foreach ($this->requiredRoles as $role) {
                if ($this->isGranted($role)) {
                    $hasAccess = true;
                    break;
                }
            }

            if (!$hasAccess) {
                throw $this->createAccessDeniedException('You do not have permission to export performance data.');
            }
        }

        $env = $request->query->get('env') ?? $this->getParameter('kernel.environment');
        $filters = $this->buildFiltersFromRequest($request);

        try {
            $routes = $this->metricsService->getRoutesWithAggregatesFiltered($env, $filters, 'requestTime', 'DESC', null);
        } catch (\Exception $e) {
            $routes = [];
        }

        $data = array_map(static function ($route) {
            return [
                'route_name' => $route->getName(),
                'http_method' => $route->getHttpMethod(),
                'environment' => $route->getEnv(),
                'request_time' => $route->getRequestTime(),
                'query_time' => $route->getQueryTime(),
                'total_queries' => $route->getTotalQueries(),
                'memory_usage' => $route->getMemoryUsage(),
                'memory_usage_mb' => $route->getMemoryUsage() ? round($route->getMemoryUsage() / 1024 / 1024, 2) : null,
                'access_count' => $route->getAccessCount(),
                'last_accessed_at' => $route->getLastAccessedAt()?->format('c'),
                'params' => $route->getParams(),
                'created_at' => $route->getCreatedAt()?->format('c'),
                'last_accessed_at_iso' => $route->getLastAccessedAt()?->format('c'),
            ];
        }, $routes);

        $response = new Response(json_encode([
            'environment' => $env,
            'exported_at' => date('c'),
            'total_records' => \count($data),
            'data' => $data,
        ], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));

        $response->headers->set('Content-Type', 'application/json; charset=UTF-8');
        $response->headers->set('Content-Disposition', \sprintf(
            'attachment; filename="performance_metrics_%s_%s.json"',
            $env,
            date('Y-m-d_His')
        ));

        return $response;
    }

    /**
     * Export access records (RouteDataRecord) to CSV.
     *
     * Uses same filters as access-records page: env, start_date, end_date, route, status_code.
     *
     * @param Request $request The HTTP request
     *
     * @return StreamedResponse The CSV file response
     */
    #[Route(
        path: '/export/records/csv',
        name: 'nowo_performance.export_records_csv',
        methods: ['GET']
    )]
    public function exportRecordsCsv(Request $request): StreamedResponse
    {
        if (!$this->enabled || !$this->enableAccessRecords) {
            throw $this->createNotFoundException('Temporal access records are disabled.');
        }

        if (null === $this->recordRepository) {
            throw $this->createNotFoundException('RouteDataRecordRepository is not available.');
        }

        if (!empty($this->requiredRoles)) {
            $hasAccess = false;
            foreach ($this->requiredRoles as $role) {
                if ($this->isGranted($role)) {
                    $hasAccess = true;
                    break;
                }
            }
            if (!$hasAccess) {
                throw $this->createAccessDeniedException('You do not have permission to export access records.');
            }
        }

        $env = $request->query->get('env') ?? $this->getParameter('kernel.environment');
        $startDate = $request->query->get('start_date') ? new \DateTimeImmutable($request->query->get('start_date')) : null;
        $endDate = $request->query->get('end_date') ? new \DateTimeImmutable($request->query->get('end_date')) : null;
        $routeName = $request->query->get('route');
        $routeName = \is_string($routeName) && '' !== $routeName ? $routeName : null;
        $sc = $request->query->get('status_code');
        $statusCode = null !== $sc && '' !== $sc ? (int) $sc : null;
        $minQt = $request->query->get('min_query_time');
        $maxQt = $request->query->get('max_query_time');
        $minMb = $request->query->get('min_memory_mb');
        $maxMb = $request->query->get('max_memory_mb');
        $minQueryTime = null !== $minQt && '' !== $minQt ? (float) $minQt : null;
        $maxQueryTime = null !== $maxQt && '' !== $maxQt ? (float) $maxQt : null;
        $minMemoryUsage = null !== $minMb && '' !== $minMb ? (int) round((float) $minMb * 1024 * 1024) : null;
        $maxMemoryUsage = null !== $maxMb && '' !== $maxMb ? (int) round((float) $maxMb * 1024 * 1024) : null;

        try {
            $result = $this->recordRepository->getRecordsForExport(
                $env,
                $startDate,
                $endDate,
                $routeName,
                $statusCode,
                $minQueryTime,
                $maxQueryTime,
                $minMemoryUsage,
                $maxMemoryUsage,
            );
        } catch (\Throwable $e) {
            $result = ['records' => [], 'total' => 0];
        }

        $records = $result['records'];

        $response = new StreamedResponse(static function () use ($records) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'ID',
                'Route Name',
                'Environment',
                'Accessed At',
                'Status Code',
                'Response Time (s)',
                'Total Queries',
                'Query Time (s)',
                'Memory Usage (bytes)',
            ]);
            foreach ($records as $r) {
                $rd = $r->getRouteData();
                fputcsv($handle, [
                    $r->getId() ?? '',
                    $rd?->getName() ?? '',
                    $rd?->getEnv() ?? '',
                    $r->getAccessedAt()?->format('Y-m-d H:i:s') ?? '',
                    $r->getStatusCode() ?? '',
                    $r->getResponseTime() ?? '',
                    $r->getTotalQueries() ?? '',
                    $r->getQueryTime() ?? '',
                    $r->getMemoryUsage() ?? '',
                ]);
            }
            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', \sprintf(
            'attachment; filename="performance_access_records_%s_%s.csv"',
            $env,
            date('Y-m-d_His')
        ));

        return $response;
    }

    /**
     * Export access records (RouteDataRecord) to JSON.
     *
     * Uses same filters as access-records page: env, start_date, end_date, route, status_code.
     *
     * @param Request $request The HTTP request
     *
     * @return Response The JSON file response
     */
    #[Route(
        path: '/export/records/json',
        name: 'nowo_performance.export_records_json',
        methods: ['GET']
    )]
    public function exportRecordsJson(Request $request): Response
    {
        if (!$this->enabled || !$this->enableAccessRecords) {
            throw $this->createNotFoundException('Temporal access records are disabled.');
        }

        if (null === $this->recordRepository) {
            throw $this->createNotFoundException('RouteDataRecordRepository is not available.');
        }

        if (!empty($this->requiredRoles)) {
            $hasAccess = false;
            foreach ($this->requiredRoles as $role) {
                if ($this->isGranted($role)) {
                    $hasAccess = true;
                    break;
                }
            }
            if (!$hasAccess) {
                throw $this->createAccessDeniedException('You do not have permission to export access records.');
            }
        }

        $env = $request->query->get('env') ?? $this->getParameter('kernel.environment');
        $startDate = $request->query->get('start_date') ? new \DateTimeImmutable($request->query->get('start_date')) : null;
        $endDate = $request->query->get('end_date') ? new \DateTimeImmutable($request->query->get('end_date')) : null;
        $routeName = $request->query->get('route');
        $routeName = \is_string($routeName) && '' !== $routeName ? $routeName : null;
        $sc = $request->query->get('status_code');
        $statusCode = null !== $sc && '' !== $sc ? (int) $sc : null;
        $minQt = $request->query->get('min_query_time');
        $maxQt = $request->query->get('max_query_time');
        $minMb = $request->query->get('min_memory_mb');
        $maxMb = $request->query->get('max_memory_mb');
        $minQueryTime = null !== $minQt && '' !== $minQt ? (float) $minQt : null;
        $maxQueryTime = null !== $maxQt && '' !== $maxQt ? (float) $maxQt : null;
        $minMemoryUsage = null !== $minMb && '' !== $minMb ? (int) round((float) $minMb * 1024 * 1024) : null;
        $maxMemoryUsage = null !== $maxMb && '' !== $maxMb ? (int) round((float) $maxMb * 1024 * 1024) : null;

        try {
            $result = $this->recordRepository->getRecordsForExport(
                $env,
                $startDate,
                $endDate,
                $routeName,
                $statusCode,
                $minQueryTime,
                $maxQueryTime,
                $minMemoryUsage,
                $maxMemoryUsage,
            );
        } catch (\Throwable $e) {
            $result = ['records' => [], 'total' => 0];
        }

        $data = array_map(static function ($r) {
            $rd = $r->getRouteData();

            return [
                'id' => $r->getId(),
                'route_name' => $rd?->getName(),
                'environment' => $rd?->getEnv(),
                'accessed_at' => $r->getAccessedAt()?->format('c'),
                'status_code' => $r->getStatusCode(),
                'response_time' => $r->getResponseTime(),
                'total_queries' => $r->getTotalQueries(),
                'query_time' => $r->getQueryTime(),
                'memory_usage' => $r->getMemoryUsage(),
            ];
        }, $result['records']);

        $response = new Response(json_encode([
            'environment' => $env,
            'exported_at' => date('c'),
            'total_records' => \count($data),
            'total_matching' => $result['total'],
            'data' => $data,
        ], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));

        $response->headers->set('Content-Type', 'application/json; charset=UTF-8');
        $response->headers->set('Content-Disposition', \sprintf(
            'attachment; filename="performance_access_records_%s_%s.json"',
            $env,
            date('Y-m-d_His')
        ));

        return $response;
    }

    /**
     * Build filters array from request parameters.
     *
     * @param Request $request The HTTP request
     *
     * @return array<string, mixed> Filters array
     */
    private function buildFiltersFromRequest(Request $request): array
    {
        $filters = [];

        if ($request->query->get('route')) {
            $filters['route_name_pattern'] = $request->query->get('route');
        }

        if ($request->query->get('min_request_time')) {
            $filters['min_request_time'] = (float) $request->query->get('min_request_time');
        }

        if ($request->query->get('max_request_time')) {
            $filters['max_request_time'] = (float) $request->query->get('max_request_time');
        }

        if ($request->query->get('min_query_count')) {
            $filters['min_query_count'] = (int) $request->query->get('min_query_count');
        }

        if ($request->query->get('max_query_count')) {
            $filters['max_query_count'] = (int) $request->query->get('max_query_count');
        }

        if ($request->query->get('date_from')) {
            try {
                $filters['date_from'] = new \DateTimeImmutable($request->query->get('date_from'));
            } catch (\Exception $e) {
                // Ignore invalid date
            }
        }

        if ($request->query->get('date_to')) {
            try {
                $filters['date_to'] = new \DateTimeImmutable($request->query->get('date_to'));
            } catch (\Exception $e) {
                // Ignore invalid date
            }
        }

        return $filters;
    }

    /**
     * API endpoint for chart data.
     *
     * Returns performance metrics data formatted for Chart.js visualization.
     *
     * @param Request $request The HTTP request
     *
     * @return Response JSON response with chart data
     */
    #[Route(
        path: '/api/chart-data',
        name: 'nowo_performance.api.chart_data',
        methods: ['GET']
    )]
    public function chartData(Request $request): Response
    {
        if (!$this->enabled) {
            throw $this->createNotFoundException('Performance dashboard is disabled.');
        }

        // Check role requirements if configured
        if (!empty($this->requiredRoles)) {
            $hasAccess = false;
            foreach ($this->requiredRoles as $role) {
                if ($this->isGranted($role)) {
                    $hasAccess = true;
                    break;
                }
            }

            if (!$hasAccess) {
                throw $this->createAccessDeniedException('You do not have permission to access the performance API.');
            }
        }

        $env = $request->query->get('env') ?? $this->getParameter('kernel.environment');
        $routeName = $request->query->get('route');
        $days = (int) $request->query->get('days', 7);
        $metric = $request->query->get('metric', 'requestTime'); // requestTime, queryTime, totalQueries, memoryUsage

        try {
            $data = $this->getChartData($env, $routeName, $days, $metric);
        } catch (\Exception $e) {
            $data = [
                'labels' => [],
                'datasets' => [],
            ];
        }

        return new Response(
            json_encode($data, \JSON_PRETTY_PRINT),
            Response::HTTP_OK,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * Diagnostic page to check configuration and detect why data might not be recorded.
     *
     * Reviews configuration, database tables, environment, registered data, and ignored routes.
     *
     * @param Request $request The HTTP request
     *
     * @return Response The HTTP response
     */
    #[Route(
        path: '/diagnose',
        name: 'nowo_performance.diagnose',
        methods: ['GET']
    )]
    public function diagnose(Request $request): Response
    {
        if (!$this->enabled) {
            throw $this->createNotFoundException('Performance dashboard is disabled.');
        }

        // Check role requirements if configured
        if (!empty($this->requiredRoles)) {
            $hasAccess = false;
            foreach ($this->requiredRoles as $role) {
                if ($this->isGranted($role)) {
                    $hasAccess = true;
                    break;
                }
            }

            if (!$hasAccess) {
                throw $this->createAccessDeniedException('You do not have permission to access the diagnostic page.');
            }
        }

        $diagnostic = [];

        // 1. Configuration Check
        $diagnostic['configuration'] = [
            'bundle_enabled' => $this->bundleEnabled,
            'track_queries' => $this->trackQueries,
            'track_request_time' => $this->trackRequestTime,
            'track_sub_requests' => $this->trackSubRequests,
            'async' => $this->async,
            'sampling_rate' => $this->samplingRate,
            'enable_logging' => $this->enableLogging,
            'enable_access_records' => $this->enableAccessRecords,
            'connection_name' => $this->connectionName,
            'ignore_routes' => $this->ignoreRoutes,
            'allowed_environments' => $this->allowedEnvironments,
        ];

        // 2. Environment Check
        $currentEnv = $this->getParameter('kernel.environment');
        $diagnostic['environment'] = [
            'current' => $currentEnv,
            'allowed' => $this->allowedEnvironments,
            'is_allowed' => \in_array($currentEnv, $this->allowedEnvironments, true),
        ];

        // 3. Database Connection Check
        $connectionStatus = [
            'connection_name' => $this->connectionName,
            'connected' => false,
            'error' => null,
        ];
        try {
            $connection = $this->metricsService->getRepository()->getEntityManager()->getConnection();
            // Test connection by executing a simple query (this will auto-connect if needed)
            $connection->executeQuery('SELECT 1');
            $connectionStatus['connected'] = true;
            $connectionStatus['database_name'] = $connection->getDatabase();
            $connectionStatus['driver'] = $this->getDriverName($connection);
        } catch (\Exception $e) {
            $connectionStatus['error'] = $e->getMessage();
        }
        $diagnostic['database_connection'] = $connectionStatus;

        // 4. Table Status Check
        $tableStatus = [
            'main_table_exists' => false,
            'main_table_complete' => false,
            'main_table_name' => null,
            'missing_columns' => [],
            'records_table_exists' => false,
            'records_table_complete' => false,
            'records_table_name' => null,
            'records_missing_columns' => [],
        ];

        if (null !== $this->tableStatusChecker) {
            try {
                $tableStatus['main_table_exists'] = $this->tableStatusChecker->tableExists();
                $tableStatus['main_table_complete'] = $this->tableStatusChecker->tableIsComplete();
                $tableStatus['main_table_name'] = $this->tableStatusChecker->getTableName();
                $tableStatus['missing_columns'] = $this->tableStatusChecker->getMissingColumns();
            } catch (\Exception $e) {
                $tableStatus['error'] = $e->getMessage();
            }
        }

        // Check records table if enabled (existence + completeness vs entity schema)
        if ($this->enableAccessRecords && null !== $this->recordRepository) {
            try {
                $entityManager = $this->recordRepository->getEntityManager();
                $metadata = $entityManager->getMetadataFactory()->getMetadataFor('Nowo\PerformanceBundle\Entity\RouteDataRecord');
                $recordsTableName = method_exists($metadata, 'getTableName')
                    ? $metadata->getTableName()
                    : ($metadata->table['name'] ?? $this->tableStatusChecker?->getTableName().'_records');
                $connection = $entityManager->getConnection();
                if (method_exists($connection, 'createSchemaManager')) {
                    $schemaManager = $connection->createSchemaManager();
                } else {
                    /** @var callable $getSchemaManager */
                    $getSchemaManager = [$connection, 'getSchemaManager'];
                    $schemaManager = $getSchemaManager();
                }
                $tableStatus['records_table_exists'] = $schemaManager->tablesExist([$recordsTableName]);
                $tableStatus['records_table_name'] = $recordsTableName;

                if ($tableStatus['records_table_exists']) {
                    $table = $schemaManager->introspectTable($recordsTableName);
                    $existingColumns = [];
                    foreach ($table->getColumns() as $column) {
                        $columnName = method_exists($column, 'getName') ? $column->getName() : '';
                        $columnName = \is_string($columnName) ? $columnName : (string) $columnName;
                        $columnName = trim($columnName, '`"\'');
                        $existingColumns[strtolower($columnName)] = true;
                    }
                    $expectedRecordsColumns = [];
                    foreach ($metadata->getFieldNames() as $fieldName) {
                        $expectedRecordsColumns[] = $metadata->getColumnName($fieldName);
                    }
                    $recordsMissing = [];
                    foreach ($expectedRecordsColumns as $col) {
                        if (!isset($existingColumns[strtolower($col)])) {
                            $recordsMissing[] = $col;
                        }
                    }
                    $tableStatus['records_missing_columns'] = $recordsMissing;
                    $tableStatus['records_table_complete'] = empty($recordsMissing);
                }
            } catch (\Exception $e) {
                $tableStatus['records_table_error'] = $e->getMessage();
            }
        }

        $diagnostic['table_status'] = $tableStatus;

        // 5. Data Registration Check
        $dataStatus = [
            'has_data' => false,
            'total_records' => 0,
            'first_record_date' => null,
            'last_record_date' => null,
            'records_by_environment' => [],
            'recent_activity' => false,
        ];

        try {
            $repository = $this->metricsService->getRepository();
            $totalRecords = $repository->count([]);
            $dataStatus['total_records'] = $totalRecords;
            $dataStatus['has_data'] = $totalRecords > 0;

            if ($totalRecords > 0) {
                // Get first and last record dates
                $firstRecord = $repository->createQueryBuilder('r')
                    ->orderBy('r.createdAt', 'ASC')
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();
                $lastRecord = $repository->createQueryBuilder('r')
                    ->orderBy('r.lastAccessedAt', 'DESC')
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();

                if (null !== $firstRecord) {
                    $dataStatus['first_record_date'] = $firstRecord->getCreatedAt();
                }
                if (null !== $lastRecord) {
                    $dataStatus['last_record_date'] = $lastRecord->getLastAccessedAt();
                    // Check if last update was in the last 24 hours
                    if (null !== $dataStatus['last_record_date']) {
                        $now = new \DateTimeImmutable();
                        $diff = $now->diff($dataStatus['last_record_date']);
                        $dataStatus['recent_activity'] = 0 === $diff->days && $diff->h < 24;
                    }
                }

                // Count by environment
                foreach ($this->allowedEnvironments as $env) {
                    $count = $repository->createQueryBuilder('r')
                        ->select('COUNT(r.id)')
                        ->where('r.env = :env')
                        ->setParameter('env', $env)
                        ->getQuery()
                        ->getSingleScalarResult();
                    $dataStatus['records_by_environment'][$env] = (int) $count;
                }
            }
        } catch (\Exception $e) {
            $dataStatus['error'] = $e->getMessage();
        }

        $diagnostic['data_status'] = $dataStatus;

        // 6. Route Tracking Check (same prefix logic as PerformanceMetricsSubscriber: _wdt ignores _wdt_open, etc.)
        $currentRoute = $request->attributes->get('_route');
        $routeTracking = [
            'ignored_routes_count' => \count($this->ignoreRoutes),
            'ignored_routes' => $this->ignoreRoutes,
            'current_route' => $currentRoute,
            'current_route_ignored' => $this->isRouteIgnored($currentRoute),
        ];

        $diagnostic['route_tracking'] = $routeTracking;

        // 7. Subscriber Status Check
        $subscriberStatus = [
            'subscriber_registered' => false,
            'subscriber_class' => 'Nowo\\PerformanceBundle\\EventSubscriber\\PerformanceMetricsSubscriber',
            'data_collector_enabled' => false,
            'data_collector_disabled_reason' => null,
            'last_route_tracked' => null,
            'tracking_conditions' => [],
        ];

        // Check if subscriber is registered
        try {
            $container = $this->container ?? null;
            $subscriberFound = false;
            $detectionMethod = null;

            // Method 1: Check event dispatcher listeners (most reliable)
            if (null !== $container && $container->has('event_dispatcher')) {
                try {
                    $eventDispatcher = $container->get('event_dispatcher');

                    // Check REQUEST and TERMINATE event listeners
                    if (method_exists($eventDispatcher, 'getListeners')) {
                        $eventsToCheck = [KernelEvents::REQUEST, KernelEvents::TERMINATE];
                        foreach ($eventsToCheck as $eventName) {
                            try {
                                $listeners = $eventDispatcher->getListeners($eventName);
                                foreach ($listeners as $listener) {
                                    // Handle different listener formats
                                    $listenerClass = null;
                                    if (\is_array($listener) && isset($listener[0])) {
                                        $listenerClass = \get_class($listener[0]);
                                    } elseif (\is_object($listener)) {
                                        $listenerClass = $listener::class;
                                    }

                                    if (null !== $listenerClass && str_contains($listenerClass, 'PerformanceMetricsSubscriber')) {
                                        $subscriberFound = true;
                                        $detectionMethod = "Found in {$eventName} listeners";
                                        break 2; // Break both loops
                                    }
                                }
                            } catch (\Exception $e) {
                                // Continue to next event
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Continue to next method
                }
            }

            // Method 2: Try to get the service directly from container
            if (!$subscriberFound && null !== $container) {
                try {
                    $subscriberServiceId = 'Nowo\\PerformanceBundle\\EventSubscriber\\PerformanceMetricsSubscriber';
                    // Try with FQCN first
                    if ($container->has($subscriberServiceId)) {
                        $subscriber = $container->get($subscriberServiceId);
                        if ($subscriber instanceof \Nowo\PerformanceBundle\EventSubscriber\PerformanceMetricsSubscriber) {
                            $subscriberFound = true;
                            $detectionMethod = 'Found in container by FQCN';
                        }
                    }
                } catch (\Exception $e) {
                    // Continue to next method
                }
            }

            // Method 3: Check if subscriber class exists and is properly configured
            // Since it's explicitly registered in services.yaml with kernel.event_subscriber tag,
            // if the class exists, it should be registered
            if (!$subscriberFound && class_exists('Nowo\\PerformanceBundle\\EventSubscriber\\PerformanceMetricsSubscriber')) {
                // Verify it implements EventSubscriberInterface
                $reflection = new \ReflectionClass('Nowo\\PerformanceBundle\\EventSubscriber\\PerformanceMetricsSubscriber');
                if ($reflection->implementsInterface(\Symfony\Component\EventDispatcher\EventSubscriberInterface::class)) {
                    // Check if it has getSubscribedEvents method
                    if ($reflection->hasMethod('getSubscribedEvents')) {
                        $subscriberFound = true;
                        $detectionMethod = 'Class exists and implements EventSubscriberInterface (assumed registered via services.yaml)';
                    }
                }
            }

            $subscriberStatus['subscriber_registered'] = $subscriberFound;
            if (null !== $detectionMethod) {
                $subscriberStatus['detection_method'] = $detectionMethod;
            }
        } catch (\Exception $e) {
            $subscriberStatus['subscriber_error'] = $e->getMessage();
            // If there's an error but the class exists and implements the interface, assume it's registered
            if (class_exists('Nowo\\PerformanceBundle\\EventSubscriber\\PerformanceMetricsSubscriber')) {
                try {
                    $reflection = new \ReflectionClass('Nowo\\PerformanceBundle\\EventSubscriber\\PerformanceMetricsSubscriber');
                    if ($reflection->implementsInterface(\Symfony\Component\EventDispatcher\EventSubscriberInterface::class)) {
                        $subscriberStatus['subscriber_registered'] = true;
                        $subscriberStatus['detection_method'] = 'Fallback: Class exists and implements EventSubscriberInterface';
                    }
                } catch (\Exception $reflectionException) {
                    // Ignore reflection errors
                }
            }
        }

        // Check tracking conditions
        $trackingConditions = [];
        $trackingConditions[] = [
            'condition' => 'Bundle habilitado',
            'status' => $this->bundleEnabled,
            'required' => true,
        ];
        $trackingConditions[] = [
            'condition' => 'Entorno permitido',
            'status' => $diagnostic['environment']['is_allowed'],
            'required' => true,
            'details' => \sprintf('Entorno actual: %s, Permitidos: %s', $currentEnv, implode(', ', $this->allowedEnvironments)),
        ];
        $trackingConditions[] = [
            'condition' => 'Al menos un tracking habilitado',
            'status' => $this->trackQueries || $this->trackRequestTime,
            'required' => true,
            'details' => \sprintf('track_queries: %s, track_request_time: %s', $this->trackQueries ? 'Sí' : 'No', $this->trackRequestTime ? 'Sí' : 'No'),
        ];
        $trackingConditions[] = [
            'condition' => 'Ruta no ignorada',
            'status' => !$routeTracking['current_route_ignored'],
            'required' => true,
            'details' => $routeTracking['current_route_ignored']
                ? \sprintf('La ruta "%s" está en la lista de ignoradas', $routeTracking['current_route'])
                : \sprintf('La ruta "%s" no está en la lista de ignoradas', $routeTracking['current_route'] ?? 'null'),
        ];
        $trackingConditions[] = [
            'condition' => 'Tabla principal existe',
            'status' => $tableStatus['main_table_exists'],
            'required' => true,
        ];
        $trackingConditions[] = [
            'condition' => 'Tabla principal completa (según entidad)',
            'status' => $tableStatus['main_table_complete'],
            'required' => true,
            'details' => $tableStatus['main_table_exists'] && !$tableStatus['main_table_complete']
                ? 'Faltan columnas: '.implode(', ', $tableStatus['missing_columns'])
                : ($tableStatus['main_table_exists'] ? 'Todas las columnas esperadas están presentes' : 'La tabla no existe'),
        ];
        if ($this->enableAccessRecords) {
            $trackingConditions[] = [
                'condition' => 'Tabla de registros existe',
                'status' => $tableStatus['records_table_exists'],
                'required' => true,
            ];
            $trackingConditions[] = [
                'condition' => 'Tabla de registros completa (según entidad)',
                'status' => $tableStatus['records_table_complete'] ?? true,
                'required' => true,
                'details' => $tableStatus['records_table_exists'] && !($tableStatus['records_table_complete'] ?? true)
                    ? 'Faltan columnas: '.implode(', ', $tableStatus['records_missing_columns'] ?? [])
                    : ($tableStatus['records_table_exists'] ? 'Todas las columnas esperadas están presentes' : 'La tabla no existe'),
            ];
        }
        $trackingConditions[] = [
            'condition' => 'Conexión a base de datos',
            'status' => $diagnostic['database_connection']['connected'],
            'required' => true,
        ];
        $trackingConditions[] = [
            'condition' => 'Sampling rate',
            'status' => $this->samplingRate > 0,
            'required' => true,
            'details' => $this->samplingRate < 1.0
                ? \sprintf('Sampling rate: %.1f%% (solo se registrará el %.1f%% de las peticiones)', $this->samplingRate * 100, $this->samplingRate * 100)
                : 'Sampling rate: 100% (todas las peticiones se registrarán)',
        ];

        $subscriberStatus['tracking_conditions'] = $trackingConditions;
        $allConditionsMet = true;
        foreach ($trackingConditions as $condition) {
            if ($condition['required'] && !$condition['status']) {
                $allConditionsMet = false;
                break;
            }
        }
        $subscriberStatus['all_conditions_met'] = $allConditionsMet;

        $diagnostic['subscriber_status'] = $subscriberStatus;

        // 8. Potential Issues Summary
        $issues = [];
        $warnings = [];
        $suggestions = [];

        if (!$this->bundleEnabled) {
            $issues[] = 'El bundle está deshabilitado (nowo_performance.enabled: false)';
        }

        if (!$diagnostic['environment']['is_allowed']) {
            $issues[] = \sprintf('El entorno actual "%s" no está en la lista de entornos permitidos: %s', $currentEnv, implode(', ', $this->allowedEnvironments));
        }

        if (!$diagnostic['database_connection']['connected']) {
            $issues[] = 'No se puede conectar a la base de datos: '.($diagnostic['database_connection']['error'] ?? 'Error desconocido');
        }

        if (!$tableStatus['main_table_exists']) {
            $issues[] = 'La tabla principal no existe. Ejecuta: php bin/console nowo:performance:create-table';
        } elseif (!$tableStatus['main_table_complete']) {
            $issues[] = 'La tabla principal está incompleta. Faltan columnas: '.implode(', ', $tableStatus['missing_columns']);
            $suggestions[] = 'Ejecuta: php bin/console nowo:performance:create-table --update';
        }

        if ($this->enableAccessRecords && !$tableStatus['records_table_exists']) {
            $warnings[] = 'La tabla de registros de acceso no existe aunque está habilitada. Ejecuta: php bin/console nowo:performance:create-records-table';
        }
        if ($this->enableAccessRecords && $tableStatus['records_table_exists'] && !($tableStatus['records_table_complete'] ?? true)) {
            $warnings[] = 'La tabla de registros de acceso está incompleta. Faltan columnas: '.implode(', ', $tableStatus['records_missing_columns'] ?? []);
            $suggestions[] = 'Ejecuta: php bin/console nowo:performance:create-records-table --update para añadir las columnas faltantes sin perder datos.';
        }
        if (isset($tableStatus['error']) && $tableStatus['error']) {
            $issues[] = 'Error al comprobar la tabla principal: '.$tableStatus['error'];
        }
        if (isset($tableStatus['records_table_error']) && $tableStatus['records_table_error']) {
            $warnings[] = 'Error al comprobar la tabla de registros: '.$tableStatus['records_table_error'];
        }

        if (!$dataStatus['has_data']) {
            $warnings[] = 'No hay datos registrados todavía. Asegúrate de que el bundle esté habilitado y que las rutas no estén en la lista de ignoradas.';

            // Add specific suggestions based on tracking conditions
            $failedConditions = [];
            foreach ($subscriberStatus['tracking_conditions'] as $condition) {
                if ($condition['required'] && !$condition['status']) {
                    $failedConditions[] = $condition['condition'];
                }
            }

            if (!empty($failedConditions)) {
                $suggestions[] = 'Condiciones de tracking no cumplidas: '.implode(', ', $failedConditions);
            }

            if (!$subscriberStatus['subscriber_registered']) {
                $suggestions[] = 'El subscriber PerformanceMetricsSubscriber no está registrado. Verifica que el bundle esté correctamente instalado y que el cache de Symfony esté limpio (php bin/console cache:clear).';
            }

            if ($this->samplingRate < 1.0) {
                $suggestions[] = \sprintf('El sampling rate está en %.1f%%. Si no hay tráfico suficiente, es posible que ninguna petición haya sido muestreada. Considera aumentar el sampling rate o generar más tráfico.', $this->samplingRate * 100);
            }

            if (!$this->trackQueries && !$this->trackRequestTime) {
                $suggestions[] = 'Habilita al menos uno de los siguientes: track_queries o track_request_time en la configuración.';
            }

            // Check if there are routes that should be tracked
            $suggestions[] = 'Verifica que estés accediendo a rutas que no estén en la lista de ignoradas. Las rutas de assets, profiler y error están ignoradas por defecto.';
            $suggestions[] = 'Revisa los logs de la aplicación para ver si hay mensajes de "[PerformanceBundle]" que indiquen por qué no se está registrando.';
            $suggestions[] = 'Prueba acceder a una ruta de tu aplicación (no del bundle) y verifica si se registra. Las rutas del bundle mismo pueden estar siendo ignoradas.';
        } elseif (!$dataStatus['recent_activity']) {
            $warnings[] = 'No hay actividad reciente (última actualización hace más de 24 horas). Verifica que el tracking esté funcionando.';
        }

        if ($this->samplingRate < 1.0) {
            $warnings[] = \sprintf('El sampling rate está configurado en %.1f%%, por lo que solo se registrará el %.1f%% de las peticiones', $this->samplingRate * 100, $this->samplingRate * 100);
        }

        if (!$this->trackQueries && !$this->trackRequestTime) {
            $warnings[] = 'Tanto el tracking de queries como el de request time están deshabilitados. No se registrarán métricas.';
        }

        if (\count($this->ignoreRoutes) > 10) {
            $warnings[] = 'Hay muchas rutas ignoradas ('.\count($this->ignoreRoutes).'). Esto puede estar limitando el tracking.';
        }

        $diagnostic['issues'] = $issues;
        $diagnostic['warnings'] = $warnings;
        $diagnostic['suggestions'] = $suggestions;

        return $this->render('@NowoPerformanceBundle/Performance/diagnose.html.twig', [
            'diagnostic' => $diagnostic,
            'template' => $this->template,
        ]);
    }

    /**
     * Clear all performance records from the database.
     *
     * Optionally filters by environment. Requires CSRF token validation.
     *
     * @param Request $request The HTTP request
     *
     * @return RedirectResponse Redirects back to the dashboard
     */
    #[Route(
        path: '/clear',
        name: 'nowo_performance.clear',
        methods: ['POST']
    )]
    public function clear(Request $request): RedirectResponse
    {
        if (!$this->enabled) {
            throw $this->createNotFoundException('Performance dashboard is disabled.');
        }

        // Check role requirements if configured
        if (!empty($this->requiredRoles)) {
            $hasAccess = false;
            foreach ($this->requiredRoles as $role) {
                if ($this->isGranted($role)) {
                    $hasAccess = true;
                    break;
                }
            }

            if (!$hasAccess) {
                throw $this->createAccessDeniedException('You do not have permission to clear performance data.');
            }
        }

        $currentEnv = $this->getParameter('kernel.environment');
        $form = $this->createForm(ClearPerformanceDataType::class, new ClearPerformanceDataRequest($currentEnv));
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Invalid security token. Please try again.');
            $referer = $request->headers->get('referer');
            if ($referer && filter_var($referer, \FILTER_VALIDATE_URL)) {
                return $this->redirect($referer);
            }

            return $this->redirectToRoute('nowo_performance.index');
        }

        $env = $form->getData()->env;

        try {
            $repository = $this->metricsService->getRepository();

            // Dispatch before event
            if (null !== $this->eventDispatcher) {
                $beforeEvent = new BeforeRecordsClearedEvent($env);
                $this->eventDispatcher->dispatch($beforeEvent);

                if ($beforeEvent->isClearingPrevented()) {
                    $this->addFlash('warning', 'Clearing was prevented by an event listener.');
                    $referer = $request->headers->get('referer');
                    if ($referer && filter_var($referer, \FILTER_VALIDATE_URL)) {
                        return $this->redirect($referer);
                    }

                    return $this->redirectToRoute('nowo_performance.index');
                }
            }

            // Delete records (all or filtered by environment)
            $deletedCount = $repository->deleteAll($env);

            // Dispatch after event
            if (null !== $this->eventDispatcher) {
                $afterEvent = new AfterRecordsClearedEvent($deletedCount, $env);
                $this->eventDispatcher->dispatch($afterEvent);
            }

            // Invalidate cache
            if (null !== $this->cacheService) {
                if ($env) {
                    $this->cacheService->clearStatistics($env);
                } else {
                    $this->cacheService->clearEnvironments();
                }
            }

            $message = $env
                ? \sprintf('Successfully deleted %d performance record(s) for environment "%s".', $deletedCount, $env)
                : \sprintf('Successfully deleted %d performance record(s) from all environments.', $deletedCount);

            $this->addFlash('success', $message);
        } catch (\Exception $e) {
            $this->addFlash('error', 'An error occurred while clearing performance data: '.$e->getMessage());
        }

        $referer = $request->headers->get('referer');
        if ($referer && filter_var($referer, \FILTER_VALIDATE_URL)) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('nowo_performance.index');
    }

    /**
     * Delete a single performance record.
     *
     * Requires CSRF token validation and record management to be enabled.
     *
     * @param int     $id      The record ID
     * @param Request $request The HTTP request
     *
     * @return RedirectResponse Redirects back to the dashboard
     */
    #[Route(
        path: '/{id}/delete',
        name: 'nowo_performance.delete',
        methods: ['POST'],
        requirements: ['id' => '\d+']
    )]
    public function delete(int $id, Request $request): RedirectResponse
    {
        if (!$this->enabled) {
            throw $this->createNotFoundException('Performance dashboard is disabled.');
        }

        if (!$this->enableRecordManagement) {
            throw $this->createAccessDeniedException('Record management is not enabled.');
        }

        // Check role requirements if configured
        if (!empty($this->requiredRoles)) {
            $hasAccess = false;
            foreach ($this->requiredRoles as $role) {
                if ($this->isGranted($role)) {
                    $hasAccess = true;
                    break;
                }
            }

            if (!$hasAccess) {
                throw $this->createAccessDeniedException('You do not have permission to delete performance records.');
            }
        }

        $form = $this->createForm(DeleteRecordType::class, null, [
            'csrf_token_id' => 'delete_performance_record_'.$id,
            'submit_attr_class' => 'btn btn-danger btn-sm',
        ]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Invalid security token. Please try again.');
            $referer = $request->headers->get('referer');
            if ($referer && filter_var($referer, \FILTER_VALIDATE_URL)) {
                return $this->redirect($referer);
            }

            return $this->redirectToRoute('nowo_performance.index');
        }

        try {
            $repository = $this->metricsService->getRepository();
            // Get the record before deleting to know the environment
            $routeData = $repository->find($id);

            if (null === $routeData) {
                $this->addFlash('error', \sprintf('Record with ID %d not found.', $id));
                $referer = $request->headers->get('referer');
                if ($referer && filter_var($referer, \FILTER_VALIDATE_URL)) {
                    return $this->redirect($referer);
                }

                return $this->redirectToRoute('nowo_performance.index');
            }

            $env = $routeData->getEnv();
            $routeName = $routeData->getName();

            // Dispatch before event
            if (null !== $this->eventDispatcher) {
                $beforeEvent = new BeforeRecordDeletedEvent($routeData);
                $this->eventDispatcher->dispatch($beforeEvent);

                if ($beforeEvent->isDeletionPrevented()) {
                    $this->addFlash('warning', 'Deletion was prevented by an event listener.');
                    $referer = $request->headers->get('referer');
                    if ($referer && filter_var($referer, \FILTER_VALIDATE_URL)) {
                        return $this->redirect($referer);
                    }

                    return $this->redirectToRoute('nowo_performance.index');
                }
            }

            $deleted = $repository->deleteById($id);

            if ($deleted) {
                // Dispatch after event
                if (null !== $this->eventDispatcher) {
                    $afterEvent = new AfterRecordDeletedEvent($id, $routeName, $env);
                    $this->eventDispatcher->dispatch($afterEvent);
                }

                // Invalidate cache
                if (null !== $this->cacheService) {
                    if ($env) {
                        $this->cacheService->clearStatistics($env);
                    }
                    $this->cacheService->clearEnvironments();
                }

                $this->addFlash('success', 'Performance record deleted successfully.');
            } else {
                $this->addFlash('error', 'Record not found.');
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'An error occurred while deleting the record: '.$e->getMessage());
        }

        $referer = $request->headers->get('referer');
        if ($referer && filter_var($referer, \FILTER_VALIDATE_URL)) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('nowo_performance.index');
    }

    /**
     * Mark a performance record as reviewed.
     *
     * Requires CSRF token validation and review system to be enabled.
     *
     * @param int     $id      The record ID
     * @param Request $request The HTTP request
     *
     * @return RedirectResponse Redirects back to the dashboard
     */
    #[Route(
        path: '/{id}/review',
        name: 'nowo_performance.review',
        methods: ['POST'],
        requirements: ['id' => '\d+']
    )]
    public function review(int $id, Request $request): RedirectResponse
    {
        if (!$this->enabled) {
            throw $this->createNotFoundException('Performance dashboard is disabled.');
        }

        if (!$this->enableReviewSystem) {
            throw $this->createAccessDeniedException('Review system is not enabled.');
        }

        // Check role requirements if configured
        if (!empty($this->requiredRoles)) {
            $hasAccess = false;
            foreach ($this->requiredRoles as $role) {
                if ($this->isGranted($role)) {
                    $hasAccess = true;
                    break;
                }
            }

            if (!$hasAccess) {
                throw $this->createAccessDeniedException('You do not have permission to review performance records.');
            }
        }

        try {
            $repository = $this->metricsService->getRepository();

            // Get the record before updating to know the environment
            $routeData = $repository->find($id);

            if (null === $routeData) {
                $this->addFlash('error', \sprintf('Record with ID %d not found.', $id));
                $referer = $request->headers->get('referer');
                if ($referer && filter_var($referer, \FILTER_VALIDATE_URL)) {
                    return $this->redirect($referer);
                }

                return $this->redirectToRoute('nowo_performance.index');
            }

            // Create and handle the form
            $form = $this->createForm(ReviewRouteDataType::class, null, [
                'csrf_token_id' => 'review_performance_record_'.$id,
            ]);
            $form->handleRequest($request);

            if (!$form->isSubmitted() || !$form->isValid()) {
                $this->addFlash('error', 'Invalid form data. Please try again.');
                $referer = $request->headers->get('referer');
                if ($referer && filter_var($referer, \FILTER_VALIDATE_URL)) {
                    return $this->redirect($referer);
                }

                return $this->redirectToRoute('nowo_performance.index');
            }

            $formData = $form->getData();
            $queriesImproved = $formData['queries_improved'] ?? '';
            $timeImproved = $formData['time_improved'] ?? '';
            $reviewedBy = $this->getUser()?->getUserIdentifier();

            // Convert string values to boolean/null
            $queriesImprovedBool = match ($queriesImproved) {
                '1', 'true', 'yes' => true,
                '0', 'false', 'no' => false,
                default => null,
            };

            $timeImprovedBool = match ($timeImproved) {
                '1', 'true', 'yes' => true,
                '0', 'false', 'no' => false,
                default => null,
            };

            $env = $routeData->getEnv();

            // Dispatch before event
            if (null !== $this->eventDispatcher) {
                $beforeEvent = new BeforeRecordReviewedEvent($routeData, $queriesImprovedBool, $timeImprovedBool, $reviewedBy);
                $this->eventDispatcher->dispatch($beforeEvent);

                if ($beforeEvent->isReviewPrevented()) {
                    $this->addFlash('warning', 'Review was prevented by an event listener.');
                    $referer = $request->headers->get('referer');
                    if ($referer && filter_var($referer, \FILTER_VALIDATE_URL)) {
                        return $this->redirect($referer);
                    }

                    return $this->redirectToRoute('nowo_performance.index');
                }

                // Use modified values from event
                $queriesImprovedBool = $beforeEvent->getQueriesImproved();
                $timeImprovedBool = $beforeEvent->getTimeImproved();
                $reviewedBy = $beforeEvent->getReviewedBy();
            }

            $updated = $repository->markAsReviewed($id, $queriesImprovedBool, $timeImprovedBool, $reviewedBy);

            if ($updated) {
                // Reload the updated record for the after event
                $updatedRouteData = $repository->find($id);

                // Dispatch after event
                if (null !== $this->eventDispatcher && null !== $updatedRouteData) {
                    $afterEvent = new AfterRecordReviewedEvent($updatedRouteData);
                    $this->eventDispatcher->dispatch($afterEvent);
                }

                // Invalidate cache
                if (null !== $this->cacheService) {
                    if ($env) {
                        $this->cacheService->clearStatistics($env);
                    }
                    $this->cacheService->clearEnvironments();
                }

                $this->addFlash('success', 'Performance record marked as reviewed.');
            } else {
                $this->addFlash('error', 'Record not found.');
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'An error occurred while reviewing the record: '.$e->getMessage());
        }

        $referer = $request->headers->get('referer');
        if ($referer && filter_var($referer, \FILTER_VALIDATE_URL)) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('nowo_performance.index');
    }

    /**
     * Get chart data for visualization.
     *
     * @param string      $env       Environment name
     * @param string|null $routeName Route name (optional)
     * @param int         $days      Number of days to include
     * @param string      $metric    Metric to chart (requestTime, queryTime, totalQueries, memoryUsage)
     *
     * @return array<string, mixed> Chart data structure
     */
    private function getChartData(string $env, ?string $routeName, int $days, string $metric): array
    {
        $endDate = new \DateTimeImmutable();
        $startDate = $endDate->modify("-{$days} days");

        $filters = [
            'date_from' => $startDate,
            'date_to' => $endDate,
        ];

        if (null !== $routeName && '' !== $routeName) {
            $filters['route_name_pattern'] = $routeName;
        }

        $routes = $this->metricsService->getRoutesWithAggregatesFiltered($env, $filters, 'createdAt', 'ASC', null);

        $groupedData = [];
        foreach ($routes as $route) {
            $date = $route->getCreatedAt()?->format('Y-m-d') ?? $route->getLastAccessedAt()?->format('Y-m-d') ?? date('Y-m-d');

            if (!isset($groupedData[$date])) {
                $groupedData[$date] = [
                    'count' => 0,
                    'sum' => 0.0,
                    'max' => 0.0,
                ];
            }

            $value = match ($metric) {
                'requestTime' => $route->getRequestTime() ?? 0.0,
                'queryTime' => $route->getQueryTime() ?? 0.0,
                'totalQueries' => (float) ($route->getTotalQueries() ?? 0),
                'memoryUsage' => (float) ($route->getMemoryUsage() ?? 0) / 1024 / 1024, // Convert to MB
                default => $route->getRequestTime() ?? 0.0,
            };

            ++$groupedData[$date]['count'];
            $groupedData[$date]['sum'] += $value;
            $groupedData[$date]['max'] = max($groupedData[$date]['max'], $value);
        }

        // Generate labels for all days in range
        $labels = [];
        $avgData = [];
        $maxData = [];
        $currentDate = $startDate;

        while ($currentDate <= $endDate) {
            $dateKey = $currentDate->format('Y-m-d');
            $labels[] = $currentDate->format('M d');

            if (isset($groupedData[$dateKey])) {
                $avgData[] = round($groupedData[$dateKey]['sum'] / $groupedData[$dateKey]['count'], 2);
                $maxData[] = round($groupedData[$dateKey]['max'], 2);
            } else {
                $avgData[] = 0;
                $maxData[] = 0;
            }

            $currentDate = $currentDate->modify('+1 day');
        }

        $metricLabel = match ($metric) {
            'requestTime' => 'Request Time (s)',
            'queryTime' => 'Query Time (s)',
            'totalQueries' => 'Total Queries',
            'memoryUsage' => 'Memory Usage (MB)',
            default => 'Request Time (s)',
        };

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Average '.$metricLabel,
                    'data' => $avgData,
                    'borderColor' => 'rgb(75, 192, 192)',
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'tension' => 0.1,
                ],
                [
                    'label' => 'Maximum '.$metricLabel,
                    'data' => $maxData,
                    'borderColor' => 'rgb(255, 99, 132)',
                    'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                    'tension' => 0.1,
                ],
            ],
        ];
    }

    /**
     * Display temporal access statistics.
     *
     * Shows access counts, average response times, and status code distributions by hour.
     *
     * @param Request $request The HTTP request
     *
     * @return Response The HTTP response
     */
    #[Route(
        path: '/access-statistics',
        name: 'nowo_performance.access_statistics',
        methods: ['GET']
    )]
    public function accessStatistics(Request $request): Response
    {
        if (!$this->enabled) {
            throw $this->createNotFoundException('Performance dashboard is disabled.');
        }

        if (!$this->enableAccessRecords) {
            throw $this->createNotFoundException('Temporal access records are disabled.');
        }

        // Check role requirements if configured
        if (!empty($this->requiredRoles)) {
            $hasAccess = false;
            foreach ($this->requiredRoles as $role) {
                if ($this->isGranted($role)) {
                    $hasAccess = true;
                    break;
                }
            }

            if (!$hasAccess) {
                throw $this->createAccessDeniedException('You do not have permission to access temporal access statistics.');
            }
        }

        // Get available environments and routes first (needed for form)
        try {
            $environments = $this->getAvailableEnvironments();
        } catch (\Exception $e) {
            $environments = ['dev', 'test', 'prod'];
        }
        $env = $request->query->get('env') ?? $this->getParameter('kernel.environment');
        $availableRoutes = [];
        try {
            $routeEntities = $this->metricsService->getRepository()->findBy(['env' => $env], ['name' => 'ASC']);
            foreach ($routeEntities as $routeEntity) {
                if (null !== $routeEntity->getName()) {
                    $availableRoutes[] = $routeEntity->getName();
                }
            }
            $availableRoutes = array_values(array_unique($availableRoutes));
            sort($availableRoutes);
        } catch (\Exception $e) {
            // Ignore
        }

        // Filter form (FormType) – GET
        $filterData = new RecordFilters(
            $request->query->get('start_date') ? new \DateTimeImmutable($request->query->get('start_date')) : (new \DateTimeImmutable())->modify('-7 days')->setTime(0, 0, 0),
            $request->query->get('end_date') ? new \DateTimeImmutable($request->query->get('end_date')) : new \DateTimeImmutable(),
            $env,
            $request->query->get('route'),
            $request->query->get('status_code') ? (int) $request->query->get('status_code') : null
        );
        $filterForm = $this->createForm(RecordFiltersType::class, $filterData, [
            'environments' => $environments,
            'available_routes' => $availableRoutes,
            'all_routes_label' => 'access_statistics.all_routes',
            'all_status_label' => 'access_statistics.all_status_codes',
        ]);
        $filterForm->handleRequest($request);
        $filterData = $filterForm->getData();
        $startDate = $filterData->startDate ?? (new \DateTimeImmutable())->modify('-7 days')->setTime(0, 0, 0);
        $endDate = $filterData->endDate ?? new \DateTimeImmutable();
        $env = $filterData->env ?? $this->getParameter('kernel.environment');
        $routeName = $filterData->route;
        if ('' === $routeName || null === $routeName) {
            $routeName = null;
        }
        $statusCode = $filterData->statusCode;

        $statisticsByHour = [];
        $statisticsByDayOfWeek = [];
        $statisticsByMonth = [];
        $heatmapData = [];
        $totalAccessCount = 0;

        if (null !== $this->recordRepository) {
            try {
                $statisticsByHour = $this->recordRepository->getStatisticsByHour($env, $startDate, $endDate, $routeName, $statusCode);
                $statisticsByDayOfWeek = $this->recordRepository->getStatisticsByDayOfWeek($env, $startDate, $endDate, $routeName, $statusCode);
                $statisticsByMonth = $this->recordRepository->getStatisticsByMonth($env, $startDate, $endDate, $routeName, $statusCode);
                $heatmapData = $this->recordRepository->getHeatmapData($env, $startDate, $endDate, $routeName, $statusCode);
                $totalAccessCount = $this->recordRepository->getTotalAccessCount($env, $startDate, $endDate, $routeName, $statusCode);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error fetching access statistics: '.$e->getMessage());
                $statisticsByHour = [];
                $statisticsByDayOfWeek = [];
                $statisticsByMonth = [];
                $heatmapData = [];
            }
        } else {
            $this->addFlash('warning', 'RouteDataRecordRepository is not available. Cannot fetch temporal access statistics.');
        }

        // Delete-records-by-filter form (FormType) – POST, for the delete button
        $deleteByFilterData = new DeleteRecordsByFilterRequest(
            $env,
            'access_statistics',
            $startDate->format('Y-m-d\TH:i'),
            $endDate->format('Y-m-d\TH:i'),
            $routeName ?? '',
            null !== $statusCode ? (string) $statusCode : null
        );
        $deleteByFilterForm = $this->createForm(DeleteRecordsByFilterType::class, $deleteByFilterData, [
            'from_value' => 'access_statistics',
        ]);

        return $this->render('@NowoPerformanceBundle/Performance/access_statistics.html.twig', [
            'statistics_by_hour' => $statisticsByHour,
            'statistics_by_day_of_week' => $statisticsByDayOfWeek,
            'statistics_by_month' => $statisticsByMonth,
            'heatmap_data' => $heatmapData,
            'total_access_count' => $totalAccessCount,
            'environment' => $env,
            'environments' => $environments,
            'available_routes' => $availableRoutes,
            'selected_route' => $routeName,
            'selected_status_code' => $statusCode,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'template' => $this->template,
            'dateTimeFormat' => $this->dateTimeFormat,
            'dateFormat' => $this->dateFormat,
            'enable_record_management' => $this->enableRecordManagement,
            'filterForm' => $filterForm->createView(),
            'deleteByFilterForm' => $deleteByFilterForm->createView(),
        ]);
    }

    /**
     * Display paginated access records.
     *
     * Shows individual access records with pagination.
     *
     * @param Request $request The HTTP request
     *
     * @return Response The HTTP response
     */
    #[Route(
        path: '/access-records',
        name: 'nowo_performance.access_records',
        methods: ['GET']
    )]
    public function accessRecords(Request $request): Response
    {
        if (!$this->enabled || !$this->enableAccessRecords) {
            throw $this->createNotFoundException('Temporal access records are disabled.');
        }

        // Check role requirements if configured
        if (!empty($this->requiredRoles)) {
            $hasAccess = false;
            foreach ($this->requiredRoles as $role) {
                if ($this->isGranted($role)) {
                    $hasAccess = true;
                    break;
                }
            }

            if (!$hasAccess) {
                throw $this->createAccessDeniedException('You do not have permission to access access records.');
            }
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = max(10, min(100, (int) $request->query->get('per_page', 50)));

        // Get environments and available routes for form
        try {
            $environments = $this->getAvailableEnvironments();
        } catch (\Exception $e) {
            $environments = ['dev', 'test', 'prod'];
        }
        $env = $request->query->get('env') ?? $this->getParameter('kernel.environment');
        $availableRoutes = [];
        try {
            $routeEntities = $this->metricsService->getRepository()->findBy(['env' => $env], ['name' => 'ASC']);
            foreach ($routeEntities as $routeEntity) {
                if (null !== $routeEntity->getName()) {
                    $availableRoutes[] = $routeEntity->getName();
                }
            }
            $availableRoutes = array_values(array_unique($availableRoutes));
            sort($availableRoutes);
        } catch (\Exception $e) {
            // Ignore
        }

        // Filter form (FormType) – GET
        $minQt = $request->query->get('min_query_time');
        $maxQt = $request->query->get('max_query_time');
        $minMb = $request->query->get('min_memory_mb');
        $maxMb = $request->query->get('max_memory_mb');
        $filterData = new RecordFilters(
            $request->query->get('start_date') ? new \DateTimeImmutable($request->query->get('start_date')) : null,
            $request->query->get('end_date') ? new \DateTimeImmutable($request->query->get('end_date')) : null,
            $env,
            $request->query->get('route'),
            $request->query->get('status_code') ? (int) $request->query->get('status_code') : null,
            null !== $minQt && '' !== $minQt ? (float) $minQt : null,
            null !== $maxQt && '' !== $maxQt ? (float) $maxQt : null,
            null !== $minMb && '' !== $minMb ? (int) round((float) $minMb * 1024 * 1024) : null,
            null !== $maxMb && '' !== $maxMb ? (int) round((float) $maxMb * 1024 * 1024) : null,
        );
        $filterForm = $this->createForm(RecordFiltersType::class, $filterData, [
            'environments' => $environments,
            'available_routes' => $availableRoutes,
            'all_routes_label' => 'access_statistics.all_routes',
            'all_status_label' => 'access_statistics.all_status_codes',
        ]);
        $filterForm->handleRequest($request);
        $filterData = $filterForm->getData();
        // Sync memory MB fields (not mapped) into filterData
        $minMbData = $filterForm->get('min_memory_mb')->getData();
        $maxMbData = $filterForm->get('max_memory_mb')->getData();
        $filterData->minMemoryUsage = null !== $minMbData && '' !== $minMbData ? (int) round((float) $minMbData * 1024 * 1024) : null;
        $filterData->maxMemoryUsage = null !== $maxMbData && '' !== $maxMbData ? (int) round((float) $maxMbData * 1024 * 1024) : null;
        $startDate = $filterData->startDate;
        $endDate = $filterData->endDate;
        $env = $filterData->env ?? $this->getParameter('kernel.environment');
        $routeName = $filterData->route;
        $statusCode = $filterData->statusCode;

        $paginatedData = [
            'records' => [],
            'total' => 0,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => 0,
        ];

        if (null !== $this->recordRepository) {
            try {
                $paginatedData = $this->recordRepository->getPaginatedRecords(
                    $env,
                    $page,
                    $perPage,
                    $startDate,
                    $endDate,
                    $routeName,
                    $statusCode,
                    $filterData->minQueryTime,
                    $filterData->maxQueryTime,
                    $filterData->minMemoryUsage,
                    $filterData->maxMemoryUsage
                );
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error fetching access records: '.$e->getMessage());
            }
        } else {
            $this->addFlash('warning', 'RouteDataRecordRepository is not available. Cannot fetch access records.');
        }

        // Delete-records-by-filter form (FormType) – POST (hidden fields carry current filter state)
        $deleteByFilterData = new DeleteRecordsByFilterRequest(
            $env,
            'access_records',
            $startDate?->format('Y-m-d\TH:i'),
            $endDate?->format('Y-m-d\TH:i'),
            $routeName ?? '',
            null !== $statusCode ? (string) $statusCode : null,
            null !== $filterData->minQueryTime ? (string) $filterData->minQueryTime : null,
            null !== $filterData->maxQueryTime ? (string) $filterData->maxQueryTime : null,
            null !== $filterData->minMemoryUsage ? (string) $filterData->minMemoryUsage : null,
            null !== $filterData->maxMemoryUsage ? (string) $filterData->maxMemoryUsage : null,
        );
        $deleteByFilterForm = $this->createForm(DeleteRecordsByFilterType::class, $deleteByFilterData, [
            'from_value' => 'access_records',
        ]);

        return $this->render('@NowoPerformanceBundle/Performance/access_records.html.twig', [
            'paginated_data' => $paginatedData,
            'environment' => $env,
            'environments' => $environments,
            'available_routes' => $availableRoutes,
            'selected_route' => $routeName,
            'selected_status_code' => $statusCode,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'template' => $this->template,
            'dateTimeFormat' => $this->dateTimeFormat,
            'dateFormat' => $this->dateFormat,
            'enable_record_management' => $this->enableRecordManagement,
            'filterForm' => $filterForm->createView(),
            'deleteByFilterForm' => $deleteByFilterForm->createView(),
        ]);
    }

    /**
     * Delete access records (RouteDataRecord) matching the current filter.
     *
     * Expects POST with _token and filter params: env, start_date?, end_date?, route?, status_code?.
     * Requires enable_access_records and enable_record_management.
     *
     * @param Request $request The HTTP request
     *
     * @return RedirectResponse Redirects back to access records or access statistics
     */
    #[Route(
        path: '/delete-records-by-filter',
        name: 'nowo_performance.delete_records_by_filter',
        methods: ['POST']
    )]
    public function deleteRecordsByFilter(Request $request): RedirectResponse
    {
        if (!$this->enabled || !$this->enableAccessRecords) {
            throw $this->createNotFoundException('Temporal access records are disabled.');
        }

        if (!$this->enableRecordManagement) {
            throw $this->createAccessDeniedException('Record management is disabled.');
        }

        if (!empty($this->requiredRoles)) {
            $hasAccess = false;
            foreach ($this->requiredRoles as $role) {
                if ($this->isGranted($role)) {
                    $hasAccess = true;
                    break;
                }
            }
            if (!$hasAccess) {
                throw $this->createAccessDeniedException('You do not have permission to delete access records.');
            }
        }

        $currentEnv = $this->getParameter('kernel.environment');
        $form = $this->createForm(DeleteRecordsByFilterType::class, new DeleteRecordsByFilterRequest($currentEnv, 'access_records'));
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Invalid security token. Please try again.');
            $redirectEnv = $form->get('env')->getData() ?? $currentEnv;

            return $this->redirectToRoute('nowo_performance.access_records', ['env' => $redirectEnv]);
        }

        $data = $form->getData();
        $env = $data->env;
        if ('' === $env || !\is_string($env)) {
            $this->addFlash('error', 'Environment is required.');

            return $this->redirectToRoute('nowo_performance.access_records');
        }

        $startDateParam = $data->startDate;
        $endDateParam = $data->endDate;
        $routeName = $data->route;
        $statusCodeParam = $data->statusCode;
        $statusCode = null !== $statusCodeParam && '' !== $statusCodeParam ? (int) $statusCodeParam : null;
        $minQueryTime = null !== $data->minQueryTime && '' !== $data->minQueryTime ? (float) $data->minQueryTime : null;
        $maxQueryTime = null !== $data->maxQueryTime && '' !== $data->maxQueryTime ? (float) $data->maxQueryTime : null;
        $minMemoryUsage = null !== $data->minMemoryUsage && '' !== $data->minMemoryUsage ? (int) $data->minMemoryUsage : null;
        $maxMemoryUsage = null !== $data->maxMemoryUsage && '' !== $data->maxMemoryUsage ? (int) $data->maxMemoryUsage : null;

        $startDate = null;
        if (null !== $startDateParam && '' !== $startDateParam) {
            try {
                $startDate = new \DateTimeImmutable($startDateParam);
            } catch (\Exception $e) {
                $startDate = null;
            }
        }
        $endDate = null;
        if (null !== $endDateParam && '' !== $endDateParam) {
            try {
                $endDate = new \DateTimeImmutable($endDateParam);
            } catch (\Exception $e) {
                $endDate = null;
            }
        }

        if (null === $this->recordRepository) {
            $this->addFlash('error', 'Access records repository is not available.');

            return $this->redirectToRoute('nowo_performance.access_records', ['env' => $env]);
        }

        try {
            $deleted = $this->recordRepository->deleteByFilter(
                $env,
                $startDate,
                $endDate,
                $routeName,
                $statusCode,
                $minQueryTime,
                $maxQueryTime,
                $minMemoryUsage,
                $maxMemoryUsage,
            );
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error deleting records: '.$e->getMessage());
            $redirectParams = [
                'env' => $env,
                'start_date' => $startDateParam,
                'end_date' => $endDateParam,
                'route' => $routeName,
                'status_code' => $statusCodeParam,
            ];
            if (null !== $minQueryTime) {
                $redirectParams['min_query_time'] = $minQueryTime;
            }
            if (null !== $maxQueryTime) {
                $redirectParams['max_query_time'] = $maxQueryTime;
            }
            if (null !== $minMemoryUsage) {
                $redirectParams['min_memory_mb'] = round($minMemoryUsage / 1024 / 1024, 2);
            }
            if (null !== $maxMemoryUsage) {
                $redirectParams['max_memory_mb'] = round($maxMemoryUsage / 1024 / 1024, 2);
            }

            return $this->redirectToRoute('nowo_performance.access_records', $redirectParams);
        }

        if (null !== $this->cacheService) {
            $this->cacheService->clearStatistics($env);
        }

        $this->addFlash('success', \sprintf('Deleted %d access record(s) matching the filter.', $deleted));

        $redirectParams = ['env' => $env];
        if (null !== $startDateParam && '' !== $startDateParam) {
            $redirectParams['start_date'] = $startDateParam;
        }
        if (null !== $endDateParam && '' !== $endDateParam) {
            $redirectParams['end_date'] = $endDateParam;
        }
        if (null !== $routeName && '' !== $routeName) {
            $redirectParams['route'] = $routeName;
        }
        if (null !== $statusCode) {
            $redirectParams['status_code'] = $statusCode;
        }
        if (null !== $data->minQueryTime && '' !== $data->minQueryTime) {
            $redirectParams['min_query_time'] = $data->minQueryTime;
        }
        if (null !== $data->maxQueryTime && '' !== $data->maxQueryTime) {
            $redirectParams['max_query_time'] = $data->maxQueryTime;
        }
        if (null !== $data->minMemoryUsage && '' !== $data->minMemoryUsage) {
            $redirectParams['min_memory_mb'] = round((int) $data->minMemoryUsage / 1024 / 1024, 2);
        }
        if (null !== $data->maxMemoryUsage && '' !== $data->maxMemoryUsage) {
            $redirectParams['max_memory_mb'] = round((int) $data->maxMemoryUsage / 1024 / 1024, 2);
        }

        $from = $data->from ?? 'access_records';
        if ('access_statistics' === $from) {
            return $this->redirectToRoute('nowo_performance.access_statistics', $redirectParams);
        }

        return $this->redirectToRoute('nowo_performance.access_records', $redirectParams);
    }

    /**
     * Get driver name from connection (compatible with wrapped drivers).
     *
     * When the driver is wrapped with middleware (like AbstractDriverMiddleware),
     * the getName() method may not be available. This method handles both cases.
     *
     * @param \Doctrine\DBAL\Connection $connection The database connection
     *
     * @return string The driver name or 'unknown' if unable to determine
     */
    private function getDriverName(\Doctrine\DBAL\Connection $connection): string
    {
        try {
            $driver = $connection->getDriver();

            // Try direct getName() method (works for unwrapped drivers)
            if (method_exists($driver, 'getName')) {
                try {
                    /** @var callable $getName */
                    $getName = [$driver, 'getName'];

                    return (string) $getName();
                } catch (\Exception $e) {
                    // Continue to next method
                }
            }

            // If driver is wrapped with middleware, try to get the underlying driver
            // using reflection to access the wrapped driver
            $reflection = new \ReflectionClass($driver);

            // Check if it's a middleware wrapper (AbstractDriverMiddleware)
            if ($reflection->hasProperty('driver')) {
                try {
                    $driverProperty = $reflection->getProperty('driver');
                    $driverProperty->setAccessible(true);
                    $wrappedDriver = $driverProperty->getValue($driver);

                    if ($wrappedDriver instanceof \Doctrine\DBAL\Driver && method_exists($wrappedDriver, 'getName')) {
                        /** @var callable $getName */
                        $getName = [$wrappedDriver, 'getName'];

                        return (string) $getName();
                    }
                } catch (\Exception $e) {
                    // Continue to next method
                }
            }

            // Fallback: try to get driver name from database platform class name
            $platform = $connection->getDatabasePlatform();
            $platformClass = $platform::class;

            // Infer driver name from platform class name
            if (str_contains($platformClass, 'MySQL')) {
                return 'pdo_mysql';
            }
            if (str_contains($platformClass, 'PostgreSQL')) {
                return 'pdo_pgsql';
            }
            if (str_contains($platformClass, 'SQLite')) {
                return 'pdo_sqlite';
            }
            if (str_contains($platformClass, 'SQLServer')) {
                return 'pdo_sqlsrv';
            }

            return 'unknown';
        } catch (\Exception $e) {
            // If all methods fail, return 'unknown'
            return 'unknown';
        }
    }

    /**
     * Check if the route is ignored (literal name or glob pattern).
     *
     * Must match PerformanceMetricsSubscriber::isRouteIgnored logic so diagnose "current_route_ignored" is consistent.
     * Literal = exact or prefix with '_'; pattern = entry contains * or ? → fnmatch.
     *
     * @param mixed $routeName The route name from the request (can be null)
     *
     * @return bool True if the route should not be tracked
     */
    private function isRouteIgnored(mixed $routeName): bool
    {
        if (null === $routeName || '' === (string) $routeName) {
            return false;
        }
        $routeName = (string) $routeName;
        foreach ($this->ignoreRoutes as $ignored) {
            $ignored = (string) $ignored;
            if ('' === $ignored) {
                continue;
            }
            if (str_contains($ignored, '*') || str_contains($ignored, '?')) {
                if (fnmatch($ignored, $routeName, \FNM_NOESCAPE)) {
                    return true;
                }
                continue;
            }
            if ($routeName === $ignored) {
                return true;
            }
            if (str_starts_with($routeName, $ignored.'_')) {
                return true;
            }
        }

        return false;
    }
}
