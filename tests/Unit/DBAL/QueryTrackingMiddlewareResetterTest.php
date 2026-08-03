<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\DBAL;

use Nowo\PerformanceBundle\DBAL\QueryTrackingCounters;
use Nowo\PerformanceBundle\DBAL\QueryTrackingMiddlewareResetter;
use PHPUnit\Framework\TestCase;

final class QueryTrackingMiddlewareResetterTest extends TestCase
{
    public function testResetClearsCounters(): void
    {
        $counters = new QueryTrackingCounters();
        $counters->startQuery('q1');
        $counters->stopQuery('q1');
        $this->assertSame(1, $counters->getQueryCount());

        $resetter = new QueryTrackingMiddlewareResetter($counters);
        $resetter->reset();

        $this->assertSame(0, $counters->getQueryCount());
        $this->assertSame(0.0, $counters->getTotalQueryTime());
    }

    public function testResetOnFreshCounters(): void
    {
        $counters = new QueryTrackingCounters();
        $resetter = new QueryTrackingMiddlewareResetter($counters);
        $resetter->reset();

        $this->assertSame(0, $counters->getQueryCount());
    }
}
