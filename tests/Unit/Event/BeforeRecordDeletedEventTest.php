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

        $this->assertSame($routeData->getId(), $event->getRouteDataId());
        $this->assertFalse($event->isDeletionPrevented());
    }

    public function testPreventDeletion(): void
    {
        $routeData = new RouteData();
        $event     = new BeforeRecordDeletedEvent($routeData);

        $this->assertFalse($event->isDeletionPrevented());

        $event->preventDeletion();

        $this->assertTrue($event->isDeletionPrevented());
    }

    public function testPreventDeletionCalledMultipleTimesRemainsPrevented(): void
    {
        $routeData = new RouteData();
        $event     = new BeforeRecordDeletedEvent($routeData);

        $event->preventDeletion();
        $event->preventDeletion();

        $this->assertTrue($event->isDeletionPrevented());
    }

    public function testRouteMetricsAreEqualOnMultipleCalls(): void
    {
        $routeData = new RouteData();
        $routeData->setName('api_foo')->setEnv('prod');
        $event = new BeforeRecordDeletedEvent($routeData);

        $this->assertEquals($event->getRouteMetrics(), $event->getRouteMetrics());
        $this->assertSame($routeData->getId(), $event->getRouteDataId());
    }

    public function testExtendsSymfonyEvent(): void
    {
        $routeData = new RouteData();
        $event     = new BeforeRecordDeletedEvent($routeData);

        $this->assertInstanceOf(Event::class, $event);
    }

    public function testRouteAccessorsReturnsRouteWithNameAndEnv(): void
    {
        $routeData = new RouteData();
        $routeData->setName('api_users')->setEnv('prod');

        $event = new BeforeRecordDeletedEvent($routeData);

        $this->assertSame('api_users', $event->getRouteName());
        $this->assertSame('prod', $event->getEnv());
    }

    public function testRouteAccessorsReturnsRouteWithStageEnv(): void
    {
        $routeData = new RouteData();
        $routeData->setName('api_dashboard')->setEnv('stage');

        $event = new BeforeRecordDeletedEvent($routeData);

        $this->assertSame('api_dashboard', $event->getRouteName());
        $this->assertSame('stage', $event->getEnv());
    }

    public function testRouteAccessorsReturnsRouteWithTestEnv(): void
    {
        $routeData = new RouteData();
        $routeData->setName('api_health')->setEnv('test');

        $event = new BeforeRecordDeletedEvent($routeData);

        $this->assertSame('api_health', $event->getRouteName());
        $this->assertSame('test', $event->getEnv());
    }

    public function testPreventDeletionWithStageEnvRoute(): void
    {
        $routeData = new RouteData();
        $routeData->setName('api_dashboard')->setEnv('stage');
        $event = new BeforeRecordDeletedEvent($routeData);

        $this->assertSame('stage', $event->getEnv());
        $this->assertFalse($event->isDeletionPrevented());

        $event->preventDeletion();

        $this->assertTrue($event->isDeletionPrevented());
    }
}
