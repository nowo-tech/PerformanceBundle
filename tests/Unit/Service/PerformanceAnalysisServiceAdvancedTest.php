<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Service;

use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Service\PerformanceAnalysisService;
use PHPUnit\Framework\TestCase;

/**
 * Advanced tests for PerformanceAnalysisService.
 */
final class PerformanceAnalysisServiceAdvancedTest extends TestCase
{
    private PerformanceAnalysisService $service;

    protected function setUp(): void
    {
        $this->service = new PerformanceAnalysisService();
    }

    public function testAnalyzeEfficiencyWithEmptyArray(): void
    {
        $result = $this->service->analyzeEfficiency([]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('avg_query_ratio', $result);
        $this->assertArrayHasKey('efficient_routes', $result);
        $this->assertArrayHasKey('inefficient_routes', $result);
        $this->assertArrayHasKey('query_bottleneck_routes', $result);
        $this->assertNull($result['avg_query_ratio']);
        $this->assertEmpty($result['efficient_routes']);
        $this->assertEmpty($result['inefficient_routes']);
        $this->assertEmpty($result['query_bottleneck_routes']);
    }

    public function testAnalyzeEfficiencyWithEfficientRoutes(): void
    {
        $route = new RouteData();
        $route->setName('app_home');
        $route->setRequestTime(0.1);
        $route->setTotalQueries(5);
        $route->setQueryTime(0.05);

        $result = $this->service->analyzeEfficiency([$route]);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result['efficient_routes']);
        $this->assertEmpty($result['inefficient_routes']);
    }

    public function testAnalyzeEfficiencyWithInefficientRoutes(): void
    {
        $route = new RouteData();
        $route->setName('app_slow');
        $route->setRequestTime(2.0);
        $route->setTotalQueries(60);
        $route->setQueryTime(1.5);

        $result = $this->service->analyzeEfficiency([$route]);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result['inefficient_routes']);
    }

    public function testAnalyzeEfficiencyWithQueryBottleneckRoutes(): void
    {
        $route = new RouteData();
        $route->setName('app_bottleneck');
        $route->setRequestTime(1.0);
        $route->setTotalQueries(50);
        $route->setQueryTime(0.9); // 90% of request time

        $result = $this->service->analyzeEfficiency([$route]);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result['query_bottleneck_routes']);
        $this->assertGreaterThan(80, $result['query_bottleneck_routes'][0]['query_ratio']);
    }

    public function testAnalyzeEfficiencyWithNullValues(): void
    {
        $route = new RouteData();
        $route->setName('app_null');
        $route->setRequestTime(null);
        $route->setTotalQueries(null);
        $route->setQueryTime(null);

        $result = $this->service->analyzeEfficiency([$route]);

        $this->assertIsArray($result);
        $this->assertEmpty($result['efficient_routes']);
        $this->assertEmpty($result['inefficient_routes']);
        $this->assertEmpty($result['query_bottleneck_routes']);
    }

    public function testAnalyzeEfficiencyWithZeroRequestTime(): void
    {
        $route = new RouteData();
        $route->setName('app_zero');
        $route->setRequestTime(0.0);
        $route->setTotalQueries(10);
        $route->setQueryTime(0.1);

        $result = $this->service->analyzeEfficiency([$route]);

        $this->assertIsArray($result);
        // Routes with zero request time should not be categorized
        $this->assertEmpty($result['efficient_routes']);
    }

    public function testAnalyzeEfficiencyCalculatesAverageQueryRatio(): void
    {
        $route1 = new RouteData();
        $route1->setName('route1');
        $route1->setRequestTime(1.0);
        $route1->setQueryTime(0.5); // 50%

        $route2 = new RouteData();
        $route2->setName('route2');
        $route2->setRequestTime(1.0);
        $route2->setQueryTime(0.3); // 30%

        $result = $this->service->analyzeEfficiency([$route1, $route2]);

        $this->assertIsArray($result);
        $this->assertNotNull($result['avg_query_ratio']);
        $this->assertEquals(40.0, $result['avg_query_ratio']); // (50 + 30) / 2
    }

    public function testGenerateRecommendationsWithHighQueryCount(): void
    {
        $route = new RouteData();
        $route->setName('app_high_queries');
        $route->setTotalQueries(40);

        $advancedStats = [
            'query_count' => [
                'mean' => 35.0,
            ],
        ];

        $result = $this->service->generateRecommendations([$route], $advancedStats);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertEquals('query_optimization', $result[0]['type']);
        $this->assertEquals('high', $result[0]['priority']);
    }

    public function testGenerateRecommendationsWithSlowP95(): void
    {
        $route = new RouteData();
        $route->setName('app_slow');

        $advancedStats = [
            'request_time' => [
                'percentiles' => [95 => 2.5],
            ],
        ];

        $result = $this->service->generateRecommendations([$route], $advancedStats);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertEquals('performance', $result[0]['type']);
        $this->assertEquals('high', $result[0]['priority']);
    }

    public function testGenerateRecommendationsWithHighMemoryUsage(): void
    {
        $route = new RouteData();
        $route->setName('app_memory');

        $advancedStats = [
            'memory_usage' => [
                'mean' => 60.0,
            ],
        ];

        $result = $this->service->generateRecommendations([$route], $advancedStats);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertEquals('memory', $result[0]['type']);
        $this->assertEquals('high', $result[0]['priority']);
    }

    public function testGenerateRecommendationsWithOutliers(): void
    {
        $route = new RouteData();
        $route->setName('app_outlier');

        $advancedStats = [
            'request_time' => [
                'outliers_count' => 5,
            ],
        ];

        $result = $this->service->generateRecommendations([$route], $advancedStats);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertEquals('outliers', $result[0]['type']);
    }

    public function testGenerateRecommendationsWithStrongCorrelation(): void
    {
        $route1 = new RouteData();
        $route1->setName('route1');
        $route1->setRequestTime(0.5);
        $route1->setQueryTime(0.4);

        $route2 = new RouteData();
        $route2->setName('route2');
        $route2->setRequestTime(1.0);
        $route2->setQueryTime(0.8);

        $route3 = new RouteData();
        $route3->setName('route3');
        $route3->setRequestTime(1.5);
        $route3->setQueryTime(1.2);

        $advancedStats = [];

        $result = $this->service->generateRecommendations([$route1, $route2, $route3], $advancedStats);

        $this->assertIsArray($result);
        // May contain query_bottleneck recommendation if correlation is strong
    }

    public function testAnalyzeTrafficDistributionWithEmptyArray(): void
    {
        $result = $this->service->analyzeTrafficDistribution([]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('total_accesses', $result);
        $this->assertArrayHasKey('hot_paths', $result);
        $this->assertArrayHasKey('cold_paths', $result);
        $this->assertArrayHasKey('traffic_concentration', $result);
        $this->assertEquals(0, $result['total_accesses']);
        $this->assertEmpty($result['hot_paths']);
        $this->assertEmpty($result['cold_paths']);
        $this->assertNull($result['traffic_concentration']);
    }

    public function testAnalyzeTrafficDistributionWithSingleRoute(): void
    {
        $route = new RouteData();
        $route->setName('app_home');
        $route->setAccessCount(100);

        $result = $this->service->analyzeTrafficDistribution([$route]);

        $this->assertIsArray($result);
        $this->assertEquals(100, $result['total_accesses']);
        $this->assertNotEmpty($result['hot_paths']);
        $this->assertNotEmpty($result['cold_paths']);
    }

    public function testAnalyzeTrafficDistributionWithMultipleRoutes(): void
    {
        $route1 = new RouteData();
        $route1->setName('route1');
        $route1->setAccessCount(1000);

        $route2 = new RouteData();
        $route2->setName('route2');
        $route2->setAccessCount(100);

        $route3 = new RouteData();
        $route3->setName('route3');
        $route3->setAccessCount(10);

        $result = $this->service->analyzeTrafficDistribution([$route1, $route2, $route3]);

        $this->assertIsArray($result);
        $this->assertEquals(1110, $result['total_accesses']);
        $this->assertNotEmpty($result['hot_paths']);
        $this->assertNotEmpty($result['cold_paths']);
        $this->assertNotNull($result['traffic_concentration']);
        $this->assertGreaterThan(0, $result['traffic_concentration']);
    }

    public function testAnalyzeTrafficDistributionWithZeroAccessCounts(): void
    {
        $route = new RouteData();
        $route->setName('app_new');
        $route->setAccessCount(0);

        $result = $this->service->analyzeTrafficDistribution([$route]);

        $this->assertIsArray($result);
        $this->assertEquals(0, $result['total_accesses']);
        $this->assertNull($result['traffic_concentration']);
    }

    public function testAnalyzeTrafficDistributionCalculatesPercentages(): void
    {
        $route1 = new RouteData();
        $route1->setName('route1');
        $route1->setAccessCount(1000);

        $route2 = new RouteData();
        $route2->setName('route2');
        $route2->setAccessCount(100);

        $result = $this->service->analyzeTrafficDistribution([$route1, $route2]);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result['hot_paths']);
        if (!empty($result['hot_paths'])) {
            $this->assertArrayHasKey('percentage', $result['hot_paths'][0]);
            $this->assertGreaterThan(0, $result['hot_paths'][0]['percentage']);
        }
    }

    public function testAnalyzeTrafficDistributionWithManyRoutes(): void
    {
        $routes = [];
        for ($i = 1; $i <= 20; ++$i) {
            $route = new RouteData();
            $route->setName("route{$i}");
            $route->setAccessCount($i * 10);
            $routes[] = $route;
        }

        $result = $this->service->analyzeTrafficDistribution($routes);

        $this->assertIsArray($result);
        $this->assertEquals(2100, $result['total_accesses']); // Sum of 10+20+...+200
        $this->assertNotEmpty($result['hot_paths']);
        $this->assertNotEmpty($result['cold_paths']);
        // Top 10% = 2 routes, bottom 10% = 2 routes
        $this->assertCount(2, $result['hot_paths']);
        $this->assertCount(2, $result['cold_paths']);
    }
}
