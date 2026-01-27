<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Event;

use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Event\BeforeRecordReviewedEvent;
use PHPUnit\Framework\TestCase;

final class BeforeRecordReviewedEventTest extends TestCase
{
    public function testGettersReturnConstructorValues(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home');

        $event = new BeforeRecordReviewedEvent($routeData, true, false, 'admin');

        $this->assertSame($routeData, $event->getRouteData());
        $this->assertTrue($event->getQueriesImproved());
        $this->assertFalse($event->getTimeImproved());
        $this->assertSame('admin', $event->getReviewedBy());
    }

    public function testSettersModifyValues(): void
    {
        $routeData = new RouteData();
        $event = new BeforeRecordReviewedEvent($routeData, null, null, null);

        $event->setQueriesImproved(true);
        $event->setTimeImproved(false);
        $event->setReviewedBy('user123');

        $this->assertTrue($event->getQueriesImproved());
        $this->assertFalse($event->getTimeImproved());
        $this->assertSame('user123', $event->getReviewedBy());
    }

    public function testPreventReview(): void
    {
        $routeData = new RouteData();
        $event = new BeforeRecordReviewedEvent($routeData, null, null, null);

        $this->assertFalse($event->isReviewPrevented());

        $event->preventReview();

        $this->assertTrue($event->isReviewPrevented());
    }
}
