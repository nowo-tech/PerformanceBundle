<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Event;

use Nowo\PerformanceBundle\Event\BeforeMetricsRecordedEvent;
use PHPUnit\Framework\TestCase;

final class BeforeMetricsRecordedEventTest extends TestCase
{
    public function testGettersReturnConstructorValues(): void
    {
        $event = new BeforeMetricsRecordedEvent(
            'app_home',
            'dev',
            0.5,
            10,
            0.2,
            ['id' => 123],
            1048576
        );

        $this->assertSame('app_home', $event->getRouteName());
        $this->assertSame('dev', $event->getEnv());
        $this->assertSame(0.5, $event->getRequestTime());
        $this->assertSame(10, $event->getTotalQueries());
        $this->assertSame(0.2, $event->getQueryTime());
        $this->assertSame(['id' => 123], $event->getParams());
        $this->assertSame(1048576, $event->getMemoryUsage());
        // Note: httpMethod is not part of BeforeMetricsRecordedEvent
    }

    public function testSettersModifyValues(): void
    {
        $event = new BeforeMetricsRecordedEvent(
            'app_home',
            'dev',
            0.5,
            10,
            0.2,
            ['id' => 123],
            1048576
        );

        $event->setRequestTime(0.8);
        $event->setTotalQueries(15);
        $event->setQueryTime(0.3);
        $event->setParams(['id' => 456]);
        $event->setMemoryUsage(2097152);

        $this->assertSame(0.8, $event->getRequestTime());
        $this->assertSame(15, $event->getTotalQueries());
        $this->assertSame(0.3, $event->getQueryTime());
        $this->assertSame(['id' => 456], $event->getParams());
        $this->assertSame(2097152, $event->getMemoryUsage());
    }

    public function testGettersReturnNullForOptionalParameters(): void
    {
        $event = new BeforeMetricsRecordedEvent('app_home', 'dev');

        $this->assertNull($event->getRequestTime());
        $this->assertNull($event->getTotalQueries());
        $this->assertNull($event->getQueryTime());
        $this->assertNull($event->getParams());
        $this->assertNull($event->getMemoryUsage());
    }

    public function testSettersCanSetNullValues(): void
    {
        $event = new BeforeMetricsRecordedEvent(
            'app_home',
            'dev',
            0.5,
            10,
            0.2,
            ['id' => 123],
            1048576
        );

        $event->setRequestTime(null);
        $event->setTotalQueries(null);
        $event->setQueryTime(null);
        $event->setParams(null);
        $event->setMemoryUsage(null);

        $this->assertNull($event->getRequestTime());
        $this->assertNull($event->getTotalQueries());
        $this->assertNull($event->getQueryTime());
        $this->assertNull($event->getParams());
        $this->assertNull($event->getMemoryUsage());
    }

    public function testSetMemoryUsageWithZero(): void
    {
        $event = new BeforeMetricsRecordedEvent('app_home', 'dev', null, null, null, null, 1048576);
        $event->setMemoryUsage(0);
        $this->assertSame(0, $event->getMemoryUsage());
    }

    public function testRouteNameAndEnvAreImmutable(): void
    {
        $event = new BeforeMetricsRecordedEvent('my_route', 'prod');
        $this->assertSame('my_route', $event->getRouteName());
        $this->assertSame('prod', $event->getEnv());
    }
}
