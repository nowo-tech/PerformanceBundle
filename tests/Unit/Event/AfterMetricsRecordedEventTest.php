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
}
