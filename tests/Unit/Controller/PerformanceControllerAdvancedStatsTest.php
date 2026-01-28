<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Controller;

use Nowo\PerformanceBundle\Controller\PerformanceController;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Model\RouteDataWithAggregates;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests for PerformanceController advanced statistics methods.
 */
final class PerformanceControllerAdvancedStatsTest extends TestCase
{
    private PerformanceMetricsService|MockObject $metricsService;
    private PerformanceController $controller;

    protected function setUp(): void
    {
        $this->metricsService = $this->createMock(PerformanceMetricsService::class);
        $this->controller = new PerformanceController(
            $this->metricsService,
            null,
            true,
            [],
            'bootstrap',
            null,
            null,
            null,
            false,
            false,
            null,
            0.5,
            1.0,
            20,
            50,
            20.0,
            50.0,
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            0,
            [200, 404, 500, 503],
            null,
            false,
            true,
            ['dev', 'test'],
            'default',
            true,
            true,
            false,
            [],
            false,
            1.0,
            true,
        );
    }

    /**
     * Helper method to call private methods using reflection.
     */
    private function callPrivateMethod(string $methodName, mixed ...$args): mixed
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($this->controller, $args);
    }

    private function routeWithAggregates(
        ?float $requestTime,
        ?float $queryTime,
        ?int $totalQueries,
        int $accessCount = 1,
        ?int $memoryUsage = null,
    ): RouteDataWithAggregates {
        $routeData = $this->createMock(RouteData::class);
        $routeData->method('getId')->willReturn(1);
        $routeData->method('getEnv')->willReturn('dev');
        $routeData->method('getName')->willReturn('test');
        $routeData->method('getAccessRecords')->willReturn(new \Doctrine\Common\Collections\ArrayCollection());

        return new RouteDataWithAggregates($routeData, [
            'request_time' => $requestTime,
            'query_time' => $queryTime,
            'total_queries' => $totalQueries,
            'memory_usage' => $memoryUsage,
            'access_count' => $accessCount,
            'status_codes' => [],
        ]);
    }

    public function testCalculateAdvancedStatsWithEmptyRoutes(): void
    {
        $result = $this->callPrivateMethod('calculateAdvancedStats', []);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('request_time', $result);
        $this->assertArrayHasKey('query_time', $result);
        $this->assertArrayHasKey('query_count', $result);
        $this->assertArrayHasKey('memory_usage', $result);
        $this->assertArrayHasKey('access_count', $result);

        foreach ($result as $stats) {
            $this->assertArrayHasKey('count', $stats);
            $this->assertEquals(0, $stats['count']);
        }
    }

    public function testCalculateAdvancedStatsWithRoutes(): void
    {
        $route1 = $this->routeWithAggregates(0.5, 0.2, 10, 5, 10485760);  // 10 MB
        $route2 = $this->routeWithAggregates(1.0, 0.3, 20, 10, 20971520); // 20 MB

        $routes = [$route1, $route2];
        $result = $this->callPrivateMethod('calculateAdvancedStats', $routes);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('request_time', $result);
        $this->assertArrayHasKey('query_time', $result);
        $this->assertArrayHasKey('query_count', $result);
        $this->assertArrayHasKey('memory_usage', $result);
        $this->assertArrayHasKey('access_count', $result);

        $this->assertEquals(2, $result['request_time']['count']);
        $this->assertEquals(2, $result['query_time']['count']);
        $this->assertEquals(2, $result['query_count']['count']);
        $this->assertEquals(2, $result['memory_usage']['count']);
        $this->assertEquals(2, $result['access_count']['count']);
    }

    public function testCalculateAdvancedStatsWithNullValues(): void
    {
        $route1 = $this->routeWithAggregates(null, null, null, 0, null);

        $routes = [$route1];
        $result = $this->callPrivateMethod('calculateAdvancedStats', $routes);

        $this->assertEquals(0, $result['request_time']['count']);
        $this->assertEquals(0, $result['query_time']['count']);
        $this->assertEquals(0, $result['query_count']['count']);
        $this->assertEquals(0, $result['memory_usage']['count']);
        // access_count is int (0), not null, so it is included
        $this->assertEquals(1, $result['access_count']['count']);
        $this->assertEquals(0.0, $result['access_count']['mean']);
    }

    public function testCalculateDetailedStatsWithEmptyArray(): void
    {
        $result = $this->callPrivateMethod('calculateDetailedStats', [], 'Test Label', 'unit');

        $this->assertIsArray($result);
        $this->assertEquals(0, $result['count']);
        $this->assertEquals('', $result['label']);
        $this->assertEquals('', $result['unit']);
    }

    public function testCalculateDetailedStatsWithSingleValue(): void
    {
        $result = $this->callPrivateMethod('calculateDetailedStats', [5.0], 'Test Label', 'unit');

        $this->assertEquals(1, $result['count']);
        $this->assertEquals(5.0, $result['mean']);
        $this->assertEquals(5.0, $result['median']);
        $this->assertEquals(5.0, $result['min']);
        $this->assertEquals(5.0, $result['max']);
        $this->assertEquals(0.0, $result['range']);
    }

    public function testCalculateDetailedStatsWithMultipleValues(): void
    {
        $values = [1.0, 2.0, 3.0, 4.0, 5.0];
        $result = $this->callPrivateMethod('calculateDetailedStats', $values, 'Test Label', 'unit');

        $this->assertEquals(5, $result['count']);
        $this->assertEquals(3.0, $result['mean']);
        $this->assertEquals(3.0, $result['median']);
        $this->assertEquals(1.0, $result['min']);
        $this->assertEquals(5.0, $result['max']);
        $this->assertEquals(4.0, $result['range']);
        $this->assertArrayHasKey('percentiles', $result);
        $this->assertArrayHasKey(25, $result['percentiles']);
        $this->assertArrayHasKey(50, $result['percentiles']);
        $this->assertArrayHasKey(75, $result['percentiles']);
        $this->assertArrayHasKey(90, $result['percentiles']);
        $this->assertArrayHasKey(95, $result['percentiles']);
        $this->assertArrayHasKey(99, $result['percentiles']);
    }

    public function testCalculateDetailedStatsWithEvenCount(): void
    {
        $values = [1.0, 2.0, 3.0, 4.0];
        $result = $this->callPrivateMethod('calculateDetailedStats', $values, 'Test Label', 'unit');

        $this->assertEquals(4, $result['count']);
        $this->assertEquals(2.5, $result['median']); // (2 + 3) / 2
    }

    public function testCalculateDetailedStatsWithAllSameValues(): void
    {
        $values = [5.0, 5.0, 5.0, 5.0, 5.0];
        $result = $this->callPrivateMethod('calculateDetailedStats', $values, 'Test Label', 'unit');

        $this->assertEquals(5, $result['count']);
        $this->assertEquals(5.0, $result['mean']);
        $this->assertEquals(5.0, $result['median']);
        $this->assertEquals(0.0, $result['range']);
        $this->assertEquals(0.0, $result['std_dev']);
        $this->assertEquals(5, $result['distribution'][0]); // All in first bucket
    }

    public function testCalculateDetailedStatsWithOutliers(): void
    {
        $values = [1.0, 2.0, 3.0, 4.0, 5.0, 100.0]; // 100 is an outlier
        $result = $this->callPrivateMethod('calculateDetailedStats', $values, 'Test Label', 'unit');

        $this->assertGreaterThan(0, $result['outliers_count']);
        $this->assertContains(100.0, $result['outliers']);
    }

    public function testGetEmptyStats(): void
    {
        $result = $this->callPrivateMethod('getEmptyStats');

        $this->assertIsArray($result);
        $this->assertEquals('', $result['label']);
        $this->assertEquals('', $result['unit']);
        $this->assertEquals(0, $result['count']);
        $this->assertEquals(0.0, $result['mean']);
        $this->assertEquals(0.0, $result['median']);
        $this->assertEquals(0.0, $result['mode']);
        $this->assertEquals(0.0, $result['std_dev']);
        $this->assertEquals(0.0, $result['min']);
        $this->assertEquals(0.0, $result['max']);
        $this->assertEquals(0.0, $result['range']);
        $this->assertEquals([], $result['percentiles']);
        $this->assertEquals(0, $result['outliers_count']);
        $this->assertEquals([], $result['outliers']);
        $this->assertEquals([], $result['distribution']);
        $this->assertEquals([], $result['bucket_labels']);
    }

    public function testGetRoutesNeedingAttentionWithEmptyRoutes(): void
    {
        $advancedStats = [
            'request_time' => $this->callPrivateMethod('getEmptyStats'),
            'query_count' => $this->callPrivateMethod('getEmptyStats'),
            'memory_usage' => $this->callPrivateMethod('getEmptyStats'),
        ];

        $result = $this->callPrivateMethod('getRoutesNeedingAttention', [], $advancedStats);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('slow_request_time', $result);
        $this->assertArrayHasKey('high_query_count', $result);
        $this->assertArrayHasKey('high_memory', $result);
        $this->assertArrayHasKey('outliers', $result);
        $this->assertEmpty($result['slow_request_time']);
        $this->assertEmpty($result['high_query_count']);
        $this->assertEmpty($result['high_memory']);
        $this->assertEmpty($result['outliers']);
    }

    public function testGetRoutesNeedingAttentionWithSlowRoutes(): void
    {
        $route = $this->routeWithAggregates(2.0, null, null, 1, null);

        $advancedStats = [
            'request_time' => [
                'percentiles' => [95 => 1.0],
                'outliers' => [],
            ],
            'query_count' => [
                'percentiles' => [],
                'outliers' => [],
            ],
            'memory_usage' => [
                'percentiles' => [],
                'outliers' => [],
            ],
        ];

        $result = $this->callPrivateMethod('getRoutesNeedingAttention', [$route], $advancedStats);

        $this->assertNotEmpty($result['slow_request_time']);
        $this->assertEquals(2.0, $result['slow_request_time'][0]['value']);
    }

    public function testGetRoutesNeedingAttentionWithHighQueryCount(): void
    {
        $route = $this->routeWithAggregates(null, null, 100, 1, null);

        $advancedStats = [
            'request_time' => [
                'percentiles' => [],
                'outliers' => [],
            ],
            'query_count' => [
                'percentiles' => [95 => 50],
                'outliers' => [],
            ],
            'memory_usage' => [
                'percentiles' => [],
                'outliers' => [],
            ],
        ];

        $result = $this->callPrivateMethod('getRoutesNeedingAttention', [$route], $advancedStats);

        $this->assertNotEmpty($result['high_query_count']);
        $this->assertEquals(100, $result['high_query_count'][0]['value']);
    }

    public function testGetRoutesNeedingAttentionWithHighMemory(): void
    {
        $route = $this->routeWithAggregates(null, null, null, 1, 104857600); // 100 MB

        $advancedStats = [
            'request_time' => [
                'percentiles' => [],
                'outliers' => [],
            ],
            'query_count' => [
                'percentiles' => [],
                'outliers' => [],
            ],
            'memory_usage' => [
                'percentiles' => [95 => 50.0], // 50 MB
                'outliers' => [],
            ],
        ];

        $result = $this->callPrivateMethod('getRoutesNeedingAttention', [$route], $advancedStats);

        $this->assertNotEmpty($result['high_memory']);
        $this->assertEquals(100.0, $result['high_memory'][0]['value']); // Converted to MB
    }

    public function testGetRoutesNeedingAttentionWithOutliers(): void
    {
        $route = $this->routeWithAggregates(10.0, null, null, 1, null);

        $advancedStats = [
            'request_time' => [
                'percentiles' => [],
                'outliers' => [10.0],
            ],
            'query_count' => [
                'percentiles' => [],
                'outliers' => [],
            ],
            'memory_usage' => [
                'percentiles' => [],
                'outliers' => [],
            ],
        ];

        $result = $this->callPrivateMethod('getRoutesNeedingAttention', [$route], $advancedStats);

        $this->assertNotEmpty($result['outliers']);
        $this->assertEquals($route, $result['outliers'][0]['route']);
    }
}
