<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Controller;

use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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
     * @param bool $enabled Whether the performance dashboard is enabled
     * @param array $requiredRoles Required roles to access the dashboard
     */
    public function __construct(
        private readonly PerformanceMetricsService $metricsService,
        #[Autowire('%nowo_performance.dashboard.enabled%')]
        private readonly bool $enabled,
        #[Autowire('%nowo_performance.dashboard.roles%')]
        private readonly array $requiredRoles = []
    ) {
    }

    /**
     * Display performance metrics dashboard.
     *
     * Shows a list of all tracked routes with filtering capabilities.
     * The view can be overridden by creating a template at
     * `templates/bundles/NowoPerformanceBundle/Performance/index.html.twig`
     *
     * @param Request $request The HTTP request
     * @return Response The HTTP response
     */
    #[Route(
        path: '',
        name: 'nowo_performance_index',
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

        $env = $request->query->get('env') ?? $this->getParameter('kernel.environment');
        $routeName = $request->query->get('route');
        $sortBy = $request->query->get('sort', 'requestTime');
        $order = $request->query->get('order', 'DESC');
        $limit = (int) $request->query->get('limit', 100);

        // Get all routes for the environment
        try {
            $routes = $this->metricsService->getRoutesByEnvironment($env);
        } catch (\Exception $e) {
            // If there's an error getting routes, return empty list
            $routes = [];
        }

        // Filter by route name if provided
        if ($routeName !== null && $routeName !== '') {
            $routes = array_filter($routes, function ($route) use ($routeName) {
                return $route->getName() !== null && stripos($route->getName(), $routeName) !== false;
            });
        }

        // Sort routes
        usort($routes, function ($a, $b) use ($sortBy, $order) {
            $valueA = $this->getSortValue($a, $sortBy);
            $valueB = $this->getSortValue($b, $sortBy);

            if ($valueA === $valueB) {
                return 0;
            }

            $result = $valueA <=> $valueB;

            return $order === 'ASC' ? $result : -$result;
        });

        // Apply limit
        $routes = \array_slice($routes, 0, $limit);

        // Calculate statistics
        try {
            $allRoutes = $this->metricsService->getRoutesByEnvironment($env);
            $stats = $this->calculateStats($allRoutes);
        } catch (\Exception $e) {
            $stats = $this->calculateStats([]);
        }

        try {
            $environments = $this->getAvailableEnvironments();
        } catch (\Exception $e) {
            $environments = ['dev', 'test', 'prod'];
        }

        return $this->render('@NowoPerformanceBundle/Performance/index.html.twig', [
            'routes' => $routes,
            'stats' => $stats,
            'environment' => $env,
            'currentRoute' => $routeName,
            'sortBy' => $sortBy,
            'order' => $order,
            'limit' => $limit,
            'environments' => $environments,
        ]);
    }

    /**
     * Get sort value for a route.
     *
     * @param \Nowo\PerformanceBundle\Entity\RouteData $route The route data
     * @param string $sortBy The field to sort by
     * @return float|int|string|null The sort value
     */
    private function getSortValue($route, string $sortBy): float|int|string|null
    {
        return match ($sortBy) {
            'name' => $route->getName() ?? '',
            'requestTime' => $route->getRequestTime() ?? 0.0,
            'queryTime' => $route->getQueryTime() ?? 0.0,
            'totalQueries' => $route->getTotalQueries() ?? 0,
            'env' => $route->getEnv() ?? '',
            default => $route->getRequestTime() ?? 0.0,
        };
    }

    /**
     * Calculate statistics for routes.
     *
     * @param array $routes Array of RouteData entities
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

        $requestTimes = array_filter(array_map(fn($r) => $r->getRequestTime(), $routes));
        $queryTimes = array_filter(array_map(fn($r) => $r->getQueryTime(), $routes));
        $queryCounts = array_filter(array_map(fn($r) => $r->getTotalQueries(), $routes));

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
     * Get available environments from routes.
     *
     * @return string[] Array of environment names
     */
    private function getAvailableEnvironments(): array
    {
        try {
            return $this->metricsService->getRepository()->getDistinctEnvironments();
        } catch (\Exception $e) {
            // Fallback to default environments if repository query fails
            return ['dev', 'test', 'prod'];
        }
    }
}
