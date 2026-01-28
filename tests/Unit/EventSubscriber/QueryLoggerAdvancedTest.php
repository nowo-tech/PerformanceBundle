<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\EventSubscriber;

use Nowo\PerformanceBundle\EventSubscriber\QueryLogger;
use PHPUnit\Framework\TestCase;

/**
 * Advanced tests for QueryLogger edge cases.
 */
final class QueryLoggerAdvancedTest extends TestCase
{
    public function testStartQueryWithSameIdMultipleTimes(): void
    {
        $logger = new QueryLogger();
        
        $queryId = 'same_query_id';
        $logger->startQuery($queryId);
        usleep(1000);
        $logger->startQuery($queryId); // Start again with same ID
        
        $logger->stopQuery($queryId);
        
        // Should only count once (second start overwrites first)
        $this->assertSame(1, $logger->getQueryCount());
    }

    public function testStopQueryMultipleTimes(): void
    {
        $logger = new QueryLogger();
        
        $queryId = 'query1';
        $logger->startQuery($queryId);
        $logger->stopQuery($queryId);
        $logger->stopQuery($queryId); // Stop again
        
        // Should only count once
        $this->assertSame(1, $logger->getQueryCount());
    }

    public function testQueryTimeWithVeryShortQueries(): void
    {
        $logger = new QueryLogger();
        
        $queryId = 'fast_query';
        $logger->startQuery($queryId);
        $logger->stopQuery($queryId);
        
        // Even very fast queries should have some time (even if very small)
        $this->assertGreaterThanOrEqual(0.0, $logger->getTotalQueryTime());
    }

    public function testMultipleResets(): void
    {
        $logger = new QueryLogger();
        
        $logger->startQuery('query1');
        $logger->stopQuery('query1');
        
        $logger->reset();
        $this->assertSame(0, $logger->getQueryCount());
        
        $logger->startQuery('query2');
        $logger->stopQuery('query2');
        
        $logger->reset();
        $this->assertSame(0, $logger->getQueryCount());
    }

    public function testQueryTimeAccumulation(): void
    {
        $logger = new QueryLogger();
        
        $logger->startQuery('query1');
        usleep(1000);
        $logger->stopQuery('query1');
        
        $time1 = $logger->getTotalQueryTime();
        
        $logger->startQuery('query2');
        usleep(1000);
        $logger->stopQuery('query2');
        
        $time2 = $logger->getTotalQueryTime();
        
        // Total time should be greater than first query time
        $this->assertGreaterThan($time1, $time2);
    }

    public function testManyQueries(): void
    {
        $logger = new QueryLogger();
        
        // Track many queries
        for ($i = 1; $i <= 100; ++$i) {
            $queryId = "query_{$i}";
            $logger->startQuery($queryId);
            usleep(100); // Small delay
            $logger->stopQuery($queryId);
        }
        
        $this->assertSame(100, $logger->getQueryCount());
        $this->assertGreaterThan(0.0, $logger->getTotalQueryTime());
    }

    public function testResetClearsAllQueries(): void
    {
        $logger = new QueryLogger();
        
        // Start multiple queries but don't stop them
        $logger->startQuery('query1');
        $logger->startQuery('query2');
        $logger->startQuery('query3');
        
        $logger->reset();
        
        // All should be cleared
        $this->assertSame(0, $logger->getQueryCount());
        $this->assertSame(0.0, $logger->getTotalQueryTime());
        
        // Stopping queries that were started before reset should not affect count
        $logger->stopQuery('query1');
        $this->assertSame(0, $logger->getQueryCount());
    }

    public function testQueryTimePrecision(): void
    {
        $logger = new QueryLogger();
        
        $logger->startQuery('query1');
        usleep(10000); // 10ms
        $logger->stopQuery('query1');
        
        $time = $logger->getTotalQueryTime();
        
        // Time should be at least 0.01 seconds (10ms)
        $this->assertGreaterThanOrEqual(0.01, $time);
    }

    public function testMultipleInstancesAreIndependent(): void
    {
        $logger1 = new QueryLogger();
        $logger2 = new QueryLogger();
        
        $logger1->startQuery('query1');
        $logger1->stopQuery('query1');
        
        $logger2->startQuery('query2');
        $logger2->stopQuery('query2');
        
        // Each logger should have its own count
        $this->assertSame(1, $logger1->getQueryCount());
        $this->assertSame(1, $logger2->getQueryCount());
        
        // Resetting one should not affect the other
        $logger1->reset();
        $this->assertSame(0, $logger1->getQueryCount());
        $this->assertSame(1, $logger2->getQueryCount());
    }

    public function testStartQueryWithEmptyString(): void
    {
        $logger = new QueryLogger();
        
        $logger->startQuery('');
        usleep(1000);
        $logger->stopQuery('');
        
        $this->assertSame(1, $logger->getQueryCount());
    }

    public function testStartQueryWithSpecialCharacters(): void
    {
        $logger = new QueryLogger();
        
        $queryId = 'query_with_special_chars_!@#$%^&*()';
        $logger->startQuery($queryId);
        usleep(1000);
        $logger->stopQuery($queryId);
        
        $this->assertSame(1, $logger->getQueryCount());
    }

    public function testStartQueryWithUnicodeCharacters(): void
    {
        $logger = new QueryLogger();
        
        $queryId = 'query_with_unicode_émojis_🚀';
        $logger->startQuery($queryId);
        usleep(1000);
        $logger->stopQuery($queryId);
        
        $this->assertSame(1, $logger->getQueryCount());
    }

    public function testStopQueryWithDifferentIdThanStarted(): void
    {
        $logger = new QueryLogger();
        
        $logger->startQuery('query1');
        $logger->stopQuery('query2'); // Different ID
        
        // Should not count (query1 was never stopped)
        $this->assertSame(0, $logger->getQueryCount());
        
        // Now stop the correct one
        $logger->stopQuery('query1');
        $this->assertSame(1, $logger->getQueryCount());
    }

    public function testGetQueryCountAfterReset(): void
    {
        $logger = new QueryLogger();
        
        $logger->startQuery('query1');
        $logger->stopQuery('query1');
        $logger->reset();
        
        $this->assertSame(0, $logger->getQueryCount());
    }

    public function testGetTotalQueryTimeAfterReset(): void
    {
        $logger = new QueryLogger();
        
        $logger->startQuery('query1');
        usleep(1000);
        $logger->stopQuery('query1');
        $logger->reset();
        
        $this->assertSame(0.0, $logger->getTotalQueryTime());
    }
}
