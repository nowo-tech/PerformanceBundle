<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Service;

use Nowo\PerformanceBundle\Entity\RouteData;

/**
 * Service for advanced performance analysis.
 *
 * Provides correlation analysis, trend detection, efficiency metrics,
 * and automated recommendations based on performance data.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
class PerformanceAnalysisService
{
    /**
     * Analyze correlation between different metrics.
     *
     * @param array<RouteData> $routes Array of route data
     *
     * @return array<string, mixed> Correlation analysis
     */
    public function analyzeCorrelations(array $routes): array
    {
        if (empty($routes)) {
            return [
                'request_time_vs_query_time' => null,
                'request_time_vs_query_count' => null,
                'query_time_vs_query_count' => null,
                'memory_vs_request_time' => null,
                'access_count_vs_performance' => null,
            ];
        }

        $requestTimes = [];
        $queryTimes = [];
        $queryCounts = [];
        $memoryUsages = [];
        $accessCounts = [];

        foreach ($routes as $route) {
            if (null !== $route->getRequestTime()) {
                $requestTimes[] = $route->getRequestTime();
            }
            if (null !== $route->getQueryTime()) {
                $queryTimes[] = $route->getQueryTime();
            }
            if (null !== $route->getTotalQueries()) {
                $queryCounts[] = $route->getTotalQueries();
            }
            if (null !== $route->getMemoryUsage()) {
                $memoryUsages[] = $route->getMemoryUsage() / 1024 / 1024; // Convert to MB
            }
            $accessCounts[] = $route->getAccessCount();
        }

        return [
            'request_time_vs_query_time' => $this->calculateCorrelation($requestTimes, $queryTimes),
            'request_time_vs_query_count' => $this->calculateCorrelation($requestTimes, $queryCounts),
            'query_time_vs_query_count' => $this->calculateCorrelation($queryTimes, $queryCounts),
            'memory_vs_request_time' => $this->calculateCorrelation($memoryUsages, $requestTimes),
            'access_count_vs_performance' => $this->calculateCorrelation($accessCounts, $requestTimes),
        ];
    }

    /**
     * Calculate Pearson correlation coefficient between two arrays.
     *
     * @param array<float> $x First array
     * @param array<float> $y Second array
     *
     * @return array<string, mixed>|null Correlation data or null if insufficient data
     */
    private function calculateCorrelation(array $x, array $y): ?array
    {
        // Filter to only include pairs where both values exist
        $pairs = [];
        $minLength = min(\count($x), \count($y));
        for ($i = 0; $i < $minLength; ++$i) {
            if (isset($x[$i]) && isset($y[$i]) && null !== $x[$i] && null !== $y[$i]) {
                $pairs[] = ['x' => $x[$i], 'y' => $y[$i]];
            }
        }

        if (\count($pairs) < 2) {
            return null;
        }

        $n = \count($pairs);
        $sumX = array_sum(array_column($pairs, 'x'));
        $sumY = array_sum(array_column($pairs, 'y'));
        $sumXY = array_sum(array_map(static fn ($p) => $p['x'] * $p['y'], $pairs));
        $sumX2 = array_sum(array_map(static fn ($p) => $p['x'] * $p['x'], $pairs));
        $sumY2 = array_sum(array_map(static fn ($p) => $p['y'] * $p['y'], $pairs));

        $numerator = ($n * $sumXY) - ($sumX * $sumY);
        $denominator = sqrt((($n * $sumX2) - ($sumX * $sumX)) * (($n * $sumY2) - ($sumY * $sumY)));

        if (0 === $denominator) {
            return null;
        }

        $correlation = $numerator / $denominator;

        // Interpret correlation strength
        $strength = 'none';
        $interpretation = 'No correlation';
        if (abs($correlation) >= 0.9) {
            $strength = 'very_strong';
            $interpretation = abs($correlation) > 0 ? 'Very strong positive' : 'Very strong negative';
        } elseif (abs($correlation) >= 0.7) {
            $strength = 'strong';
            $interpretation = $correlation > 0 ? 'Strong positive' : 'Strong negative';
        } elseif (abs($correlation) >= 0.5) {
            $strength = 'moderate';
            $interpretation = $correlation > 0 ? 'Moderate positive' : 'Moderate negative';
        } elseif (abs($correlation) >= 0.3) {
            $strength = 'weak';
            $interpretation = $correlation > 0 ? 'Weak positive' : 'Weak negative';
        }

        return [
            'coefficient' => round($correlation, 4),
            'strength' => $strength,
            'interpretation' => $interpretation,
            'sample_size' => $n,
        ];
    }

    /**
     * Analyze efficiency metrics (e.g., request time vs query time ratio).
     *
     * @param array<RouteData> $routes Array of route data
     *
     * @return array<string, mixed> Efficiency analysis
     */
    public function analyzeEfficiency(array $routes): array
    {
        if (empty($routes)) {
            return [
                'avg_query_ratio' => null,
                'efficient_routes' => [],
                'inefficient_routes' => [],
                'query_bottleneck_routes' => [],
            ];
        }

        $efficientRoutes = [];
        $inefficientRoutes = [];
        $queryBottleneckRoutes = [];

        foreach ($routes as $route) {
            $requestTime = $route->getRequestTime();
            $queryTime = $route->getQueryTime();
            $queryCount = $route->getTotalQueries();

            if (null !== $requestTime && $requestTime > 0) {
                $queryRatio = null !== $queryTime && $queryTime > 0 ? ($queryTime / $requestTime) * 100 : 0;

                // Routes where queries take up most of the request time (potential bottleneck)
                if ($queryRatio > 80 && null !== $queryTime && $queryTime > 0.1) {
                    $queryBottleneckRoutes[] = [
                        'route' => $route,
                        'query_ratio' => round($queryRatio, 2),
                        'request_time' => $requestTime,
                        'query_time' => $queryTime,
                        'query_count' => $queryCount,
                    ];
                }

                // Efficient routes: low request time with reasonable query count
                if ($requestTime < 0.2 && (null === $queryCount || $queryCount < 10)) {
                    $efficientRoutes[] = [
                        'route' => $route,
                        'request_time' => $requestTime,
                        'query_count' => $queryCount,
                    ];
                }

                // Inefficient routes: high request time or high query count
                if ($requestTime > 1.0 || (null !== $queryCount && $queryCount > 50)) {
                    $inefficientRoutes[] = [
                        'route' => $route,
                        'request_time' => $requestTime,
                        'query_count' => $queryCount,
                        'query_time' => $queryTime,
                    ];
                }
            }
        }

        // Calculate average query ratio
        $queryRatios = [];
        foreach ($routes as $route) {
            $requestTime = $route->getRequestTime();
            $queryTime = $route->getQueryTime();
            if (null !== $requestTime && $requestTime > 0 && null !== $queryTime && $queryTime > 0) {
                $queryRatios[] = ($queryTime / $requestTime) * 100;
            }
        }

        $avgQueryRatio = !empty($queryRatios) ? array_sum($queryRatios) / \count($queryRatios) : null;

        // Sort by severity
        usort($queryBottleneckRoutes, static fn ($a, $b) => $b['query_ratio'] <=> $a['query_ratio']);
        usort($inefficientRoutes, static fn ($a, $b) => ($b['request_time'] ?? 0) <=> ($a['request_time'] ?? 0));

        return [
            'avg_query_ratio' => null !== $avgQueryRatio ? round($avgQueryRatio, 2) : null,
            'efficient_routes' => \array_slice($efficientRoutes, 0, 10), // Top 10
            'inefficient_routes' => \array_slice($inefficientRoutes, 0, 10), // Top 10 worst
            'query_bottleneck_routes' => \array_slice($queryBottleneckRoutes, 0, 10), // Top 10
        ];
    }

    /**
     * Generate automated recommendations based on performance data.
     *
     * @param array<RouteData>     $routes        Array of route data
     * @param array<string, mixed> $advancedStats Advanced statistics
     *
     * @return array<string, mixed> Recommendations
     */
    public function generateRecommendations(array $routes, array $advancedStats): array
    {
        $recommendations = [];

        // Analyze query count
        $queryCountStats = $advancedStats['query_count'] ?? [];
        if (!empty($queryCountStats) && isset($queryCountStats['mean'])) {
            $meanQueries = $queryCountStats['mean'];
            if ($meanQueries > 30) {
                $recommendations[] = [
                    'type' => 'query_optimization',
                    'priority' => 'high',
                    'title' => 'High Average Query Count',
                    'description' => \sprintf(
                        'Average query count is %.1f queries per request. Consider implementing eager loading, query batching, or caching to reduce database load.',
                        $meanQueries
                    ),
                    'action' => 'Review routes with high query counts and implement eager loading strategies.',
                ];
            } elseif ($meanQueries > 20) {
                $recommendations[] = [
                    'type' => 'query_optimization',
                    'priority' => 'medium',
                    'title' => 'Moderate Query Count',
                    'description' => \sprintf(
                        'Average query count is %.1f queries per request. Consider optimizing queries for frequently accessed routes.',
                        $meanQueries
                    ),
                    'action' => 'Identify routes with N+1 query problems and optimize them.',
                ];
            }
        }

        // Analyze request time
        $requestTimeStats = $advancedStats['request_time'] ?? [];
        if (!empty($requestTimeStats) && isset($requestTimeStats['p95'])) {
            $p95 = $requestTimeStats['p95'];
            if ($p95 > 2.0) {
                $recommendations[] = [
                    'type' => 'performance',
                    'priority' => 'high',
                    'title' => 'Slow 95th Percentile Response Time',
                    'description' => \sprintf(
                        '95%% of requests take less than %.2fs, but 5%% are slower. Focus optimization efforts on the slowest routes.',
                        $p95
                    ),
                    'action' => 'Review routes above P95 and identify bottlenecks.',
                ];
            }
        }

        // Analyze memory usage
        $memoryStats = $advancedStats['memory_usage'] ?? [];
        if (!empty($memoryStats) && isset($memoryStats['mean'])) {
            $meanMemory = $memoryStats['mean'];
            if ($meanMemory > 50) {
                $recommendations[] = [
                    'type' => 'memory',
                    'priority' => 'high',
                    'title' => 'High Memory Usage',
                    'description' => \sprintf(
                        'Average memory usage is %.1f MB per request. Consider implementing pagination, streaming, or reducing data loaded into memory.',
                        $meanMemory
                    ),
                    'action' => 'Review routes with high memory usage and optimize data loading.',
                ];
            }
        }

        // Analyze outliers
        foreach (['request_time', 'query_count', 'memory_usage'] as $metric) {
            $stats = $advancedStats[$metric] ?? [];
            if (!empty($stats) && isset($stats['outliers_count']) && $stats['outliers_count'] > 0) {
                $recommendations[] = [
                    'type' => 'outliers',
                    'priority' => 'medium',
                    'title' => \sprintf('%s Outliers Detected', ucfirst(str_replace('_', ' ', $metric))),
                    'description' => \sprintf(
                        '%d routes have outlier values for %s. These routes may need immediate attention.',
                        $stats['outliers_count'],
                        str_replace('_', ' ', $metric)
                    ),
                    'action' => 'Review routes with outlier values in the Advanced Statistics page.',
                ];
                break; // Only show one outlier recommendation
            }
        }

        // Analyze access distribution
        $accessStats = $advancedStats['access_count'] ?? [];
        if (!empty($accessStats) && isset($accessStats['std_dev'])) {
            $stdDev = $accessStats['std_dev'];
            $mean = $accessStats['mean'] ?? 0;
            if ($stdDev > $mean * 2) {
                $recommendations[] = [
                    'type' => 'traffic_distribution',
                    'priority' => 'low',
                    'title' => 'Uneven Traffic Distribution',
                    'description' => 'Traffic is heavily concentrated on a few routes. Consider optimizing high-traffic routes first for maximum impact.',
                    'action' => 'Focus optimization efforts on routes with highest access counts.',
                ];
            }
        }

        // Analyze correlation between query time and request time
        $correlations = $this->analyzeCorrelations($routes);
        $queryCorrelation = $correlations['request_time_vs_query_time'] ?? null;
        if (null !== $queryCorrelation && $queryCorrelation['coefficient'] > 0.7) {
            $recommendations[] = [
                'type' => 'query_bottleneck',
                'priority' => 'high',
                'title' => 'Query Time Strongly Correlated with Request Time',
                'description' => \sprintf(
                    'Query execution time shows a %s correlation (%.2f) with total request time. Database queries are likely the main performance bottleneck.',
                    $queryCorrelation['strength'],
                    $queryCorrelation['coefficient']
                ),
                'action' => 'Optimize database queries, add indexes, or implement query caching.',
            ];
        }

        return $recommendations;
    }

    /**
     * Analyze traffic distribution and identify hot paths.
     *
     * @param array<RouteData> $routes Array of route data
     *
     * @return array<string, mixed> Traffic distribution analysis
     */
    public function analyzeTrafficDistribution(array $routes): array
    {
        if (empty($routes)) {
            return [
                'total_accesses' => 0,
                'hot_paths' => [],
                'cold_paths' => [],
                'traffic_concentration' => null,
            ];
        }

        $totalAccesses = array_sum(array_map(static fn ($r) => $r->getAccessCount(), $routes));
        $sortedRoutes = $routes;
        usort($sortedRoutes, static fn ($a, $b) => $b->getAccessCount() <=> $a->getAccessCount());

        // Top 10% of routes by access count
        $top10Percent = (int) ceil(\count($routes) * 0.1);
        $hotPaths = \array_slice($sortedRoutes, 0, max(1, $top10Percent));

        // Bottom 10% of routes by access count
        $bottom10Percent = (int) ceil(\count($routes) * 0.1);
        $coldPaths = \array_slice($sortedRoutes, -max(1, $bottom10Percent));

        // Calculate traffic concentration (what % of traffic goes to top 10% of routes)
        $hotPathAccesses = array_sum(array_map(static fn ($r) => $r->getAccessCount(), $hotPaths));
        $trafficConcentration = $totalAccesses > 0 ? ($hotPathAccesses / $totalAccesses) * 100 : null;

        return [
            'total_accesses' => $totalAccesses,
            'hot_paths' => array_map(static fn ($r) => [
                'route' => $r,
                'access_count' => $r->getAccessCount(),
                'percentage' => $totalAccesses > 0 ? round(($r->getAccessCount() / $totalAccesses) * 100, 2) : 0,
            ], $hotPaths),
            'cold_paths' => array_map(static fn ($r) => [
                'route' => $r,
                'access_count' => $r->getAccessCount(),
            ], $coldPaths),
            'traffic_concentration' => null !== $trafficConcentration ? round($trafficConcentration, 2) : null,
        ];
    }
}
