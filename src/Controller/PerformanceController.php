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
use Nowo\PerformanceBundle\Form\PerformanceFiltersType;
use Nowo\PerformanceBundle\Form\ReviewRouteDataType;
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
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Controller for displaying performance metrics.
 *
 * This controller provides a web interface to view and filter performance data.
 * The route path and prefix can be configured via bundle configuration.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
class PerformanceController extends AbstractController
{
    /**
     * Constructor.
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

        // Get routes with advanced filtering
        try {
            $routes = $this->metricsService->getRepository()->findWithFilters($env, $filters, $sortBy, $order, $limit);
        } catch (\Exception $e) {
            // If there's an error getting routes, return empty list
            $routes = [];
        }

        // Calculate statistics (with caching)
        // Reuse the already fetched routes if they match the statistics requirements
        try {
            $stats = null;
            if (null !== $this->cacheService) {
                $stats = $this->cacheService->getCachedStatistics($env);
            }
            if (null === $stats) {
                // If we already have all routes (no filters or limit), reuse them for statistics
                // Otherwise, fetch all routes for statistics
                if (empty($filters) && (null === $limit || $limit >= 1000)) {
                    $allRoutes = $routes;
                } else {
                    $allRoutes = $this->metricsService->getRoutesByEnvironment($env);
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
                if ($route instanceof RouteData && !$route->isReviewed()) {
                    $reviewForm = $this->createForm(ReviewRouteDataType::class, null, [
                        'csrf_token_id' => 'review_performance_record_'.$route->getId(),
                    ]);
                    $reviewForms[$route->getId()] = $reviewForm->createView();
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
            'useComponents' => $useComponents,
            'missingDependencies' => $missingDependencies,
            'dependencyStatus' => $dependencyStatus,
            'enableRecordManagement' => $this->enableRecordManagement,
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
     * Get sort value for a route.
     *
     * @param RouteData $route  The route data
     * @param string    $sortBy The field to sort by
     *
     * @return float|int|string|null The sort value
     */
    private function getSortValue($route, string $sortBy): float|int|string|null
    {
        return match ($sortBy) {
            'name' => $route->getName() ?? '',
            'requestTime' => $route->getRequestTime() ?? 0.0,
            'queryTime' => $route->getQueryTime() ?? 0.0,
            'totalQueries' => $route->getTotalQueries() ?? 0,
            'accessCount' => $route->getAccessCount() ?? 1,
            'env' => $route->getEnv() ?? '',
            default => $route->getRequestTime() ?? 0.0,
        };
    }

    /**
     * Calculate statistics for routes.
     *
     * @param array $routes Array of RouteData entities
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

        $requestTimes = array_filter(array_map(static fn ($r) => $r->getRequestTime(), $routes));
        $queryTimes = array_filter(array_map(static fn ($r) => $r->getQueryTime(), $routes));
        $queryCounts = array_filter(array_map(static fn ($r) => $r->getTotalQueries(), $routes));

        return [
            'total_routes' => \count($routes),
            'total_queries' => array_sum($queryCounts),
            'avg_request_time' => !empty($requestTimes) ? array_sum($requestTimes) / \count($requestTimes) : 0.0,
            'avg_query_time' => !empty($queryTimes) ? array_sum($queryTimes) / \count($queryTimes) : 0.0,
            'max_request_time' => !empty($requestTimes) ? max($requestTimes) : 0.0,
            'max_query_time' => !empty($queryTimes) ? max($queryTimes) : 0.0,
            'max_queries' => !empty($queryCounts) ? max($queryCounts) : 0,
        ];
    }

    /**
     * Calculate advanced statistics for routes.
     *
     * @param array $routes Array of RouteData entities
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

        $requestTimes = array_values(array_filter(array_map(static fn ($r) => $r->getRequestTime(), $routes), static fn ($v) => null !== $v));
        $queryTimes = array_values(array_filter(array_map(static fn ($r) => $r->getQueryTime(), $routes), static fn ($v) => null !== $v));
        $queryCounts = array_values(array_filter(array_map(static fn ($r) => $r->getTotalQueries(), $routes), static fn ($v) => null !== $v));
        $memoryUsages = array_values(array_filter(array_map(static fn ($r) => $r->getMemoryUsage() ? $r->getMemoryUsage() / 1024 / 1024 : null, $routes), static fn ($v) => null !== $v)); // Convert to MB
        $accessCounts = array_values(array_filter(array_map(static fn ($r) => $r->getAccessCount(), $routes), static fn ($v) => null !== $v));

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

        // Get environment
        $env = $request->query->get('env') ?? $this->getParameter('kernel.environment');

        // Get all routes for statistics
        try {
            $routes = $this->metricsService->getRepository()->findAllForStatistics($env);
        } catch (\Exception $e) {
            $routes = [];
        }

        // Calculate advanced statistics
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

        // Get available environments
        try {
            $environments = $this->getAvailableEnvironments();
        } catch (\Exception $e) {
            $environments = ['dev', 'test', 'prod'];
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
        ]);
    }

    /**
     * Get routes that need attention (outliers and worst performers).
     *
     * @param array $routes        Array of RouteData entities
     * @param array $advancedStats Advanced statistics
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
        // Try to get from cache first
        if (null !== $this->cacheService) {
            $cached = $this->cacheService->getCachedEnvironments();
            if (null !== $cached) {
                return $cached;
            }
        }

        try {
            $environments = $this->metricsService->getRepository()->getDistinctEnvironments();

            // If no environments found in database, use configured allowed environments
            // This ensures the filter always has options even when no data is recorded yet
            if (empty($environments) && !empty($this->allowedEnvironments)) {
                $environments = $this->allowedEnvironments;
            }

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

            // Cache the result
            if (null !== $this->cacheService) {
                $this->cacheService->cacheEnvironments($environments);
            }

            return $environments;
        } catch (\Exception $e) {
            // Fallback to configured allowed environments if repository query fails
            $default = !empty($this->allowedEnvironments) ? $this->allowedEnvironments : ['dev', 'test', 'prod'];

            // Cache the fallback
            if (null !== $this->cacheService) {
                $this->cacheService->cacheEnvironments($default);
            }

            return $default;
        }
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
                'Updated At',
            ]);

            // Get routes
            try {
                $routes = $this->metricsService->getRepository()->findWithFilters($env, $filters, 'requestTime', 'DESC', null);
            } catch (\Exception $e) {
                $routes = [];
            }

            // Write data rows
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
                    $route->getUpdatedAt()?->format('Y-m-d H:i:s') ?? '',
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

        // Get routes
        try {
            $routes = $this->metricsService->getRepository()->findWithFilters($env, $filters, 'requestTime', 'DESC', null);
        } catch (\Exception $e) {
            $routes = [];
        }

        // Convert routes to array
        $data = array_map(static function (RouteData $route) {
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
                'updated_at' => $route->getUpdatedAt()?->format('c'),
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
            'records_table_name' => null,
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

        // Check records table if enabled
        if ($this->enableAccessRecords && null !== $this->recordRepository) {
            try {
                $entityManager = $this->recordRepository->getEntityManager();
                $metadata = $entityManager->getMetadataFactory()->getMetadataFor('Nowo\PerformanceBundle\Entity\RouteDataRecord');
                $recordsTableName = method_exists($metadata, 'getTableName')
                    ? $metadata->getTableName()
                    : ($metadata->table['name'] ?? $this->tableStatusChecker?->getTableName().'_records');
                $connection = $entityManager->getConnection();
                // Get schema manager (compatible with DBAL 2.x and 3.x)
                if (method_exists($connection, 'createSchemaManager')) {
                    $schemaManager = $connection->createSchemaManager();
                } else {
                    /** @var callable $getSchemaManager */
                    $getSchemaManager = [$connection, 'getSchemaManager'];
                    $schemaManager = $getSchemaManager();
                }
                $tableStatus['records_table_exists'] = $schemaManager->tablesExist([$recordsTableName]);
                $tableStatus['records_table_name'] = $recordsTableName;
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
                    ->orderBy('r.updatedAt', 'DESC')
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();

                if (null !== $firstRecord) {
                    $dataStatus['first_record_date'] = $firstRecord->getCreatedAt();
                }
                if (null !== $lastRecord) {
                    $dataStatus['last_record_date'] = $lastRecord->getUpdatedAt();
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

        // 6. Route Tracking Check
        $routeTracking = [
            'ignored_routes_count' => \count($this->ignoreRoutes),
            'ignored_routes' => $this->ignoreRoutes,
            'current_route' => $request->attributes->get('_route'),
            'current_route_ignored' => \in_array($request->attributes->get('_route'), $this->ignoreRoutes, true),
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
            if (null !== $container && $container->has('event_dispatcher')) {
                $eventDispatcher = $container->get('event_dispatcher');
                if (method_exists($eventDispatcher, 'getListeners')) {
                    $listeners = $eventDispatcher->getListeners(KernelEvents::REQUEST);
                    foreach ($listeners as $listener) {
                        if (\is_array($listener) && isset($listener[0])) {
                            $listenerClass = \get_class($listener[0]);
                            if (str_contains($listenerClass, 'PerformanceMetricsSubscriber')) {
                                $subscriberStatus['subscriber_registered'] = true;
                                break;
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $subscriberStatus['subscriber_error'] = $e->getMessage();
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

        // Validate CSRF token
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('clear_performance_data', $token)) {
            $this->addFlash('error', 'Invalid security token. Please try again.');
            // Redirect to referer if available
            $referer = $request->headers->get('referer');
            if ($referer && filter_var($referer, \FILTER_VALIDATE_URL)) {
                return $this->redirect($referer);
            }

            return $this->redirectToRoute('nowo_performance.index');
        }

        try {
            $repository = $this->metricsService->getRepository();
            $env = $request->request->get('env');

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
                $env = $request->request->get('env');
                if ($env) {
                    $this->cacheService->clearStatistics($env);
                } else {
                    // Clear all environments if no specific env provided
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

        // Redirect to referer if available, otherwise to the dashboard
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

        // Validate CSRF token
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_performance_record_'.$id, $token)) {
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

        $routes = $this->metricsService->getRepository()->findWithFilters($env, $filters, 'createdAt', 'ASC', null);

        // Group by date and calculate averages
        $groupedData = [];
        foreach ($routes as $route) {
            $date = $route->getCreatedAt()?->format('Y-m-d') ?? $route->getUpdatedAt()?->format('Y-m-d') ?? date('Y-m-d');

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
                throw $this->createAccessDeniedException('You do not have permission to access temporal access statistics.');
            }
        }

        $env = $request->query->get('env') ?? $this->getParameter('kernel.environment');
        $startDateParam = $request->query->get('start_date');
        $endDateParam = $request->query->get('end_date');

        $startDate = $startDateParam ? new \DateTimeImmutable($startDateParam) : (new \DateTimeImmutable())->modify('-7 days')->setTime(0, 0, 0);
        $endDate = $endDateParam ? new \DateTimeImmutable($endDateParam) : new \DateTimeImmutable();

        $statisticsByHour = [];
        $totalAccessCount = 0;

        if (null !== $this->recordRepository) {
            try {
                $statisticsByHour = $this->recordRepository->getStatisticsByHour($env, $startDate, $endDate);
                $totalAccessCount = $this->recordRepository->getTotalAccessCount($env, $startDate, $endDate);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error fetching hourly access statistics: '.$e->getMessage());
                $statisticsByHour = [];
            }
        } else {
            $this->addFlash('warning', 'RouteDataRecordRepository is not available. Cannot fetch temporal access statistics.');
        }

        // Get available environments
        try {
            $environments = $this->getAvailableEnvironments();
        } catch (\Exception $e) {
            $environments = ['dev', 'test', 'prod'];
        }

        return $this->render('@NowoPerformanceBundle/Performance/access_statistics.html.twig', [
            'statistics_by_hour' => $statisticsByHour,
            'total_access_count' => $totalAccessCount,
            'environment' => $env,
            'environments' => $environments,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'template' => $this->template,
            'dateTimeFormat' => $this->dateTimeFormat,
            'dateFormat' => $this->dateFormat,
        ]);
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
}
