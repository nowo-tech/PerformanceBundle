<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Event;

use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Event\AfterRecordReviewedEvent;
use PHPUnit\Framework\TestCase;

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
}
