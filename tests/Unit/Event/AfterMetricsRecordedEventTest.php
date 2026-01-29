<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Event;

use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Event\AfterMetricsRecordedEvent;
use PHPUnit\Framework\TestCase;

final class AfterMetricsRecordedEventTest extends TestCase
{
    public function testGettersReturnConstructorValues(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('dev');

        $event = new AfterMetricsRecordedEvent($routeData, true);

        $this->assertSame($routeData, $event->getRouteData());
        $this->assertTrue($event->isNew());
    }

    public function testIsNewReturnsFalseForUpdate(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('dev');

        $event = new AfterMetricsRecordedEvent($routeData, false);

        $this->assertFalse($event->isNew());
    }

    public function testOptionalMetricsReturnConstructorValues(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home')->setEnv('dev');

        $event = new AfterMetricsRecordedEvent(
            $routeData,
            true,
            0.25,
            12,
            1048576
        );

        $this->assertSame(0.25, $event->getRequestTime());
        $this->assertSame(12, $event->getTotalQueries());
        $this->assertSame(1048576, $event->getMemoryUsage());
    }

    public function testOptionalMetricsDefaultToNull(): void
    {
        $routeData = new RouteData();
        $event = new AfterMetricsRecordedEvent($routeData, true);

        $this->assertNull($event->getRequestTime());
        $this->assertNull($event->getTotalQueries());
        $this->assertNull($event->getMemoryUsage());
    }
}
