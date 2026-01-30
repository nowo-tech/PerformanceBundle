<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Event;

use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Event\BeforeRecordDeletedEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\Event;

final class BeforeRecordDeletedEventTest extends TestCase
{
    public function testGettersReturnConstructorValues(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('dev');

        $event = new BeforeRecordDeletedEvent($routeData);

        $this->assertSame($routeData, $event->getRouteData());
        $this->assertFalse($event->isDeletionPrevented());
    }

    public function testPreventDeletion(): void
    {
        $routeData = new RouteData();
        $event = new BeforeRecordDeletedEvent($routeData);

        $this->assertFalse($event->isDeletionPrevented());

        $event->preventDeletion();

        $this->assertTrue($event->isDeletionPrevented());
    }

    public function testPreventDeletionCalledMultipleTimesRemainsPrevented(): void
    {
        $routeData = new RouteData();
        $event = new BeforeRecordDeletedEvent($routeData);

        $event->preventDeletion();
        $event->preventDeletion();

        $this->assertTrue($event->isDeletionPrevented());
    }

    public function testGetRouteDataReturnsSameInstanceOnMultipleCalls(): void
    {
        $routeData = new RouteData();
        $routeData->setName('api_foo')->setEnv('prod');
        $event = new BeforeRecordDeletedEvent($routeData);

        $this->assertSame($event->getRouteData(), $event->getRouteData());
        $this->assertSame($routeData, $event->getRouteData());
    }

    public function testExtendsSymfonyEvent(): void
    {
        $routeData = new RouteData();
        $event = new BeforeRecordDeletedEvent($routeData);

        $this->assertInstanceOf(Event::class, $event);
    }

    public function testGetRouteDataReturnsRouteWithNameAndEnv(): void
    {
        $routeData = new RouteData();
        $routeData->setName('api_users')->setEnv('prod');

        $event = new BeforeRecordDeletedEvent($routeData);

        $this->assertSame('api_users', $event->getRouteData()->getName());
        $this->assertSame('prod', $event->getRouteData()->getEnv());
    }

    public function testGetRouteDataReturnsRouteWithStageEnv(): void
    {
        $routeData = new RouteData();
        $routeData->setName('api_dashboard')->setEnv('stage');

        $event = new BeforeRecordDeletedEvent($routeData);

        $this->assertSame('api_dashboard', $event->getRouteData()->getName());
        $this->assertSame('stage', $event->getRouteData()->getEnv());
    }

    public function testGetRouteDataReturnsRouteWithTestEnv(): void
    {
        $routeData = new RouteData();
        $routeData->setName('api_health')->setEnv('test');

        $event = new BeforeRecordDeletedEvent($routeData);

        $this->assertSame('api_health', $event->getRouteData()->getName());
        $this->assertSame('test', $event->getRouteData()->getEnv());
    }

    public function testPreventDeletionWithStageEnvRoute(): void
    {
        $routeData = new RouteData();
        $routeData->setName('api_dashboard')->setEnv('stage');
        $event = new BeforeRecordDeletedEvent($routeData);

        $this->assertSame('stage', $event->getRouteData()->getEnv());
        $this->assertFalse($event->isDeletionPrevented());

        $event->preventDeletion();

        $this->assertTrue($event->isDeletionPrevented());
    }
}
