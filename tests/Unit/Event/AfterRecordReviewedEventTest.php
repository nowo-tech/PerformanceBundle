<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Event;

use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Event\AfterRecordReviewedEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\Event;

final class AfterRecordReviewedEventTest extends TestCase
{
    public function testGettersReturnConstructorValues(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('dev');

        $event = new AfterRecordReviewedEvent($routeData);

        $this->assertSame($routeData->getId(), $event->getRouteDataId());
    }

    public function testRouteMetricsAreEqualOnMultipleCalls(): void
    {
        $routeData = new RouteData();
        $routeData->setName('test_route');
        $event = new AfterRecordReviewedEvent($routeData);

        $this->assertEquals($event->getRouteMetrics(), $event->getRouteMetrics());
        $this->assertSame($routeData->getId(), $event->getRouteDataId());
    }

    public function testExtendsSymfonyEvent(): void
    {
        $routeData = new RouteData();
        $event     = new AfterRecordReviewedEvent($routeData);

        $this->assertInstanceOf(Event::class, $event);
    }

    public function testRouteAccessorsReturnsCorrectRoute(): void
    {
        $routeData = new RouteData();
        $routeData->setName('api_users')->setEnv('prod');
        $event = new AfterRecordReviewedEvent($routeData);

        $this->assertSame('api_users', $event->getRouteName());
        $this->assertSame('prod', $event->getEnv());
    }

    public function testRouteAccessorsWithStageEnv(): void
    {
        $routeData = new RouteData();
        $routeData->setName('api_dashboard')->setEnv('stage');
        $event = new AfterRecordReviewedEvent($routeData);

        $this->assertSame('api_dashboard', $event->getRouteName());
        $this->assertSame('stage', $event->getEnv());
    }

    public function testRouteAccessorsWithTestEnv(): void
    {
        $routeData = new RouteData();
        $routeData->setName('api_health')->setEnv('test');
        $event = new AfterRecordReviewedEvent($routeData);

        $this->assertSame('api_health', $event->getRouteName());
        $this->assertSame('test', $event->getEnv());
    }
}
