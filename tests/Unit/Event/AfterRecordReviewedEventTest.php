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

        $this->assertSame($routeData, $event->getRouteData());
    }

    public function testGetRouteDataReturnsSameInstance(): void
    {
        $routeData = new RouteData();
        $routeData->setName('test_route');
        $event = new AfterRecordReviewedEvent($routeData);

        $this->assertSame($event->getRouteData(), $event->getRouteData());
        $this->assertSame($routeData, $event->getRouteData());
    }

    public function testExtendsSymfonyEvent(): void
    {
        $routeData = new RouteData();
        $event = new AfterRecordReviewedEvent($routeData);

        $this->assertInstanceOf(Event::class, $event);
    }

    public function testGetRouteDataReturnsCorrectRoute(): void
    {
        $routeData = new RouteData();
        $routeData->setName('api_users')->setEnv('prod');
        $event = new AfterRecordReviewedEvent($routeData);

        $this->assertSame('api_users', $event->getRouteData()->getName());
        $this->assertSame('prod', $event->getRouteData()->getEnv());
    }

    public function testGetRouteDataWithStageEnv(): void
    {
        $routeData = new RouteData();
        $routeData->setName('api_dashboard')->setEnv('stage');
        $event = new AfterRecordReviewedEvent($routeData);

        $this->assertSame('api_dashboard', $event->getRouteData()->getName());
        $this->assertSame('stage', $event->getRouteData()->getEnv());
    }
}
