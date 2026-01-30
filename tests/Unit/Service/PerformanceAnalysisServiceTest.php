<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Service;

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

    public function testAnalyzeEfficiencyWithMockedRoutes(): void
    {
        $efficient = $this->routeMock(0.1, 0.02, 5, null, 1);
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

    public function testGenerateRecommendationsWithHighQueryCount(): void
    {
        $routes = [];
        $stats = [
            'query_count' => ['mean' => 35],
        ];

        $result = $this->service->generateRecommendations($routes, $stats);

        $this->assertNotEmpty($result);
        $first = $result[0];
        $this->assertSame('query_optimization', $first['type']);
        $this->assertSame('high', $first['priority']);
        $this->assertStringContainsString('35', $first['description']);
    }

    public function testGenerateRecommendationsWithSlowP95(): void
    {
        $stats = ['request_time' => ['p95' => 2.5]];
        $result = $this->service->generateRecommendations([], $stats);

        $this->assertNotEmpty($result);
        $found = false;
        foreach ($result as $r) {
            if (($r['type'] ?? '') === 'performance' && isset($r['description']) && str_contains($r['description'], '2.5')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }

    public function testGenerateRecommendationsWithHighMemory(): void
    {
        $stats = ['memory_usage' => ['mean' => 60]];
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
        $stats = ['request_time' => ['outliers_count' => 3]];
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

    public function testGenerateRecommendationsWithTrafficDistribution(): void
    {
        $stats = ['access_count' => ['std_dev' => 100, 'mean' => 10]];
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
        $r1 = $this->routeMock(1.0, 0.5, 5, null, 1);
        $r2 = $this->routeMock(2.0, 1.0, 10, null, 2);
        $r3 = $this->routeMock(3.0, 1.5, 15, null, 3);
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

    private function routeMock(
        ?float $requestTime,
        ?float $queryTime,
        ?int $totalQueries,
        ?int $memoryUsage,
        int $accessCount,
    ): object {
        $r = $this->getMockBuilder(\stdClass::class)
            ->addMethods([
                'getRequestTime',
                'getQueryTime',
                'getTotalQueries',
                'getMemoryUsage',
                'getAccessCount',
            ])
            ->getMock();
        $r->method('getRequestTime')->willReturn($requestTime);
        $r->method('getQueryTime')->willReturn($queryTime);
        $r->method('getTotalQueries')->willReturn($totalQueries);
        $r->method('getMemoryUsage')->willReturn($memoryUsage);
        $r->method('getAccessCount')->willReturn($accessCount);

        return $r;
    }
}
