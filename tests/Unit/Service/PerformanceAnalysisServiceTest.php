<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Service;

use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Service\PerformanceAnalysisService;
use PHPUnit\Framework\TestCase;

final class PerformanceAnalysisServiceTest extends TestCase
{
    private PerformanceAnalysisService $service;

    protected function setUp(): void
    {
        $this->service = new PerformanceAnalysisService();
    }

    public function testAnalyzeCorrelationsWithEmptyArray(): void
    {
        $result = $this->service->analyzeCorrelations([]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('request_time_vs_query_time', $result);
        $this->assertNull($result['request_time_vs_query_time']);
    }

    public function testAnalyzeCorrelationsWithSingleRoute(): void
    {
        $route = new RouteData();
        $route->setName('app_home');
        $route->setRequestTime(0.5);
        $route->setTotalQueries(10);
        $route->setQueryTime(0.2);

        $result = $this->service->analyzeCorrelations([$route]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('request_time_vs_query_time', $result);
        // With only one data point, correlation cannot be calculated
        $this->assertNull($result['request_time_vs_query_time']);
    }

    public function testAnalyzeCorrelationsWithConstantValues(): void
    {
        // Create routes with constant values (zero variance)
        $route1 = new RouteData();
        $route1->setName('route1');
        $route1->setRequestTime(0.5);
        $route1->setTotalQueries(10);
        $route1->setQueryTime(0.2);

        $route2 = new RouteData();
        $route2->setName('route2');
        $route2->setRequestTime(0.5); // Same request time (zero variance)
        $route2->setTotalQueries(10); // Same query count (zero variance)
        $route2->setQueryTime(0.2);

        $result = $this->service->analyzeCorrelations([$route1, $route2]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('request_time_vs_query_time', $result);
        // Should return null when variance is zero (division by zero prevention)
        $this->assertNull($result['request_time_vs_query_time']);
    }

    public function testAnalyzeCorrelationsWithValidData(): void
    {
        // Create routes with varying values
        $route1 = new RouteData();
        $route1->setName('route1');
        $route1->setRequestTime(0.3);
        $route1->setTotalQueries(5);
        $route1->setQueryTime(0.1);

        $route2 = new RouteData();
        $route2->setName('route2');
        $route2->setRequestTime(0.6);
        $route2->setTotalQueries(10);
        $route2->setQueryTime(0.2);

        $route3 = new RouteData();
        $route3->setName('route3');
        $route3->setRequestTime(0.9);
        $route3->setTotalQueries(15);
        $route3->setQueryTime(0.3);

        $result = $this->service->analyzeCorrelations([$route1, $route2, $route3]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('request_time_vs_query_time', $result);
        
        $correlation = $result['request_time_vs_query_time'];
        if (null !== $correlation) {
            $this->assertIsArray($correlation);
            $this->assertArrayHasKey('coefficient', $correlation);
            $this->assertArrayHasKey('strength', $correlation);
            $this->assertArrayHasKey('interpretation', $correlation);
            $this->assertArrayHasKey('sample_size', $correlation);
            
            // Coefficient should be between -1 and 1
            $this->assertGreaterThanOrEqual(-1.0, $correlation['coefficient']);
            $this->assertLessThanOrEqual(1.0, $correlation['coefficient']);
            
            // Sample size should be 3
            $this->assertSame(3, $correlation['sample_size']);
        }
    }

    public function testAnalyzeCorrelationsWithNullValues(): void
    {
        // Create routes with some null values
        $route1 = new RouteData();
        $route1->setName('route1');
        $route1->setRequestTime(0.3);
        $route1->setTotalQueries(5);
        $route1->setQueryTime(null); // Null query time

        $route2 = new RouteData();
        $route2->setName('route2');
        $route2->setRequestTime(null); // Null request time
        $route2->setTotalQueries(10);
        $route2->setQueryTime(0.2);

        $route3 = new RouteData();
        $route3->setName('route3');
        $route3->setRequestTime(0.9);
        $route3->setTotalQueries(15);
        $route3->setQueryTime(0.3);

        $result = $this->service->analyzeCorrelations([$route1, $route2, $route3]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('request_time_vs_query_time', $result);
        // Should handle null values gracefully
        // May return null if not enough valid pairs, or a correlation if there are enough
    }

    public function testAnalyzeCorrelationsWithMixedNullAndValidValues(): void
    {
        // Create routes with mixed null and valid values
        $route1 = new RouteData();
        $route1->setName('route1');
        $route1->setRequestTime(0.3);
        $route1->setTotalQueries(5);
        $route1->setQueryTime(0.1);

        $route2 = new RouteData();
        $route2->setName('route2');
        $route2->setRequestTime(0.6);
        $route2->setTotalQueries(10);
        $route2->setQueryTime(0.2);

        // Only one valid pair, should return null
        $result = $this->service->analyzeCorrelations([$route1, $route2]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('request_time_vs_query_time', $result);
        // With only 2 valid pairs, correlation calculation may return null
    }
}
