<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Event;

use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Event\AfterMetricsRecordedEvent;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Contracts\EventDispatcher\Event;

final class AfterMetricsRecordedEventTest extends TestCase
{
    public function testGettersReturnConstructorValues(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('dev');

        $event = new AfterMetricsRecordedEvent($routeData, true);

        $this->assertSame($routeData->getId(), $event->getRouteDataId());
        $this->assertTrue($event->isNew());
    }

    public function testIdNameAndEnvAccessors(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home')->setEnv('dev');

        $event = new AfterMetricsRecordedEvent($routeData, true);
        $this->assertNull($event->getRouteDataId());
        $this->assertSame('app_home', $event->getRouteName());
        $this->assertSame('dev', $event->getEnv());

        (new ReflectionProperty(RouteData::class, 'id'))->setValue($routeData, 42);
        $this->assertSame(42, $event->getRouteDataId());
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
            1048576,
        );

        $this->assertSame(0.25, $event->getRequestTime());
        $this->assertSame(12, $event->getTotalQueries());
        $this->assertSame(1048576, $event->getMemoryUsage());
    }

    public function testOptionalMetricsDefaultToNull(): void
    {
        $routeData = new RouteData();
        $event     = new AfterMetricsRecordedEvent($routeData, true);

        $this->assertNull($event->getRequestTime());
        $this->assertNull($event->getTotalQueries());
        $this->assertNull($event->getMemoryUsage());
    }

    public function testRouteMetricsAreEqualOnMultipleCalls(): void
    {
        $routeData = new RouteData();
        $routeData->setName('api_foo')->setEnv('prod');
        $event = new AfterMetricsRecordedEvent($routeData, true);

        $this->assertEquals($event->getRouteMetrics(), $event->getRouteMetrics());
        $this->assertSame($routeData->getId(), $event->getRouteDataId());
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
        $event     = new AfterMetricsRecordedEvent($routeData, true);

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

        $event = new AfterMetricsRecordedEvent($routeData, true, 0.0);

        $this->assertSame(0.0, $event->getRequestTime());
    }

    public function testGetTotalQueriesWithZero(): void
    {
        $routeData = new RouteData();
        $routeData->setName('api_foo')->setEnv('prod');

        $event = new AfterMetricsRecordedEvent($routeData, false, null, 0);

        $this->assertSame(0, $event->getTotalQueries());
    }

    public function testRouteAccessorsWithStageEnv(): void
    {
        $routeData = new RouteData();
        $routeData->setName('api_dashboard')->setEnv('stage');
        $event = new AfterMetricsRecordedEvent($routeData, true);

        $this->assertSame('api_dashboard', $event->getRouteName());
        $this->assertSame('stage', $event->getEnv());
    }

    public function testRouteAccessorsWithTestEnv(): void
    {
        $routeData = new RouteData();
        $routeData->setName('api_health')->setEnv('test');
        $event = new AfterMetricsRecordedEvent($routeData, false);

        $this->assertSame('api_health', $event->getRouteName());
        $this->assertSame('test', $event->getEnv());
        $this->assertFalse($event->isNew());
    }
}
