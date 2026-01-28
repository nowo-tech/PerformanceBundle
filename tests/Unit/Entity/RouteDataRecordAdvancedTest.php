<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Entity;

use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Entity\RouteDataRecord;
use PHPUnit\Framework\TestCase;

/**
 * Advanced tests for RouteDataRecord entity edge cases.
 */
final class RouteDataRecordAdvancedTest extends TestCase
{
    public function testConstructorInitializesAccessedAt(): void
    {
        $record = new RouteDataRecord();

        $this->assertNotNull($record->getAccessedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $record->getAccessedAt());
    }

    public function testSetRouteData(): void
    {
        $record = new RouteDataRecord();
        $routeData = new RouteData();

        $record->setRouteData($routeData);

        $this->assertSame($routeData, $record->getRouteData());
    }

    public function testSetRouteDataToNull(): void
    {
        $record = new RouteDataRecord();
        $routeData = new RouteData();

        $record->setRouteData($routeData);
        $record->setRouteData(null);

        $this->assertNull($record->getRouteData());
    }

    public function testSetAccessedAt(): void
    {
        $record = new RouteDataRecord();
        $date = new \DateTimeImmutable('2026-01-01 12:00:00');

        $record->setAccessedAt($date);

        $this->assertSame($date, $record->getAccessedAt());
    }

    public function testSetStatusCode(): void
    {
        $record = new RouteDataRecord();

        $this->assertNull($record->getStatusCode());

        $record->setStatusCode(200);
        $this->assertSame(200, $record->getStatusCode());

        $record->setStatusCode(404);
        $this->assertSame(404, $record->getStatusCode());

        $record->setStatusCode(null);
        $this->assertNull($record->getStatusCode());
    }

    public function testSetStatusCodeWithVariousCodes(): void
    {
        $record = new RouteDataRecord();

        $statusCodes = [200, 201, 301, 302, 400, 401, 403, 404, 500, 502, 503];

        foreach ($statusCodes as $code) {
            $record->setStatusCode($code);
            $this->assertSame($code, $record->getStatusCode());
        }
    }

    public function testSetResponseTime(): void
    {
        $record = new RouteDataRecord();

        $this->assertNull($record->getResponseTime());

        $record->setResponseTime(0.5);
        $this->assertSame(0.5, $record->getResponseTime());

        $record->setResponseTime(1.0);
        $this->assertSame(1.0, $record->getResponseTime());

        $record->setResponseTime(null);
        $this->assertNull($record->getResponseTime());
    }

    public function testSetResponseTimeWithVerySmallValues(): void
    {
        $record = new RouteDataRecord();

        $record->setResponseTime(0.001);
        $this->assertSame(0.001, $record->getResponseTime());

        $record->setResponseTime(0.0001);
        $this->assertSame(0.0001, $record->getResponseTime());
    }

    public function testSetResponseTimeWithLargeValues(): void
    {
        $record = new RouteDataRecord();

        $record->setResponseTime(10.0);
        $this->assertSame(10.0, $record->getResponseTime());

        $record->setResponseTime(100.0);
        $this->assertSame(100.0, $record->getResponseTime());
    }

    public function testSetResponseTimeWithZero(): void
    {
        $record = new RouteDataRecord();

        $record->setResponseTime(0.0);
        $this->assertSame(0.0, $record->getResponseTime());
    }

    public function testSetResponseTimeWithNegativeValue(): void
    {
        $record = new RouteDataRecord();

        // Negative values are technically valid floats, though not realistic
        $record->setResponseTime(-1.0);
        $this->assertSame(-1.0, $record->getResponseTime());
    }

    public function testSetAndGetTotalQueries(): void
    {
        $record = new RouteDataRecord();

        $this->assertNull($record->getTotalQueries());
        $record->setTotalQueries(5);
        $this->assertSame(5, $record->getTotalQueries());
        $record->setTotalQueries(null);
        $this->assertNull($record->getTotalQueries());
    }

    public function testSetAndGetQueryTime(): void
    {
        $record = new RouteDataRecord();

        $this->assertNull($record->getQueryTime());
        $record->setQueryTime(0.15);
        $this->assertSame(0.15, $record->getQueryTime());
        $record->setQueryTime(null);
        $this->assertNull($record->getQueryTime());
    }

    public function testSetAndGetMemoryUsage(): void
    {
        $record = new RouteDataRecord();

        $this->assertNull($record->getMemoryUsage());
        $record->setMemoryUsage(8192);
        $this->assertSame(8192, $record->getMemoryUsage());
        $record->setMemoryUsage(null);
        $this->assertNull($record->getMemoryUsage());
    }

    public function testGetIdInitiallyNull(): void
    {
        $record = new RouteDataRecord();

        $this->assertNull($record->getId());
    }

    public function testSetAccessedAtWithDifferentDates(): void
    {
        $record = new RouteDataRecord();

        $dates = [
            new \DateTimeImmutable('2026-01-01 00:00:00'),
            new \DateTimeImmutable('2026-01-01 12:00:00'),
            new \DateTimeImmutable('2026-12-31 23:59:59'),
        ];

        foreach ($dates as $date) {
            $record->setAccessedAt($date);
            $this->assertSame($date, $record->getAccessedAt());
        }
    }

    public function testFluentInterface(): void
    {
        $record = new RouteDataRecord();
        $routeData = new RouteData();
        $date = new \DateTimeImmutable();

        $result = $record
            ->setRouteData($routeData)
            ->setAccessedAt($date)
            ->setStatusCode(200)
            ->setResponseTime(0.5)
            ->setTotalQueries(12)
            ->setQueryTime(0.1)
            ->setMemoryUsage(4096);

        $this->assertSame($record, $result);
        $this->assertSame($routeData, $record->getRouteData());
        $this->assertSame($date, $record->getAccessedAt());
        $this->assertSame(200, $record->getStatusCode());
        $this->assertSame(0.5, $record->getResponseTime());
        $this->assertSame(12, $record->getTotalQueries());
        $this->assertSame(0.1, $record->getQueryTime());
        $this->assertSame(4096, $record->getMemoryUsage());
    }

    public function testRecordWithAllFieldsSet(): void
    {
        $record = new RouteDataRecord();
        $routeData = new RouteData();
        $date = new \DateTimeImmutable('2026-01-01 12:00:00');

        $record->setRouteData($routeData);
        $record->setAccessedAt($date);
        $record->setStatusCode(200);
        $record->setResponseTime(0.5);
        $record->setTotalQueries(8);
        $record->setQueryTime(0.25);
        $record->setMemoryUsage(16384);

        $this->assertSame($routeData, $record->getRouteData());
        $this->assertSame($date, $record->getAccessedAt());
        $this->assertSame(200, $record->getStatusCode());
        $this->assertSame(0.5, $record->getResponseTime());
        $this->assertSame(8, $record->getTotalQueries());
        $this->assertSame(0.25, $record->getQueryTime());
        $this->assertSame(16384, $record->getMemoryUsage());
    }

    public function testRecordWithNullFields(): void
    {
        $record = new RouteDataRecord();

        $record->setRouteData(null);
        $record->setStatusCode(null);
        $record->setResponseTime(null);
        $record->setTotalQueries(null);
        $record->setQueryTime(null);
        $record->setMemoryUsage(null);

        $this->assertNull($record->getRouteData());
        $this->assertNull($record->getStatusCode());
        $this->assertNull($record->getResponseTime());
        $this->assertNull($record->getTotalQueries());
        $this->assertNull($record->getQueryTime());
        $this->assertNull($record->getMemoryUsage());
        // accessedAt should still be set (from constructor)
        $this->assertNotNull($record->getAccessedAt());
    }
}
