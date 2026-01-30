<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Event;

use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Event\AfterMetricsRecordedEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\Event;

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

    public function testGetRouteDataReturnsSameInstanceOnMultipleCalls(): void
    {
        $routeData = new RouteData();
        $routeData->setName('api_foo')->setEnv('prod');
        $event = new AfterMetricsRecordedEvent($routeData, true);

        $this->assertSame($event->getRouteData(), $event->getRouteData());
        $this->assertSame($routeData, $event->getRouteData());
    }

    public function testGetMemoryUsageWithZero(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home')->setEnv('dev');

        $event = new AfterMetricsRecordedEvent($routeData, true, null, null, 0);

        $this->assertSame(0, $event->getMemoryUsage());
    }

    public function testExtendsSymfonyEvent(): void
    {
        $routeData = new RouteData();
        $event = new AfterMetricsRecordedEvent($routeData, true);

        $this->assertInstanceOf(Event::class, $event);
    }

    public function testGetRequestTimeAndTotalQueriesAndMemoryUsageWithValues(): void
    {
        $routeData = new RouteData();
        $routeData->setName('api_foo')->setEnv('prod');
        $event = new AfterMetricsRecordedEvent($routeData, false, 0.25, 8, 524288);

        $this->assertSame(0.25, $event->getRequestTime());
        $this->assertSame(8, $event->getTotalQueries());
        $this->assertSame(524288, $event->getMemoryUsage());
    }

    public function testGetRequestTimeWithZero(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home')->setEnv('dev');

        $event = new AfterMetricsRecordedEvent($routeData, true, 0.0, null, null);

        $this->assertSame(0.0, $event->getRequestTime());
    }

    public function testGetTotalQueriesWithZero(): void
    {
        $routeData = new RouteData();
        $routeData->setName('api_foo')->setEnv('prod');

        $event = new AfterMetricsRecordedEvent($routeData, false, null, 0, null);

        $this->assertSame(0, $event->getTotalQueries());
    }

    public function testGetRouteDataWithStageEnv(): void
    {
        $routeData = new RouteData();
        $routeData->setName('api_dashboard')->setEnv('stage');
        $event = new AfterMetricsRecordedEvent($routeData, true);

        $this->assertSame('api_dashboard', $event->getRouteData()->getName());
        $this->assertSame('stage', $event->getRouteData()->getEnv());
    }
}
