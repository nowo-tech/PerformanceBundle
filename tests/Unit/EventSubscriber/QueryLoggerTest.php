<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\EventSubscriber;

use Nowo\PerformanceBundle\EventSubscriber\QueryLogger;
use PHPUnit\Framework\TestCase;

final class QueryLoggerTest extends TestCase
{
    public function testInitialState(): void
    {
        $logger = new QueryLogger();
        
        $this->assertSame(0, $logger->getQueryCount());
        $this->assertSame(0.0, $logger->getTotalQueryTime());
    }

    public function testStartAndStopQuery(): void
    {
        $logger = new QueryLogger();
        
        $logger->startQuery('query1');
        usleep(10000); // Sleep 10ms
        $logger->stopQuery('query1');
        
        $this->assertSame(1, $logger->getQueryCount());
        $this->assertGreaterThan(0.0, $logger->getTotalQueryTime());
    }

    public function testMultipleQueries(): void
    {
        $logger = new QueryLogger();
        
        $logger->startQuery('query1');
        usleep(5000);
        $logger->stopQuery('query1');
        
        $logger->startQuery('query2');
        usleep(5000);
        $logger->stopQuery('query2');
        
        $this->assertSame(2, $logger->getQueryCount());
        $this->assertGreaterThan(0.0, $logger->getTotalQueryTime());
    }

    public function testStopQueryWithoutStart(): void
    {
        $logger = new QueryLogger();
        
        // Stop query that was never started
        $logger->stopQuery('nonexistent');
        
        $this->assertSame(0, $logger->getQueryCount());
        $this->assertSame(0.0, $logger->getTotalQueryTime());
    }

    public function testReset(): void
    {
        $logger = new QueryLogger();
        
        $logger->startQuery('query1');
        usleep(5000);
        $logger->stopQuery('query1');
        
        $this->assertSame(1, $logger->getQueryCount());
        $this->assertGreaterThan(0.0, $logger->getTotalQueryTime());
        
        $logger->reset();
        
        $this->assertSame(0, $logger->getQueryCount());
        $this->assertSame(0.0, $logger->getTotalQueryTime());
    }

    public function testConcurrentQueries(): void
    {
        $logger = new QueryLogger();
        
        $logger->startQuery('query1');
        $logger->startQuery('query2');
        
        usleep(5000);
        
        $logger->stopQuery('query1');
        $logger->stopQuery('query2');
        
        $this->assertSame(2, $logger->getQueryCount());
        $this->assertGreaterThan(0.0, $logger->getTotalQueryTime());
    }

    public function testStopQuerySameIdTwiceSecondNoOp(): void
    {
        $logger = new QueryLogger();
        $logger->startQuery('q1');
        usleep(2000);
        $logger->stopQuery('q1');
        $countAfterFirst = $logger->getQueryCount();
        $timeAfterFirst = $logger->getTotalQueryTime();

        $logger->stopQuery('q1');

        $this->assertSame($countAfterFirst, $logger->getQueryCount());
        $this->assertSame($timeAfterFirst, $logger->getTotalQueryTime());
    }

    public function testResetThenStartAndStopQueryAgain(): void
    {
        $logger = new QueryLogger();
        $logger->startQuery('q1');
        usleep(5000);
        $logger->stopQuery('q1');
        $this->assertSame(1, $logger->getQueryCount());

        $logger->reset();
        $this->assertSame(0, $logger->getQueryCount());
        $this->assertSame(0.0, $logger->getTotalQueryTime());

        $logger->startQuery('q2');
        usleep(3000);
        $logger->stopQuery('q2');

        $this->assertSame(1, $logger->getQueryCount());
        $this->assertGreaterThan(0.0, $logger->getTotalQueryTime());
    }

    public function testGetQueryCountAndTotalQueryTimeBeforeAnyQuery(): void
    {
        $logger = new QueryLogger();

        $this->assertSame(0, $logger->getQueryCount());
        $this->assertSame(0.0, $logger->getTotalQueryTime());
    }

    public function testStartAndStopQueryWithEmptyStringId(): void
    {
        $logger = new QueryLogger();

        $logger->startQuery('');
        usleep(5000);
        $logger->stopQuery('');

        $this->assertSame(1, $logger->getQueryCount());
        $this->assertGreaterThan(0.0, $logger->getTotalQueryTime());
    }
}
