<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Event;

use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Event\AfterRecordDeletedEvent;
use PHPUnit\Framework\TestCase;

final class AfterRecordDeletedEventTest extends TestCase
{
    public function testGettersReturnConstructorValues(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('dev');

        $event = new AfterRecordDeletedEvent($routeData, 'dev');

        $this->assertSame($routeData, $event->getRouteData());
        $this->assertSame('dev', $event->getEnvironment());
    }
}
