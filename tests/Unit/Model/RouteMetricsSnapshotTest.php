<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Model;

use DateTimeImmutable;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Event\AfterMetricsRecordedEvent;
use Nowo\PerformanceBundle\Event\AfterRecordReviewedEvent;
use Nowo\PerformanceBundle\Event\BeforeRecordDeletedEvent;
use Nowo\PerformanceBundle\Event\BeforeRecordReviewedEvent;
use Nowo\PerformanceBundle\Model\RouteMetricsSnapshot;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * REQ-ARCH-001 rules 3 / 4 (3.17.0): events carry ids and a framework-free snapshot; the entity accessor is unchanged.
 */
final class RouteMetricsSnapshotTest extends TestCase
{
    public function testFromRouteDataCopiesTheRow(): void
    {
        $route    = $this->route();
        $snapshot = RouteMetricsSnapshot::fromRouteData($route);

        self::assertSame(7, $snapshot->routeDataId);
        self::assertSame('app_home', $snapshot->routeName);
        self::assertSame('prod', $snapshot->env);
        self::assertSame('GET', $snapshot->httpMethod);
        self::assertTrue($snapshot->reviewed);
        self::assertSame($route->getReviewedAt(), $snapshot->reviewedAt);
        self::assertSame('alice', $snapshot->reviewedBy);
        self::assertTrue($snapshot->queriesImproved);
        self::assertFalse($snapshot->timeImproved);
        self::assertSame($route->getLastAccessedAt(), $snapshot->lastAccessedAt);
        self::assertNull($snapshot->isNew);
        self::assertNull($snapshot->requestTime);
        self::assertNull($snapshot->totalQueries);
        self::assertNull($snapshot->memoryUsage);
    }

    public function testSnapshotIsACopyNotALiveView(): void
    {
        $route    = $this->route();
        $snapshot = RouteMetricsSnapshot::fromRouteData($route);
        $route->setName('renamed');

        self::assertSame('app_home', $snapshot->routeName);
    }

    public function testFromRecordedEventAddsTheMeasuredValues(): void
    {
        $event    = new AfterMetricsRecordedEvent($this->route(), true, 0.5, 12, 2048);
        $snapshot = $event->getRouteMetrics();

        self::assertEquals(RouteMetricsSnapshot::fromRecordedEvent($event), $snapshot);
        self::assertSame(7, $snapshot->routeDataId);
        self::assertTrue($snapshot->isNew);
        self::assertSame(0.5, $snapshot->requestTime);
        self::assertSame(12, $snapshot->totalQueries);
        self::assertSame(2048, $snapshot->memoryUsage);
        self::assertEquals(RouteMetricsSnapshot::fromRecordedEvent($event), RouteMetricsSnapshot::fromContext($event));
        self::assertEquals(RouteMetricsSnapshot::fromRouteData($event->getRouteData()), RouteMetricsSnapshot::fromContext($event->getRouteData()));
    }

    public function testReviewAndDeleteEventsCarryIdsAndSnapshot(): void
    {
        $route  = $this->route();
        $events = [
            new BeforeRecordDeletedEvent($route),
            new BeforeRecordReviewedEvent($route, true, false, 'alice'),
            new AfterRecordReviewedEvent($route),
        ];

        foreach ($events as $event) {
            self::assertSame($route, $event->getRouteData(), $event::class . ': the deprecated accessor is unchanged');
            self::assertSame(7, $event->getRouteDataId());
            self::assertSame('app_home', $event->getRouteName());
            self::assertSame('prod', $event->getEnv());
            self::assertEquals(RouteMetricsSnapshot::fromRouteData($route), $event->getRouteMetrics());
        }
    }

    public function testUnflushedRowHasNoId(): void
    {
        $route = (new RouteData())->setName('new_route')->setEnv('dev');

        self::assertNull((new BeforeRecordDeletedEvent($route))->getRouteDataId());
        self::assertNull((new AfterRecordReviewedEvent($route))->getRouteMetrics()->routeDataId);
    }

    private function route(): RouteData
    {
        $route = (new RouteData())->setName('app_home')->setEnv('prod')->setHttpMethod('GET');
        $route->markAsReviewed(true, false, 'alice', new DateTimeImmutable('2026-11-01 10:00:00'));
        $route->setLastAccessedAt(new DateTimeImmutable('2026-11-02 11:00:00'));
        (new ReflectionProperty(RouteData::class, 'id'))->setValue($route, 7);

        return $route;
    }
}
