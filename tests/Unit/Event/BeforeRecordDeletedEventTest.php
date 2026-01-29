<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Event;

use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Event\BeforeRecordDeletedEvent;
use PHPUnit\Framework\TestCase;

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
}
