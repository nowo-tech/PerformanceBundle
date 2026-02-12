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
 * Tests for PerformanceController helper methods.
 *
 * This test class tests private helper methods using reflection.
 */
final class PerformanceControllerHelperMethodsTest extends TestCase
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
            true,   // checkTableStatus
            true,   // enableLogging
            null,   // accessRecordsRetentionDays
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

    /**
     * Build RouteDataWithAggregates for tests (calculateStats / calculateAdvancedStats expect this type).
     *
     * @param ?int $memoryUsage Memory in bytes (optional)
     * @param \DateTimeImmutable|null $createdAt Used for getChartData date grouping (default: 2025-01-27)
     * @param \DateTimeImmutable|null $lastAccessedAt Used for getChartData date grouping (default: 2025-01-27)
     */
    private function routeWithAggregates(
        ?float $requestTime,
        ?float $queryTime,
        ?int $totalQueries,
        int $accessCount = 1,
        ?int $memoryUsage = null,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $lastAccessedAt = null,
    ): RouteDataWithAggregates {
        $routeData = $this->createMock(RouteData::class);
        $routeData->method('getId')->willReturn(1);
        $routeData->method('getEnv')->willReturn('dev');
        $routeData->method('getName')->willReturn('test');
        $routeData->method('getAccessRecords')->willReturn(new \Doctrine\Common\Collections\ArrayCollection());
        $dt = $createdAt ?? new \DateTimeImmutable('2025-01-27');
        $routeData->method('getCreatedAt')->willReturn($dt);
        $routeData->method('getLastAccessedAt')->willReturn($lastAccessedAt ?? $dt);

        return new RouteDataWithAggregates($routeData, [
            'request_time' => $requestTime,
            'query_time' => $queryTime,
            'total_queries' => $totalQueries,
            'memory_usage' => $memoryUsage,
            'access_count' => $accessCount,
            'status_codes' => [],
        ]);
    }

    // ========== getSortValue() tests ==========

    public function testGetSortValueWithName(): void
    {
        $route = $this->createMock(RouteData::class);
        $route->method('getName')->willReturn('test_route');

        $result = $this->callPrivateMethod('getSortValue', $route, 'name');
        $this->assertSame('test_route', $result);
    }

    public function testGetSortValueWithRequestTime(): void
    {
        $route = $this->routeWithAggregates(0.5, null, null);

        $result = $this->callPrivateMethod('getSortValue', $route, 'requestTime');
        $this->assertSame(0.5, $result);
    }

    public function testGetSortValueWithQueryTime(): void
    {
        $route = $this->routeWithAggregates(null, 0.25, null);

        $result = $this->callPrivateMethod('getSortValue', $route, 'queryTime');
        $this->assertSame(0.25, $result);
    }

    public function testGetSortValueWithTotalQueries(): void
    {
        $route = $this->routeWithAggregates(null, null, 10);

        $result = $this->callPrivateMethod('getSortValue', $route, 'totalQueries');
        $this->assertSame(10, $result);
    }

    public function testGetSortValueWithAccessCount(): void
    {
        $route = $this->routeWithAggregates(null, null, null, 100);

        $result = $this->callPrivateMethod('getSortValue', $route, 'accessCount');
        $this->assertSame(100, $result);
    }

    public function testGetSortValueWithMemoryUsage(): void
    {
        $route = $this->routeWithAggregates(null, null, null, 1, 2_097_152);

        $result = $this->callPrivateMethod('getSortValue', $route, 'memoryUsage');
        $this->assertSame(2_097_152, $result);
    }

    public function testGetSortValueWithMemoryUsageNullReturnsZero(): void
    {
        $route = $this->routeWithAggregates(null, null, null, 1, null);

        $result = $this->callPrivateMethod('getSortValue', $route, 'memoryUsage');
        $this->assertSame(0, $result);
    }

    public function testGetSortValueWithMemoryUsagePlainRouteDataReturnsZero(): void
    {
        $route = $this->createMock(RouteData::class);

        $result = $this->callPrivateMethod('getSortValue', $route, 'memoryUsage');
        $this->assertSame(0, $result);
    }

    public function testGetSortValueWithCreatedAt(): void
    {
        $routeData = $this->createMock(RouteData::class);
        $createdAt = new \DateTimeImmutable('2025-01-15 10:00:00');
        $routeData->method('getCreatedAt')->willReturn($createdAt);
        $route = new RouteDataWithAggregates($routeData, [
            'request_time' => null,
            'query_time' => null,
            'total_queries' => null,
            'memory_usage' => null,
            'access_count' => 1,
            'status_codes' => [],
        ]);

        $result = $this->callPrivateMethod('getSortValue', $route, 'createdAt');
        $this->assertSame((float) $createdAt->getTimestamp(), $result);
    }

    public function testGetSortValueWithLastAccessedAt(): void
    {
        $routeData = $this->createMock(RouteData::class);
        $lastAccessedAt = new \DateTimeImmutable('2025-01-20 14:30:00');
        $routeData->method('getLastAccessedAt')->willReturn($lastAccessedAt);
        $route = new RouteDataWithAggregates($routeData, [
            'request_time' => null,
            'query_time' => null,
            'total_queries' => null,
            'memory_usage' => null,
            'access_count' => 1,
            'status_codes' => [],
        ]);

        $result = $this->callPrivateMethod('getSortValue', $route, 'lastAccessedAt');
        $this->assertSame((float) $lastAccessedAt->getTimestamp(), $result);
    }

    public function testGetSortValueWithEnv(): void
    {
        $route = $this->createMock(RouteData::class);
        $route->method('getEnv')->willReturn('dev');

        $result = $this->callPrivateMethod('getSortValue', $route, 'env');
        $this->assertSame('dev', $result);
    }

    public function testGetSortValueWithDefault(): void
    {
        $route = $this->routeWithAggregates(0.75, null, null);

        $result = $this->callPrivateMethod('getSortValue', $route, 'unknown');
        $this->assertSame(0.75, $result);
    }

    public function testGetSortValueWithNullValues(): void
    {
        $routeData = $this->createMock(RouteData::class);
        $routeData->method('getName')->willReturn(null);
        $routeData->method('getEnv')->willReturn(null);
        $routeData->method('getCreatedAt')->willReturn(null);
        $routeData->method('getLastAccessedAt')->willReturn(null);
        $route = new RouteDataWithAggregates($routeData, [
            'request_time' => null,
            'query_time' => null,
            'total_queries' => null,
            'memory_usage' => null,
            'access_count' => 1,
            'status_codes' => [],
        ]);

        $this->assertSame('', $this->callPrivateMethod('getSortValue', $route, 'name'));
        $this->assertSame(0.0, $this->callPrivateMethod('getSortValue', $route, 'requestTime'));
        $this->assertSame(0.0, $this->callPrivateMethod('getSortValue', $route, 'queryTime'));
        $this->assertSame(0, $this->callPrivateMethod('getSortValue', $route, 'totalQueries'));
        $this->assertSame(1, $this->callPrivateMethod('getSortValue', $route, 'accessCount'));
        $this->assertSame(0, $this->callPrivateMethod('getSortValue', $route, 'memoryUsage'));
        $this->assertSame('', $this->callPrivateMethod('getSortValue', $route, 'env'));
    }

    // ========== calculateStats() tests ==========

    public function testCalculateStatsWithEmptyArray(): void
    {
        $result = $this->callPrivateMethod('calculateStats', []);

        $this->assertSame([
            'total_routes' => 0,
            'avg_queries' => 0.0,
            'max_queries' => 0,
            'avg_request_time' => 0.0,
            'avg_query_time' => 0.0,
            'max_request_time' => 0.0,
            'max_query_time' => 0.0,
        ], $result);
    }

    public function testCalculateStatsWithSingleRoute(): void
    {
        $route = $this->routeWithAggregates(0.5, 0.1, 5);

        $result = $this->callPrivateMethod('calculateStats', [$route]);

        $this->assertSame(1, $result['total_routes']);
        $this->assertEqualsWithDelta(5.0, $result['avg_queries'], 0.01);
        $this->assertSame(5, $result['max_queries']);
        $this->assertSame(0.5, $result['avg_request_time']);
        $this->assertSame(0.1, $result['avg_query_time']);
        $this->assertSame(0.5, $result['max_request_time']);
        $this->assertSame(0.1, $result['max_query_time']);
    }

    public function testCalculateStatsWithMultipleRoutes(): void
    {
        $route1 = $this->routeWithAggregates(0.5, 0.1, 5);
        $route2 = $this->routeWithAggregates(1.0, 0.2, 10);
        $route3 = $this->routeWithAggregates(0.3, 0.05, 3);

        $result = $this->callPrivateMethod('calculateStats', [$route1, $route2, $route3]);

        $this->assertSame(3, $result['total_routes']);
        $this->assertEqualsWithDelta(6.0, $result['avg_queries'], 0.01); // (5 + 10 + 3) / 3
        $this->assertSame(10, $result['max_queries']);
        $this->assertSame(0.6, $result['avg_request_time']); // (0.5 + 1.0 + 0.3) / 3
        $this->assertEqualsWithDelta(0.11666666666666667, $result['avg_query_time'], 0.00000001); // (0.1 + 0.2 + 0.05) / 3
        $this->assertSame(1.0, $result['max_request_time']);
        $this->assertSame(0.2, $result['max_query_time']);
    }

    public function testCalculateStatsFiltersNullValues(): void
    {
        $route1 = $this->routeWithAggregates(0.5, null, 5);
        $route2 = $this->routeWithAggregates(null, 0.2, null);

        $result = $this->callPrivateMethod('calculateStats', [$route1, $route2]);

        $this->assertSame(2, $result['total_routes']);
        $this->assertEqualsWithDelta(5.0, $result['avg_queries'], 0.01); // Only route1 has queries
        $this->assertSame(5, $result['max_queries']);
        $this->assertSame(0.5, $result['avg_request_time']); // Only route1 has request time
        $this->assertSame(0.2, $result['avg_query_time']); // Only route2 has query time
    }

    // ========== getEmptyStats() tests ==========

    public function testGetEmptyStats(): void
    {
        $result = $this->callPrivateMethod('getEmptyStats');

        $this->assertSame([
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
        ], $result);
    }

    // ========== calculateAdvancedStats() tests ==========

    public function testCalculateAdvancedStatsWithEmptyArray(): void
    {
        $result = $this->callPrivateMethod('calculateAdvancedStats', []);

        $this->assertArrayHasKey('request_time', $result);
        $this->assertArrayHasKey('query_time', $result);
        $this->assertArrayHasKey('query_count', $result);
        $this->assertArrayHasKey('memory_usage', $result);
        $this->assertArrayHasKey('access_count', $result);

        $emptyStats = $this->callPrivateMethod('getEmptyStats');
        $this->assertSame($emptyStats, $result['request_time']);
        $this->assertSame($emptyStats, $result['query_time']);
        $this->assertSame($emptyStats, $result['query_count']);
        $this->assertSame($emptyStats, $result['memory_usage']);
        $this->assertSame($emptyStats, $result['access_count']);
    }

    public function testCalculateAdvancedStatsWithRoutes(): void
    {
        $route1 = $this->routeWithAggregates(0.5, 0.1, 5, 10, 1048576);   // 1 MB
        $route2 = $this->routeWithAggregates(1.0, 0.2, 10, 20, 2097152);  // 2 MB

        $result = $this->callPrivateMethod('calculateAdvancedStats', [$route1, $route2]);

        $this->assertArrayHasKey('request_time', $result);
        $this->assertArrayHasKey('query_time', $result);
        $this->assertArrayHasKey('query_count', $result);
        $this->assertArrayHasKey('memory_usage', $result);
        $this->assertArrayHasKey('access_count', $result);

        // Check that memory is converted to MB (1048576 bytes = 1 MB)
        $this->assertSame(2, $result['memory_usage']['count']); // 2 routes
        $this->assertSame(1.5, $result['memory_usage']['mean']); // (1 + 2) / 2 MB
    }

    public function testCalculateAdvancedStatsFiltersNullValues(): void
    {
        $route1 = $this->routeWithAggregates(0.5, null, null, 10, null);
        $route2 = $this->routeWithAggregates(null, 0.2, 10, 0, 2097152);  // 2 MB

        $result = $this->callPrivateMethod('calculateAdvancedStats', [$route1, $route2]);

        // request_time should only have route1 (0.5)
        $this->assertSame(1, $result['request_time']['count']);
        $this->assertSame(0.5, $result['request_time']['mean']);

        // query_time should only have route2 (0.2)
        $this->assertSame(1, $result['query_time']['count']);
        $this->assertSame(0.2, $result['query_time']['mean']);

        // query_count should only have route2 (10)
        $this->assertSame(1, $result['query_count']['count']);
        $this->assertSame(10.0, $result['query_count']['mean']);

        // memory_usage should only have route2 (2 MB)
        $this->assertSame(1, $result['memory_usage']['count']);
        $this->assertSame(2.0, $result['memory_usage']['mean']);

        // access_count: 0 is kept (filter null only), so [10, 0] -> count 2, mean 5
        $this->assertSame(2, $result['access_count']['count']);
        $this->assertSame(5.0, $result['access_count']['mean']);
    }

    // ========== calculateDetailedStats() tests ==========

    public function testCalculateDetailedStatsWithEmptyArray(): void
    {
        $result = $this->callPrivateMethod('calculateDetailedStats', [], 'Test Label', 's');

        $emptyStats = $this->callPrivateMethod('getEmptyStats');
        $this->assertSame($emptyStats, $result);
    }

    public function testCalculateDetailedStatsWithSingleValue(): void
    {
        $result = $this->callPrivateMethod('calculateDetailedStats', [5.0], 'Test Label', 's');

        $this->assertSame('Test Label', $result['label']);
        $this->assertSame('s', $result['unit']);
        $this->assertSame(1, $result['count']);
        $this->assertSame(5.0, $result['mean']);
        $this->assertSame(5.0, $result['median']);
        $this->assertSame(5.0, $result['mode']);
        $this->assertSame(0.0, $result['std_dev']);
        $this->assertSame(5.0, $result['min']);
        $this->assertSame(5.0, $result['max']);
        $this->assertSame(0.0, $result['range']);
        $this->assertSame(0, $result['outliers_count']);
    }

    public function testCalculateDetailedStatsWithMultipleValues(): void
    {
        $values = [1.0, 2.0, 3.0, 4.0, 5.0];
        $result = $this->callPrivateMethod('calculateDetailedStats', $values, 'Test Label', 's');

        $this->assertSame('Test Label', $result['label']);
        $this->assertSame('s', $result['unit']);
        $this->assertSame(5, $result['count']);
        $this->assertSame(3.0, $result['mean']); // (1+2+3+4+5)/5
        $this->assertSame(3.0, $result['median']); // Middle value
        $this->assertSame(1.0, $result['min']);
        $this->assertSame(5.0, $result['max']);
        $this->assertSame(4.0, $result['range']); // 5-1
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
        $result = $this->callPrivateMethod('calculateDetailedStats', $values, 'Test Label', 's');

        $this->assertSame(4, $result['count']);
        // Median for even count: (values[n/2-1] + values[n/2]) / 2 = (2 + 3) / 2 = 2.5
        $this->assertSame(2.5, $result['median']);
    }

    public function testCalculateDetailedStatsWithOutliers(): void
    {
        // Values: 1, 2, 3, 4, 5, 100 (100 is an outlier)
        $values = [1.0, 2.0, 3.0, 4.0, 5.0, 100.0];
        $result = $this->callPrivateMethod('calculateDetailedStats', $values, 'Test Label', 's');

        $this->assertSame(6, $result['count']);
        $this->assertGreaterThan(0, $result['outliers_count']);
        $this->assertContains(100.0, $result['outliers']);
    }

    public function testCalculateDetailedStatsWithDistribution(): void
    {
        $values = range(1, 100); // 1 to 100
        $result = $this->callPrivateMethod('calculateDetailedStats', $values, 'Test Label', 's');

        $this->assertSame(100, $result['count']);
        $this->assertArrayHasKey('distribution', $result);
        $this->assertCount(10, $result['distribution']); // 10 buckets
        $this->assertArrayHasKey('bucket_labels', $result);
        $this->assertCount(10, $result['bucket_labels']);
    }

    public function testCalculateDetailedStatsWithAllSameValues(): void
    {
        $values = array_fill(0, 10, 5.0); // All 5.0
        $result = $this->callPrivateMethod('calculateDetailedStats', $values, 'Test Label', 's');

        $this->assertSame(10, $result['count']);
        $this->assertSame(5.0, $result['mean']);
        $this->assertSame(5.0, $result['median']);
        $this->assertSame(5.0, $result['mode']);
        $this->assertSame(0.0, $result['std_dev']);
        $this->assertSame(0.0, $result['range']);
        $this->assertSame(0, $result['outliers_count']);
        // All values should be in first bucket
        $this->assertSame(10, $result['distribution'][0]);
    }

    public function testCalculateDetailedStatsRoundsValues(): void
    {
        $values = [1.123456, 2.234567, 3.345678];
        $result = $this->callPrivateMethod('calculateDetailedStats', $values, 'Test Label', 's');

        // Values should be rounded to 4 decimal places
        $this->assertNotEquals(1.123456, $result['mean']);
        $this->assertEquals(round(2.234567, 4), $result['mean'], '', 0.0001);
    }

    // ========== getRoutesNeedingAttention() tests ==========

    public function testGetRoutesNeedingAttentionWithEmptyRoutes(): void
    {
        $result = $this->callPrivateMethod('getRoutesNeedingAttention', [], []);

        $this->assertArrayHasKey('slow_request_time', $result);
        $this->assertArrayHasKey('high_query_count', $result);
        $this->assertArrayHasKey('high_memory', $result);
        $this->assertArrayHasKey('outliers', $result);
        $this->assertEmpty($result['slow_request_time']);
        $this->assertEmpty($result['high_query_count']);
        $this->assertEmpty($result['high_memory']);
        $this->assertEmpty($result['outliers']);
    }

    public function testGetRoutesNeedingAttentionWithSlowRequestTime(): void
    {
        $route = $this->routeWithAggregates(2.0, 0.05, 5, 1, 1048576); // 2.0 > 95th 1.0

        $advancedStats = [
            'request_time' => [
                'mean' => 0.5,
                'std_dev' => 0.1,
                'percentiles' => [95 => 1.0], // 95th percentile
            ],
            'query_count' => [
                'mean' => 10,
                'std_dev' => 2,
                'percentiles' => [95 => 12],
            ],
            'memory_usage' => [
                'mean' => 0.5,
                'std_dev' => 0.1,
                'percentiles' => [95 => 0.6],
            ],
        ];

        $result = $this->callPrivateMethod('getRoutesNeedingAttention', [$route], $advancedStats);

        $this->assertNotEmpty($result['slow_request_time']);
        $this->assertSame($route, $result['slow_request_time'][0]['route']);
        $this->assertSame(2.0, $result['slow_request_time'][0]['value']);
    }

    public function testGetRoutesNeedingAttentionWithHighQueryCount(): void
    {
        $route = $this->routeWithAggregates(0.5, 0.1, 100, 1, 1048576); // 100 > 95th 50

        $advancedStats = [
            'request_time' => [
                'mean' => 0.5,
                'std_dev' => 0.1,
                'percentiles' => [95 => 0.6],
            ],
            'query_count' => [
                'mean' => 10,
                'std_dev' => 2,
                'percentiles' => [95 => 50], // 95th percentile
            ],
            'memory_usage' => [
                'mean' => 0.5,
                'std_dev' => 0.1,
                'percentiles' => [95 => 0.6],
            ],
        ];

        $result = $this->callPrivateMethod('getRoutesNeedingAttention', [$route], $advancedStats);

        $this->assertNotEmpty($result['high_query_count']);
        $this->assertSame($route, $result['high_query_count'][0]['route']);
        $this->assertSame(100, $result['high_query_count'][0]['value']);
    }

    public function testGetRoutesNeedingAttentionWithHighMemory(): void
    {
        $route = $this->routeWithAggregates(0.5, 0.05, 5, 1, 52428800); // 50 MB > 95th 40 MB

        $advancedStats = [
            'request_time' => [
                'mean' => 0.5,
                'std_dev' => 0.1,
                'percentiles' => [95 => 0.6],
            ],
            'query_count' => [
                'mean' => 10,
                'std_dev' => 2,
                'percentiles' => [95 => 12],
            ],
            'memory_usage' => [
                'mean' => 20.0,
                'std_dev' => 5.0,
                'percentiles' => [95 => 40.0], // 95th percentile in MB
            ],
        ];

        $result = $this->callPrivateMethod('getRoutesNeedingAttention', [$route], $advancedStats);

        $this->assertNotEmpty($result['high_memory']);
        $this->assertSame($route, $result['high_memory'][0]['route']);
        $this->assertEqualsWithDelta(50.0, $result['high_memory'][0]['value'], 0.1); // 50MB
    }

    // ========== getChartData() tests ==========

    public function testGetChartDataWithRequestTimeMetric(): void
    {
        $route = $this->routeWithAggregates(0.5, 0.1, 5);
        $this->metricsService->method('getRoutesWithAggregatesFiltered')->willReturn([$route]);

        $result = $this->callPrivateMethod('getChartData', 'dev', null, 7, 'requestTime');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('labels', $result);
        $this->assertArrayHasKey('datasets', $result);
        $this->assertIsArray($result['labels']);
        $this->assertIsArray($result['datasets']);
    }

    public function testGetChartDataWithQueryTimeMetric(): void
    {
        $route = $this->routeWithAggregates(0.5, 0.25, 5);
        $this->metricsService->method('getRoutesWithAggregatesFiltered')->willReturn([$route]);

        $result = $this->callPrivateMethod('getChartData', 'dev', null, 7, 'queryTime');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('labels', $result);
        $this->assertArrayHasKey('datasets', $result);
    }

    public function testGetChartDataWithTotalQueriesMetric(): void
    {
        $route = $this->routeWithAggregates(0.5, 0.1, 10);
        $this->metricsService->method('getRoutesWithAggregatesFiltered')->willReturn([$route]);

        $result = $this->callPrivateMethod('getChartData', 'dev', null, 7, 'totalQueries');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('labels', $result);
        $this->assertArrayHasKey('datasets', $result);
    }

    public function testGetChartDataWithMemoryUsageMetric(): void
    {
        $route = $this->routeWithAggregates(0.5, 0.1, 5, 1, 1048576); // 1MB
        $this->metricsService->method('getRoutesWithAggregatesFiltered')->willReturn([$route]);

        $result = $this->callPrivateMethod('getChartData', 'dev', null, 7, 'memoryUsage');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('labels', $result);
        $this->assertArrayHasKey('datasets', $result);
    }

    public function testGetChartDataWithSpecificRoute(): void
    {
        $route = $this->routeWithAggregates(0.5, 0.1, 5);
        $this->metricsService->method('getRoutesWithAggregatesFiltered')->willReturn([$route]);

        $result = $this->callPrivateMethod('getChartData', 'dev', 'test_route', 7, 'requestTime');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('labels', $result);
        $this->assertArrayHasKey('datasets', $result);
    }

    public function testGetChartDataWithEmptyResults(): void
    {
        $this->metricsService->method('getRoutesWithAggregatesFiltered')->willReturn([]);

        $result = $this->callPrivateMethod('getChartData', 'dev', null, 7, 'requestTime');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('labels', $result);
        $this->assertArrayHasKey('datasets', $result);
        // Labels are generated for each day in range (startDate..endDate inclusive = 8 days for "7 days")
        $this->assertCount(8, $result['labels']);
        $this->assertIsArray($result['datasets']);
    }

    public function testGetChartDataWithDifferentDays(): void
    {
        $route = $this->routeWithAggregates(0.5, 0.1, 5);
        $this->metricsService->method('getRoutesWithAggregatesFiltered')->willReturn([$route]);

        $result30 = $this->callPrivateMethod('getChartData', 'dev', null, 30, 'requestTime');
        $this->assertIsArray($result30);
        $this->assertCount(31, $result30['labels']); // startDate..endDate inclusive

        $result1 = $this->callPrivateMethod('getChartData', 'dev', null, 1, 'requestTime');
        $this->assertIsArray($result1);
        $this->assertCount(2, $result1['labels']); // startDate..endDate inclusive for 1 day
    }

    // ========== buildFiltersFromRequest() tests ==========

    public function testBuildFiltersFromRequestWithNoFilters(): void
    {
        $request = new \Symfony\Component\HttpFoundation\Request();

        $result = $this->callPrivateMethod('buildFiltersFromRequest', $request);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testBuildFiltersFromRequestWithRouteFilter(): void
    {
        $request = new \Symfony\Component\HttpFoundation\Request();
        $request->query->set('route', 'test_route');

        $result = $this->callPrivateMethod('buildFiltersFromRequest', $request);

        $this->assertArrayHasKey('route_name_pattern', $result);
        $this->assertSame('test_route', $result['route_name_pattern']);
    }

    public function testBuildFiltersFromRequestWithMinRequestTime(): void
    {
        $request = new \Symfony\Component\HttpFoundation\Request();
        $request->query->set('min_request_time', '0.5');

        $result = $this->callPrivateMethod('buildFiltersFromRequest', $request);

        $this->assertArrayHasKey('min_request_time', $result);
        $this->assertSame(0.5, $result['min_request_time']);
        $this->assertIsFloat($result['min_request_time']);
    }

    public function testBuildFiltersFromRequestWithMaxRequestTime(): void
    {
        $request = new \Symfony\Component\HttpFoundation\Request();
        $request->query->set('max_request_time', '1.0');

        $result = $this->callPrivateMethod('buildFiltersFromRequest', $request);

        $this->assertArrayHasKey('max_request_time', $result);
        $this->assertSame(1.0, $result['max_request_time']);
        $this->assertIsFloat($result['max_request_time']);
    }

    public function testBuildFiltersFromRequestWithMinQueryCount(): void
    {
        $request = new \Symfony\Component\HttpFoundation\Request();
        $request->query->set('min_query_count', '10');

        $result = $this->callPrivateMethod('buildFiltersFromRequest', $request);

        $this->assertArrayHasKey('min_query_count', $result);
        $this->assertSame(10, $result['min_query_count']);
        $this->assertIsInt($result['min_query_count']);
    }

    public function testBuildFiltersFromRequestWithMaxQueryCount(): void
    {
        $request = new \Symfony\Component\HttpFoundation\Request();
        $request->query->set('max_query_count', '50');

        $result = $this->callPrivateMethod('buildFiltersFromRequest', $request);

        $this->assertArrayHasKey('max_query_count', $result);
        $this->assertSame(50, $result['max_query_count']);
        $this->assertIsInt($result['max_query_count']);
    }

    public function testBuildFiltersFromRequestWithDateFrom(): void
    {
        $request = new \Symfony\Component\HttpFoundation\Request();
        $request->query->set('date_from', '2025-01-01');

        $result = $this->callPrivateMethod('buildFiltersFromRequest', $request);

        $this->assertArrayHasKey('date_from', $result);
        $this->assertInstanceOf(\DateTimeImmutable::class, $result['date_from']);
        $this->assertSame('2025-01-01', $result['date_from']->format('Y-m-d'));
    }

    public function testBuildFiltersFromRequestWithDateTo(): void
    {
        $request = new \Symfony\Component\HttpFoundation\Request();
        $request->query->set('date_to', '2025-12-31');

        $result = $this->callPrivateMethod('buildFiltersFromRequest', $request);

        $this->assertArrayHasKey('date_to', $result);
        $this->assertInstanceOf(\DateTimeImmutable::class, $result['date_to']);
        $this->assertSame('2025-12-31', $result['date_to']->format('Y-m-d'));
    }

    public function testBuildFiltersFromRequestWithAllFilters(): void
    {
        $request = new \Symfony\Component\HttpFoundation\Request();
        $request->query->set('route', 'test_route');
        $request->query->set('min_request_time', '0.5');
        $request->query->set('max_request_time', '1.0');
        $request->query->set('min_query_count', '10');
        $request->query->set('max_query_count', '50');
        $request->query->set('date_from', '2025-01-01');
        $request->query->set('date_to', '2025-12-31');

        $result = $this->callPrivateMethod('buildFiltersFromRequest', $request);

        $this->assertArrayHasKey('route_name_pattern', $result);
        $this->assertArrayHasKey('min_request_time', $result);
        $this->assertArrayHasKey('max_request_time', $result);
        $this->assertArrayHasKey('min_query_count', $result);
        $this->assertArrayHasKey('max_query_count', $result);
        $this->assertArrayHasKey('date_from', $result);
        $this->assertArrayHasKey('date_to', $result);
        $this->assertCount(7, $result);
    }

    public function testBuildFiltersFromRequestIgnoresInvalidDate(): void
    {
        $request = new \Symfony\Component\HttpFoundation\Request();
        $request->query->set('date_from', 'invalid-date');
        $request->query->set('date_to', 'also-invalid');

        $result = $this->callPrivateMethod('buildFiltersFromRequest', $request);

        $this->assertArrayNotHasKey('date_from', $result);
        $this->assertArrayNotHasKey('date_to', $result);
    }

    public function testBuildFiltersFromRequestWithDateTimeFormat(): void
    {
        $request = new \Symfony\Component\HttpFoundation\Request();
        $request->query->set('date_from', '2025-01-01 10:30:00');
        $request->query->set('date_to', '2025-12-31 23:59:59');

        $result = $this->callPrivateMethod('buildFiltersFromRequest', $request);

        $this->assertArrayHasKey('date_from', $result);
        $this->assertArrayHasKey('date_to', $result);
        $this->assertInstanceOf(\DateTimeImmutable::class, $result['date_from']);
        $this->assertInstanceOf(\DateTimeImmutable::class, $result['date_to']);
    }
}
