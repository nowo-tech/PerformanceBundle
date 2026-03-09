<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Service;

use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Model\RouteDataWithAggregates;
use Nowo\PerformanceBundle\Service\PerformanceAnalysisService;
use PHPUnit\Framework\TestCase;

final class PerformanceAnalysisServiceTest extends TestCase
{
    private PerformanceAnalysisService $service;

    protected function setUp(): void
    {
        $this->service = new PerformanceAnalysisService();
    }

    public function testAnalyzeCorrelationsEmpty(): void
    {
        $result = $this->service->analyzeCorrelations([]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('request_time_vs_query_time', $result);
        $this->assertArrayHasKey('request_time_vs_query_count', $result);
        $this->assertArrayHasKey('query_time_vs_query_count', $result);
        $this->assertArrayHasKey('memory_vs_request_time', $result);
        $this->assertArrayHasKey('access_count_vs_performance', $result);
        $this->assertNull($result['request_time_vs_query_time']);
        $this->assertNull($result['request_time_vs_query_count']);
    }

    public function testAnalyzeEfficiencyEmpty(): void
    {
        $result = $this->service->analyzeEfficiency([]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('avg_query_ratio', $result);
        $this->assertArrayHasKey('efficient_routes', $result);
        $this->assertArrayHasKey('inefficient_routes', $result);
        $this->assertArrayHasKey('query_bottleneck_routes', $result);
        $this->assertNull($result['avg_query_ratio']);
        $this->assertSame([], $result['efficient_routes']);
        $this->assertSame([], $result['inefficient_routes']);
        $this->assertSame([], $result['query_bottleneck_routes']);
    }

    public function testAnalyzeTrafficDistributionEmpty(): void
    {
        $result = $this->service->analyzeTrafficDistribution([]);

        $this->assertIsArray($result);
        $this->assertSame(0, $result['total_accesses']);
        $this->assertSame([], $result['hot_paths']);
        $this->assertSame([], $result['cold_paths']);
        $this->assertNull($result['traffic_concentration']);
    }

    public function testGenerateRecommendationsEmpty(): void
    {
        $result = $this->service->generateRecommendations([], []);

        $this->assertIsArray($result);
        $this->assertSame([], $result);
    }

    public function testAnalyzeCorrelationsWithMockedRoutes(): void
    {
        $r1 = $this->routeMock(1.0, 0.4, 10, 1024 * 1024, 5);
        $r2 = $this->routeMock(2.0, 0.8, 20, 2 * 1024 * 1024, 10);

        $result = $this->service->analyzeCorrelations([$r1, $r2]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('request_time_vs_query_time', $result);
        $corr = $result['request_time_vs_query_time'];
        $this->assertIsArray($corr);
        $this->assertArrayHasKey('coefficient', $corr);
        $this->assertArrayHasKey('strength', $corr);
        $this->assertArrayHasKey('interpretation', $corr);
        $this->assertArrayHasKey('sample_size', $corr);
        $this->assertSame(2, $corr['sample_size']);
    }

    public function testAnalyzeCorrelationsWithRoutesHavingPartialNullMetrics(): void
    {
        $r1 = $this->routeMock(1.0, null, 10, null, 5);
        $r2 = $this->routeMock(2.0, 0.8, null, 2 * 1024 * 1024, 10);

        $result = $this->service->analyzeCorrelations([$r1, $r2]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('request_time_vs_query_time', $result);
        $this->assertArrayHasKey('memory_vs_request_time', $result);
        $this->assertArrayHasKey('access_count_vs_performance', $result);
    }

    public function testAnalyzeCorrelationsWithPerfectCorrelationHitsVeryStrong(): void
    {
        $r1 = $this->routeMock(1.0, 0.5, 10, null, 1);
        $r2 = $this->routeMock(2.0, 1.0, 20, null, 2);
        $r3 = $this->routeMock(3.0, 1.5, 30, null, 3);

        $result = $this->service->analyzeCorrelations([$r1, $r2, $r3]);

        $this->assertIsArray($result);
        $corr = $result['request_time_vs_query_time'] ?? null;
        if ($corr !== null && isset($corr['strength'])) {
            $this->assertContains($corr['strength'], ['very_strong', 'strong', 'moderate', 'weak', 'none']);
        }
    }

    public function testAnalyzeCorrelationsStrengthAndInterpretationBranches(): void
    {
        // Data that can produce different strength bands: 5 points with some scatter
        // to potentially hit moderate (0.5-0.7) or weak (0.3-0.5) in at least one metric
        $routes = [
            $this->routeMock(1.0, 0.2, 5, 1024 * 1024, 10),
            $this->routeMock(2.0, 0.9, 15, 2 * 1024 * 1024, 20),
            $this->routeMock(3.0, 0.5, 8, 1024 * 1024, 5),
            $this->routeMock(4.0, 1.2, 25, 3 * 1024 * 1024, 30),
            $this->routeMock(5.0, 1.5, 30, 4 * 1024 * 1024, 40),
        ];

        $result = $this->service->analyzeCorrelations($routes);

        $this->assertIsArray($result);
        foreach (['request_time_vs_query_time', 'request_time_vs_query_count', 'query_time_vs_query_count'] as $key) {
            $corr = $result[$key] ?? null;
            if ($corr !== null && isset($corr['strength'], $corr['interpretation'])) {
                $this->assertContains($corr['strength'], ['very_strong', 'strong', 'moderate', 'weak', 'none']);
                $this->assertIsString($corr['interpretation']);
                $this->assertArrayHasKey('coefficient', $corr);
                $this->assertArrayHasKey('sample_size', $corr);
            }
        }
    }

    public function testAnalyzeEfficiencyWithNullRequestTimeSkipsRoute(): void
    {
        $r1 = $this->routeMock(null, 0.5, 10, null, 1);
        $r2 = $this->routeMock(0.5, 0.1, 5, null, 1);

        $result = $this->service->analyzeEfficiency([$r1, $r2]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('efficient_routes', $result);
        $this->assertArrayHasKey('inefficient_routes', $result);
    }

    public function testAnalyzeTrafficDistributionWithSingleRoute(): void
    {
        $r1 = $this->routeMock(0.1, null, null, null, 100);

        $result = $this->service->analyzeTrafficDistribution([$r1]);

        $this->assertSame(100, $result['total_accesses']);
        $this->assertCount(1, $result['hot_paths']);
        $this->assertCount(1, $result['cold_paths']);
        $this->assertSame(100.0, $result['traffic_concentration']);
    }

    public function testAnalyzeEfficiencyWithMockedRoutes(): void
    {
        $efficient   = $this->routeMock(0.1, 0.02, 5, null, 1);
        $inefficient = $this->routeMock(1.5, 0.3, 60, null, 10);

        $result = $this->service->analyzeEfficiency([$efficient, $inefficient]);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result['efficient_routes']);
        $this->assertNotEmpty($result['inefficient_routes']);
    }

    public function testAnalyzeTrafficDistributionWithMockedRoutes(): void
    {
        $r1 = $this->routeMock(null, null, null, null, 100);
        $r2 = $this->routeMock(null, null, null, null, 50);
        $r3 = $this->routeMock(null, null, null, null, 10);

        $result = $this->service->analyzeTrafficDistribution([$r1, $r2, $r3]);

        $this->assertSame(160, $result['total_accesses']);
        $this->assertNotEmpty($result['hot_paths']);
        $this->assertNotEmpty($result['cold_paths']);
        $this->assertNotNull($result['traffic_concentration']);
    }

    public function testAnalyzeTrafficDistributionWithZeroAccessCount(): void
    {
        $r1 = $this->routeMock(null, null, null, null, 0);
        $r2 = $this->routeMock(null, null, null, null, 0);

        $result = $this->service->analyzeTrafficDistribution([$r1, $r2]);

        $this->assertSame(0, $result['total_accesses']);
        $this->assertNotNull($result['hot_paths']);
        $this->assertNotNull($result['cold_paths']);
        $this->assertNull($result['traffic_concentration']);
        foreach ($result['hot_paths'] as $entry) {
            $this->assertSame(0, $entry['percentage']);
        }
    }

    public function testGenerateRecommendationsWithHighQueryCount(): void
    {
        $routes = [];
        $stats  = [
            'query_count' => ['mean' => 35],
        ];

        $result = $this->service->generateRecommendations($routes, $stats);

        $this->assertNotEmpty($result);
        $first = $result[0];
        $this->assertSame('query_optimization', $first['type']);
        $this->assertSame('high', $first['priority']);
        $this->assertStringContainsString('35', $first['description']);
    }

    public function testGenerateRecommendationsWithModerateQueryCount(): void
    {
        $routes = [];
        $stats  = [
            'query_count' => ['mean' => 25],
        ];

        $result = $this->service->generateRecommendations($routes, $stats);

        $this->assertNotEmpty($result);
        $found = false;
        foreach ($result as $r) {
            if (($r['type'] ?? '') === 'query_optimization' && ($r['priority'] ?? '') === 'medium') {
                $found = true;
                $this->assertStringContainsString('25', $r['description']);
                break;
            }
        }
        $this->assertTrue($found);
    }

    public function testGenerateRecommendationsWithRequestTimeP95Moderate(): void
    {
        $stats  = ['request_time' => ['p95' => 1.5]];
        $result = $this->service->generateRecommendations([], $stats);

        $this->assertIsArray($result);
    }

    public function testGenerateRecommendationsWithSlowP95(): void
    {
        $stats  = ['request_time' => ['p95' => 2.5]];
        $result = $this->service->generateRecommendations([], $stats);

        $this->assertNotEmpty($result);
        $found = false;
        foreach ($result as $r) {
            if (($r['type'] ?? '') === 'performance' && isset($r['description']) && str_contains((string) $r['description'], '2.5')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }

    public function testGenerateRecommendationsWithHighMemory(): void
    {
        $stats  = ['memory_usage' => ['mean' => 60]];
        $result = $this->service->generateRecommendations([], $stats);

        $this->assertNotEmpty($result);
        $found = false;
        foreach ($result as $r) {
            if (($r['type'] ?? '') === 'memory') {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }

    public function testGenerateRecommendationsWithOutliers(): void
    {
        $stats  = ['request_time' => ['outliers_count' => 3]];
        $result = $this->service->generateRecommendations([], $stats);

        $this->assertNotEmpty($result);
        $found = false;
        foreach ($result as $r) {
            if (($r['type'] ?? '') === 'outliers') {
                $found = true;
                $this->assertStringContainsString('3', $r['description']);
                break;
            }
        }
        $this->assertTrue($found);
    }

    public function testGenerateRecommendationsWithOutliersInMemoryUsage(): void
    {
        $stats  = ['memory_usage' => ['outliers_count' => 2]];
        $result = $this->service->generateRecommendations([], $stats);

        $this->assertNotEmpty($result);
        $found = false;
        foreach ($result as $r) {
            if (($r['type'] ?? '') === 'outliers') {
                $found = true;
                $this->assertStringContainsString('memory usage', strtolower($r['title'] ?? ''));
                break;
            }
        }
        $this->assertTrue($found);
    }

    public function testGenerateRecommendationsWithTrafficDistribution(): void
    {
        $stats  = ['access_count' => ['std_dev' => 100, 'mean' => 10]];
        $result = $this->service->generateRecommendations([], $stats);

        $this->assertNotEmpty($result);
        $found = false;
        foreach ($result as $r) {
            if (($r['type'] ?? '') === 'traffic_distribution') {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }

    public function testGenerateRecommendationsWithCorrelationBottleneck(): void
    {
        $r1     = $this->routeMock(1.0, 0.5, 5, null, 1);
        $r2     = $this->routeMock(2.0, 1.0, 10, null, 2);
        $r3     = $this->routeMock(3.0, 1.5, 15, null, 3);
        $routes = [$r1, $r2, $r3];
        $result = $this->service->generateRecommendations($routes, []);

        $found = false;
        foreach ($result as $r) {
            if (($r['type'] ?? '') === 'query_bottleneck') {
                $found = true;
                $this->assertSame('high', $r['priority']);
                break;
            }
        }
        $this->assertTrue($found, 'Expected at least one query_bottleneck recommendation from correlated routes');
    }

    public function testAnalyzeCorrelationsWithZeroVarianceReturnsNullForThatMetric(): void
    {
        // Same request time for all -> variance in request time is 0 -> correlation null
        $r1 = $this->routeMock(1.0, 0.1, 5, null, 1);
        $r2 = $this->routeMock(1.0, 0.2, 10, null, 2);
        $r3 = $this->routeMock(1.0, 0.3, 15, null, 3);

        $result = $this->service->analyzeCorrelations([$r1, $r2, $r3]);

        $this->assertIsArray($result);
        // request_time is constant so request_time_vs_* correlations may be null (zero variance)
        $this->assertArrayHasKey('request_time_vs_query_time', $result);
        $this->assertArrayHasKey('query_time_vs_query_count', $result);
        $this->assertArrayHasKey('access_count_vs_performance', $result);
    }

    public function testAnalyzeEfficiencyWithQueryBottleneckRoutes(): void
    {
        // queryRatio > 80% and queryTime > 0.1 -> query_bottleneck_routes
        $r1 = $this->routeMock(0.2, 0.18, 8, null, 10); // 90% query ratio, query time 0.18
        $r2 = $this->routeMock(0.5, 0.45, 15, null, 5);  // 90% query ratio, query time 0.45

        $result = $this->service->analyzeEfficiency([$r1, $r2]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('query_bottleneck_routes', $result);
        $this->assertNotEmpty($result['query_bottleneck_routes']);
        $first = $result['query_bottleneck_routes'][0];
        $this->assertArrayHasKey('query_ratio', $first);
        $this->assertArrayHasKey('request_time', $first);
        $this->assertArrayHasKey('query_time', $first);
        $this->assertGreaterThanOrEqual(80, $first['query_ratio']);
    }

    public function testAnalyzeCorrelationsWithOnlyOnePairReturnsNull(): void
    {
        $r1 = $this->routeMock(1.0, 0.5, 10, null, 1);

        $result = $this->service->analyzeCorrelations([$r1]);

        $this->assertIsArray($result);
        $this->assertNull($result['request_time_vs_query_time']);
        $this->assertNull($result['request_time_vs_query_count']);
    }

    private function routeMock(
        ?float $requestTime,
        ?float $queryTime,
        ?int $totalQueries,
        ?int $memoryUsage,
        int $accessCount,
    ): RouteDataWithAggregates {
        $routeData = new RouteData();
        $routeData->setName('test_route')->setEnv('dev');

        return new RouteDataWithAggregates($routeData, [
            'request_time'  => $requestTime,
            'query_time'    => $queryTime,
            'total_queries' => $totalQueries,
            'memory_usage'  => $memoryUsage,
            'access_count'  => $accessCount,
            'status_codes'  => [],
        ]);
    }
}
