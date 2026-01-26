<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\DBAL;

use Nowo\PerformanceBundle\DBAL\QueryTrackingMiddleware;
use PHPUnit\Framework\TestCase;

final class QueryTrackingMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset middleware state before each test
        QueryTrackingMiddleware::reset();
    }

    public function testReset(): void
    {
        // Simulate some queries
        QueryTrackingMiddleware::startQuery('query1');
        QueryTrackingMiddleware::stopQuery('query1');
        
        $this->assertSame(1, QueryTrackingMiddleware::getQueryCount());
        $this->assertGreaterThan(0, QueryTrackingMiddleware::getTotalQueryTime());
        
        // Reset
        QueryTrackingMiddleware::reset();
        
        $this->assertSame(0, QueryTrackingMiddleware::getQueryCount());
        $this->assertSame(0.0, QueryTrackingMiddleware::getTotalQueryTime());
    }

    public function testStartAndStopQuery(): void
    {
        $queryId = 'test_query_1';
        
        // Start query
        QueryTrackingMiddleware::startQuery($queryId);
        
        // Query count should still be 0 until stopped
        $this->assertSame(0, QueryTrackingMiddleware::getQueryCount());
        
        // Stop query
        QueryTrackingMiddleware::stopQuery($queryId);
        
        // Now count should be 1
        $this->assertSame(1, QueryTrackingMiddleware::getQueryCount());
        $this->assertGreaterThan(0, QueryTrackingMiddleware::getTotalQueryTime());
    }

    public function testMultipleQueries(): void
    {
        // Track multiple queries
        for ($i = 1; $i <= 5; $i++) {
            $queryId = "query_{$i}";
            QueryTrackingMiddleware::startQuery($queryId);
            usleep(1000); // Small delay to ensure different timings
            QueryTrackingMiddleware::stopQuery($queryId);
        }
        
        $this->assertSame(5, QueryTrackingMiddleware::getQueryCount());
        $this->assertGreaterThan(0, QueryTrackingMiddleware::getTotalQueryTime());
    }

    public function testStopQueryWithoutStart(): void
    {
        // Stopping a query that was never started should not cause errors
        QueryTrackingMiddleware::stopQuery('non_existent_query');
        
        $this->assertSame(0, QueryTrackingMiddleware::getQueryCount());
        $this->assertSame(0.0, QueryTrackingMiddleware::getTotalQueryTime());
    }

    public function testGetQueryCountInitiallyZero(): void
    {
        $this->assertSame(0, QueryTrackingMiddleware::getQueryCount());
    }

    public function testGetTotalQueryTimeInitiallyZero(): void
    {
        $this->assertSame(0.0, QueryTrackingMiddleware::getTotalQueryTime());
    }

    public function testQueryTimeAccumulation(): void
    {
        $queryId1 = 'query1';
        $queryId2 = 'query2';
        
        // Track first query
        QueryTrackingMiddleware::startQuery($queryId1);
        usleep(1000);
        QueryTrackingMiddleware::stopQuery($queryId1);
        
        $time1 = QueryTrackingMiddleware::getTotalQueryTime();
        
        // Track second query
        QueryTrackingMiddleware::startQuery($queryId2);
        usleep(1000);
        QueryTrackingMiddleware::stopQuery($queryId2);
        
        $time2 = QueryTrackingMiddleware::getTotalQueryTime();
        
        // Total time should be greater than first query time
        $this->assertGreaterThan($time1, $time2);
    }
}
