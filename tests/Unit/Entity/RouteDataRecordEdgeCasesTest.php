<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Entity;

use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Entity\RouteDataRecord;
use PHPUnit\Framework\TestCase;

/**
 * Edge case tests for RouteDataRecord entity.
 */
final class RouteDataRecordEdgeCasesTest extends TestCase
{
    public function testSetResponseTimeWithNegativeValue(): void
    {
        $record = new RouteDataRecord();
        $record->setResponseTime(-0.1);

        $this->assertSame(-0.1, $record->getResponseTime());
    }

    public function testSetQueryTimeWithNegativeValue(): void
    {
        $record = new RouteDataRecord();
        $record->setQueryTime(-0.05);

        $this->assertSame(-0.05, $record->getQueryTime());
    }

    public function testSetResponseTimeWithVeryLargeValue(): void
    {
        $record = new RouteDataRecord();
        $record->setResponseTime(999.999);

        $this->assertSame(999.999, $record->getResponseTime());
    }

    public function testSetQueryTimeWithVeryLargeValue(): void
    {
        $record = new RouteDataRecord();
        $record->setQueryTime(500.123);

        $this->assertSame(500.123, $record->getQueryTime());
    }

    public function testSetTotalQueriesWithNegativeValue(): void
    {
        $record = new RouteDataRecord();
        $record->setTotalQueries(-5);

        $this->assertSame(-5, $record->getTotalQueries());
    }

    public function testSetTotalQueriesWithVeryLargeValue(): void
    {
        $record = new RouteDataRecord();
        $record->setTotalQueries(999999);

        $this->assertSame(999999, $record->getTotalQueries());
    }

    public function testSetMemoryUsageWithNegativeValue(): void
    {
        $record = new RouteDataRecord();
        $record->setMemoryUsage(-1024);

        $this->assertSame(-1024, $record->getMemoryUsage());
    }

    public function testSetStatusCodeWithEdgeCases(): void
    {
        $record = new RouteDataRecord();

        $record->setStatusCode(100);
        $this->assertSame(100, $record->getStatusCode());

        $record->setStatusCode(599);
        $this->assertSame(599, $record->getStatusCode());

        $record->setStatusCode(0);
        $this->assertSame(0, $record->getStatusCode());
    }

    public function testSetAccessedAtWithPastDate(): void
    {
        $record = new RouteDataRecord();
        $pastDate = new \DateTimeImmutable('2020-01-01 00:00:00');
        $record->setAccessedAt($pastDate);

        $this->assertSame($pastDate, $record->getAccessedAt());
    }

    public function testSetAccessedAtWithFutureDate(): void
    {
        $record = new RouteDataRecord();
        $futureDate = new \DateTimeImmutable('2030-01-01 00:00:00');
        $record->setAccessedAt($futureDate);

        $this->assertSame($futureDate, $record->getAccessedAt());
    }

    public function testSetRouteDataToNull(): void
    {
        $routeData = new RouteData();
        $record = new RouteDataRecord();
        $record->setRouteData($routeData);

        $this->assertSame($routeData, $record->getRouteData());

        $record->setRouteData(null);

        $this->assertNull($record->getRouteData());
    }

    public function testSetRouteDataReplacesExisting(): void
    {
        $routeData1 = new RouteData();
        $routeData2 = new RouteData();
        $record = new RouteDataRecord();

        $record->setRouteData($routeData1);
        $this->assertSame($routeData1, $record->getRouteData());

        $record->setRouteData($routeData2);
        $this->assertSame($routeData2, $record->getRouteData());
    }

    public function testAllSettersReturnSelf(): void
    {
        $record = new RouteDataRecord();
        $routeData = new RouteData();
        $date = new \DateTimeImmutable();

        $this->assertSame($record, $record->setRouteData($routeData));
        $this->assertSame($record, $record->setAccessedAt($date));
        $this->assertSame($record, $record->setStatusCode(200));
        $this->assertSame($record, $record->setResponseTime(0.5));
        $this->assertSame($record, $record->setTotalQueries(10));
        $this->assertSame($record, $record->setQueryTime(0.2));
        $this->assertSame($record, $record->setMemoryUsage(1024));
    }
}
