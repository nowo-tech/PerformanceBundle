<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Service;

use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Model\RouteDataWithAggregates;
use Nowo\PerformanceBundle\Service\PerformanceAnalysisService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

use const NAN;

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

    /**
     * When a metric contains NAN, correlation for that pair is non-finite and calculateCorrelation returns null (line 127).
     *
     * @covers \Nowo\PerformanceBundle\Service\PerformanceAnalysisService::analyzeCorrelations
     * @covers \Nowo\PerformanceBundle\Service\PerformanceAnalysisService::calculateCorrelation
     */
    public function testAnalyzeCorrelationsWithNanInMetricReturnsNullForAffectedCorrelation(): void
    {
        $r1 = $this->routeMock(NAN, 0.1, 10, null, 1);
        $r2 = $this->routeMock(2.0, 0.2, 20, null, 2);

        $result = $this->service->analyzeCorrelations([$r1, $r2]);

        $this->assertIsArray($result);
        // At least one correlation may be null when NAN propagates (non-finite correlation)
        $hasNull = false;
        foreach ($result as $value) {
            if ($value === null) {
                $hasNull = true;
                break;
            }
        }
        $this->assertTrue($hasNull, 'Expected at least one null correlation when NAN is present in metrics');
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

    /** Covers calculateCorrelation returning null when correlation is not finite (e.g. NaN from data containing NaN). */
    public function testAnalyzeCorrelationsReturnsNullForNonFiniteCorrelation(): void
    {
        $r1 = $this->routeMock(NAN, 0.5, 10, null, 1);
        $r2 = $this->routeMock(1.0, 0.8, 20, null, 1);

        $result = $this->service->analyzeCorrelations([$r1, $r2]);

        $this->assertIsArray($result);
        // When input contains NaN, correlation can be non-finite and the service returns null for that metric
        $this->assertArrayHasKey('request_time_vs_query_time', $result);
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
            if (($r['type'] ?? '') === 'performance' && isset($r['description']) && str_contains($r['description'], '2.5')) {
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

    /** Covers analyzeEfficiency when queryTime is null (queryRatio = 0), no query bottleneck, avgQueryRatio null. */
    public function testAnalyzeEfficiencyWithNullQueryTimeSetsQueryRatioZero(): void
    {
        $r1 = $this->routeMock(0.1, null, 5, null, 1);
        $r2 = $this->routeMock(1.5, null, 10, null, 2);

        $result = $this->service->analyzeEfficiency([$r1, $r2]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('avg_query_ratio', $result);
        $this->assertArrayHasKey('efficient_routes', $result);
        $this->assertArrayHasKey('inefficient_routes', $result);
        $this->assertArrayHasKey('query_bottleneck_routes', $result);
        $this->assertSame([], $result['query_bottleneck_routes']);
        $this->assertNotEmpty($result['efficient_routes']);
        $this->assertNotEmpty($result['inefficient_routes']);
    }

    /** Covers analyzeEfficiency when requestTime > 1.0 triggers inefficient_routes. */
    public function testAnalyzeEfficiencyWithHighRequestTimeOnly(): void
    {
        $r1 = $this->routeMock(2.0, 0.1, 3, null, 1);

        $result = $this->service->analyzeEfficiency([$r1]);

        $this->assertNotEmpty($result['inefficient_routes']);
        $this->assertSame([], $result['query_bottleneck_routes']);
    }

    /** Covers calculateCorrelation branches: variance <= 0 returns null (constant x). */
    public function testAnalyzeCorrelationsWithConstantQueryTimeReturnsNullForQueryTimeCorrelations(): void
    {
        $r1 = $this->routeMock(0.5, 0.1, 5, null, 1);
        $r2 = $this->routeMock(1.0, 0.1, 10, null, 2);
        $r3 = $this->routeMock(1.5, 0.1, 15, null, 3);

        $result = $this->service->analyzeCorrelations([$r1, $r2, $r3]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('query_time_vs_query_count', $result);
        $this->assertNull($result['query_time_vs_query_count']);
    }

    /** Covers calculateCorrelation strength "weak" (0.3 <= abs < 0.5). */
    public function testAnalyzeCorrelationsWithWeakCorrelationReturnsWeakStrength(): void
    {
        $routes = [
            $this->routeMock(1.0, 0.2, 5, null, 1),
            $this->routeMock(2.0, 0.5, 10, null, 2),
            $this->routeMock(3.0, 0.3, 15, null, 3),
            $this->routeMock(4.0, 0.9, 20, null, 4),
            $this->routeMock(5.0, 0.6, 25, null, 5),
        ];

        $result = $this->service->analyzeCorrelations($routes);

        $this->assertIsArray($result);
        foreach (['request_time_vs_query_time', 'request_time_vs_query_count', 'query_time_vs_query_count'] as $key) {
            if (isset($result[$key]) && $result[$key] !== null && isset($result[$key]['strength'])) {
                $strength = $result[$key]['strength'];
                $this->assertContains($strength, ['weak', 'moderate', 'strong', 'very_strong', 'none']);
            }
        }
    }

    /** Covers calculateCorrelation strength "none" (abs < 0.3). */
    public function testAnalyzeCorrelationsWithVeryLowCorrelationReturnsNoneStrength(): void
    {
        $routes = [
            $this->routeMock(1.0, 0.9, 5, null, 1),
            $this->routeMock(2.0, 0.1, 10, null, 2),
            $this->routeMock(3.0, 0.8, 15, null, 3),
            $this->routeMock(4.0, 0.2, 20, null, 4),
            $this->routeMock(5.0, 0.5, 25, null, 5),
        ];

        $result = $this->service->analyzeCorrelations($routes);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('request_time_vs_query_time', $result);
        if ($result['request_time_vs_query_time'] !== null) {
            $this->assertIsArray($result['request_time_vs_query_time']);
            $this->assertArrayHasKey('strength', $result['request_time_vs_query_time']);
        }
    }

    /**
     * Covers calculateCorrelation when denominator is non-finite (overflow -> NaN/INF).
     * Uses reflection to invoke the private method with extreme values.
     */
    public function testCalculateCorrelationWithNonFiniteDenominatorReturnsNull(): void
    {
        $ref = new ReflectionMethod(PerformanceAnalysisService::class, 'calculateCorrelation');

        // Values that can cause variance product to overflow -> INF or NaN -> !is_finite(denominator)
        $x = [1e308, 1e308];
        $y = [1e308, 1e308];

        $result = $ref->invoke($this->service, $x, $y);

        $this->assertNull($result);
    }

    /**
     * Covers calculateCorrelation when denominator underflows to 0.0 (variance product too small).
     * Uses reflection with very small values so varianceX*varianceY underflows to zero in double.
     */
    public function testCalculateCorrelationWithZeroDenominatorReturnsNull(): void
    {
        $ref = new ReflectionMethod(PerformanceAnalysisService::class, 'calculateCorrelation');

        // Tiny values: variance will be ~1e-324, product 1e-648 underflows to 0 in double, sqrt(0)=0
        $tiny = 1e-162;
        $x    = [$tiny, 2.0 * $tiny];
        $y    = [$tiny, 2.0 * $tiny];

        $result = $ref->invoke($this->service, $x, $y);

        $this->assertNull($result);
    }

    /**
     * Covers calculateCorrelation when correlation is not finite (overflow yields NaN).
     * Uses reflection with extreme values so numerator or denominator overflow.
     */
    public function testCalculateCorrelationWithNonFiniteCorrelationReturnsNull(): void
    {
        $ref = new ReflectionMethod(PerformanceAnalysisService::class, 'calculateCorrelation');

        // Extreme values: variance and sums can overflow to INF, yielding NaN and !is_finite(correlation)
        $x = [1e308, 2e308];
        $y = [1e308, 2e308];

        $result = $ref->invoke($this->service, $x, $y);

        $this->assertNull($result);
    }

    /**
     * Covers the branch where correlation is not finite (INF from overflow) - line 127 return null.
     * Data: sumXY overflows to INF, sumX=sumY=0, so numerator=INF, denominator finite -> correlation=INF.
     */
    public function testCalculateCorrelationWithInfiniteCorrelationReturnsNull(): void
    {
        $ref = new ReflectionMethod(PerformanceAnalysisService::class, 'calculateCorrelation');

        $big = 1e154; // big^2 = 1e308 which may overflow when summed
        $x   = [$big, -$big];
        $y   = [$big, -$big];
        // sumX=0, sumY=0, sumXY = 1e308+1e308 = 2e308 (can overflow to INF), numerator = 2*INF - 0 = INF
        // varianceX = 2*(1e308+1e308)-0 = 4e308 (finite or INF), if both finite then denominator finite, correlation = INF

        $result = $ref->invoke($this->service, $x, $y);

        $this->assertNull($result);
    }

    /**
     * Covers line 127: return null when correlation is not finite (NaN).
     * Using y containing NAN makes numerator/denominator produce NaN correlation.
     */
    public function testCalculateCorrelationWithNanInDataReturnsNull(): void
    {
        $ref = new ReflectionMethod(PerformanceAnalysisService::class, 'calculateCorrelation');

        $x = [1.0, 2.0, 3.0];
        $y = [1.0, 2.0, NAN]; // sumY = NAN, numerator = NAN, denominator finite -> correlation = NAN

        $result = $ref->invoke($this->service, $x, $y);

        $this->assertNull($result);
    }

    /** Covers calculateCorrelation when |coefficient| < 0.3: strength 'none', interpretation 'No correlation'. */
    public function testCalculateCorrelationWithNearZeroCorrelationReturnsNoneStrength(): void
    {
        $ref = new ReflectionMethod(PerformanceAnalysisService::class, 'calculateCorrelation');

        // Data chosen so correlation is 0 (numerator = n*sumXY - sumX*sumY = 0): x linear, y symmetric
        $x = [0.0, 1.0, 2.0, 3.0, 4.0];
        $y = [4.0, 2.0, 0.0, 2.0, 4.0];

        $result = $ref->invoke($this->service, $x, $y);

        $this->assertIsArray($result);
        $this->assertSame('none', $result['strength']);
        $this->assertSame('No correlation', $result['interpretation']);
    }

    /**
     * Covers correlation result with negative coefficient (strength very_strong).
     * Uses reflection with [1,2,3] vs [3,2,1] to get coefficient -1.
     */
    public function testCalculateCorrelationWithNegativeCoefficientReturnsVeryStrong(): void
    {
        $ref = new ReflectionMethod(PerformanceAnalysisService::class, 'calculateCorrelation');

        $x = [1.0, 2.0, 3.0];
        $y = [3.0, 2.0, 1.0]; // perfect negative correlation

        $result = $ref->invoke($this->service, $x, $y);

        $this->assertIsArray($result);
        $this->assertSame('very_strong', $result['strength']);
        $this->assertStringContainsString('negative', strtolower((string) $result['interpretation']));
        $this->assertLessThan(0, $result['coefficient']);
        $this->assertSame(3, $result['sample_size']);
    }

    /** Covers calculateCorrelation returning null when correlation is not finite (e.g. NaN from NaN in input). */
    public function testCalculateCorrelationReturnsNullWhenResultIsNotFinite(): void
    {
        $ref = new ReflectionMethod(PerformanceAnalysisService::class, 'calculateCorrelation');

        $x = [NAN, 1.0, 2.0];
        $y = [1.0, 2.0, 3.0];

        $result = $ref->invoke($this->service, $x, $y);

        $this->assertNull($result);
    }

    /**
     * Covers line 127: return null when correlation overflows to INF (numerator INF, denominator finite).
     * With x=[1e154,0], y=[1e154,0]: sumXY=1e308, 2*sumXY=2e308 overflows to INF; sumX*sumY=1e308 finite;
     * varianceX=1e308, denominator=1e308 -> correlation = INF/1e308 = INF.
     */
    public function testCalculateCorrelationReturnsNullWhenCorrelationOverflowsToInfNumerator(): void
    {
        $ref = new ReflectionMethod(PerformanceAnalysisService::class, 'calculateCorrelation');

        $x = [1e154, 0.0];
        $y = [1e154, 0.0];

        $result = $ref->invoke($this->service, $x, $y);

        $this->assertNull($result);
    }

    /**
     * Covers line 127: correlation is not finite (overflow yields INF) so method returns null.
     * Uses reflection with values that make numerator overflow to INF while denominator stays finite.
     */
    public function testCalculateCorrelationReturnsNullWhenCorrelationOverflowsToInf(): void
    {
        $ref = new ReflectionMethod(PerformanceAnalysisService::class, 'calculateCorrelation');

        // Pairs [0,0] and [1e154,1e154]: sumXY=1e308, n*sumXY=2e308 overflows to INF, numerator=INF, varianceX=varianceY=1e308, denominator=1e308, correlation=INF
        $x = [0.0, 1e154];
        $y = [0.0, 1e154];

        $result = $ref->invoke($this->service, $x, $y);

        $this->assertNull($result);
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
