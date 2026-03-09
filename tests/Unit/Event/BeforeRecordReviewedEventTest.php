<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Event;

use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Event\BeforeRecordReviewedEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\Event;

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
        $event     = new BeforeRecordReviewedEvent($routeData, null, null, null);

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
        $event     = new BeforeRecordReviewedEvent($routeData, null, null, null);

        $this->assertFalse($event->isReviewPrevented());

        $event->preventReview();

        $this->assertTrue($event->isReviewPrevented());
    }

    public function testPreventReviewCalledMultipleTimesRemainsPrevented(): void
    {
        $routeData = new RouteData();
        $event     = new BeforeRecordReviewedEvent($routeData, null, null, null);

        $event->preventReview();
        $event->preventReview();

        $this->assertTrue($event->isReviewPrevented());
    }

    public function testGetRouteDataReturnsSameInstanceOnMultipleCalls(): void
    {
        $routeData = new RouteData();
        $routeData->setName('test_route');
        $event = new BeforeRecordReviewedEvent($routeData, true, false, 'reviewer');

        $this->assertSame($event->getRouteData(), $event->getRouteData());
        $this->assertSame($routeData, $event->getRouteData());
    }

    public function testExtendsSymfonyEvent(): void
    {
        $routeData = new RouteData();
        $event     = new BeforeRecordReviewedEvent($routeData, null, null, null);

        $this->assertInstanceOf(Event::class, $event);
    }

    public function testSetReviewedByWithEmptyString(): void
    {
        $routeData = new RouteData();
        $event     = new BeforeRecordReviewedEvent($routeData, null, null, 'original');

        $event->setReviewedBy('');

        $this->assertSame('', $event->getReviewedBy());
    }

    public function testGetQueriesImprovedFalseAndTimeImprovedTrue(): void
    {
        $routeData = new RouteData();
        $routeData->setName('api_foo');

        $event = new BeforeRecordReviewedEvent($routeData, false, true, 'reviewer');

        $this->assertFalse($event->getQueriesImproved());
        $this->assertTrue($event->getTimeImproved());
        $this->assertSame('reviewer', $event->getReviewedBy());
    }

    public function testSetQueriesImprovedAndTimeImprovedBothTrue(): void
    {
        $routeData = new RouteData();
        $event     = new BeforeRecordReviewedEvent($routeData, false, false, null);

        $event->setQueriesImproved(true);
        $event->setTimeImproved(true);

        $this->assertTrue($event->getQueriesImproved());
        $this->assertTrue($event->getTimeImproved());
    }

    public function testGetRouteDataWithStageEnv(): void
    {
        $routeData = new RouteData();
        $routeData->setName('api_dashboard')->setEnv('stage');
        $event = new BeforeRecordReviewedEvent($routeData, true, false, 'admin');

        $this->assertSame('api_dashboard', $event->getRouteData()->getName());
        $this->assertSame('stage', $event->getRouteData()->getEnv());
    }

    public function testSetReviewedByWithNull(): void
    {
        $routeData = new RouteData();
        $event     = new BeforeRecordReviewedEvent($routeData, null, null, 'original');

        $event->setReviewedBy(null);

        $this->assertNull($event->getReviewedBy());
    }
}
