<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Entity;

use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Entity\RouteDataRecord;
use PHPUnit\Framework\TestCase;

final class RouteDataRecordTest extends TestCase
{
    private RouteDataRecord $record;

    protected function setUp(): void
    {
        $this->record = new RouteDataRecord();
    }

    public function testIdIsInitiallyNull(): void
    {
        $this->assertNull($this->record->getId());
    }

    public function testRouteDataIsInitiallyNull(): void
    {
        $this->assertNull($this->record->getRouteData());
    }

    public function testSetAndGetRouteData(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('dev');

        $this->record->setRouteData($routeData);

        $this->assertSame($routeData, $this->record->getRouteData());
    }

    public function testAccessedAtIsSetOnConstruction(): void
    {
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->record->getAccessedAt());
    }

    public function testSetAndGetAccessedAt(): void
    {
        $date = new \DateTimeImmutable('2024-01-01 12:00:00');
        $this->record->setAccessedAt($date);

        $this->assertSame($date, $this->record->getAccessedAt());
    }

    public function testStatusCodeIsInitiallyNull(): void
    {
        $this->assertNull($this->record->getStatusCode());
    }

    public function testSetAndGetStatusCode(): void
    {
        $this->record->setStatusCode(200);
        $this->assertSame(200, $this->record->getStatusCode());

        $this->record->setStatusCode(404);
        $this->assertSame(404, $this->record->getStatusCode());

        $this->record->setStatusCode(null);
        $this->assertNull($this->record->getStatusCode());
    }

    public function testResponseTimeIsInitiallyNull(): void
    {
        $this->assertNull($this->record->getResponseTime());
    }

    public function testSetAndGetResponseTime(): void
    {
        $this->record->setResponseTime(0.5);
        $this->assertSame(0.5, $this->record->getResponseTime());

        $this->record->setResponseTime(1.2);
        $this->assertSame(1.2, $this->record->getResponseTime());

        $this->record->setResponseTime(null);
        $this->assertNull($this->record->getResponseTime());
    }

    public function testTotalQueriesIsInitiallyNull(): void
    {
        $this->assertNull($this->record->getTotalQueries());
    }

    public function testSetAndGetTotalQueries(): void
    {
        $this->record->setTotalQueries(10);
        $this->assertSame(10, $this->record->getTotalQueries());

        $this->record->setTotalQueries(0);
        $this->assertSame(0, $this->record->getTotalQueries());

        $this->record->setTotalQueries(null);
        $this->assertNull($this->record->getTotalQueries());
    }

    public function testQueryTimeIsInitiallyNull(): void
    {
        $this->assertNull($this->record->getQueryTime());
    }

    public function testSetAndGetQueryTime(): void
    {
        $this->record->setQueryTime(0.2);
        $this->assertSame(0.2, $this->record->getQueryTime());

        $this->record->setQueryTime(null);
        $this->assertNull($this->record->getQueryTime());
    }

    public function testMemoryUsageIsInitiallyNull(): void
    {
        $this->assertNull($this->record->getMemoryUsage());
    }

    public function testSetAndGetMemoryUsage(): void
    {
        $this->record->setMemoryUsage(1024);
        $this->assertSame(1024, $this->record->getMemoryUsage());

        $this->record->setMemoryUsage(null);
        $this->assertNull($this->record->getMemoryUsage());
    }

    public function testFluentInterface(): void
    {
        $routeData = new RouteData();
        $date = new \DateTimeImmutable();

        $result = $this->record
            ->setRouteData($routeData)
            ->setAccessedAt($date)
            ->setStatusCode(200)
            ->setResponseTime(0.5)
            ->setTotalQueries(10)
            ->setQueryTime(0.2)
            ->setMemoryUsage(2048);

        $this->assertSame($this->record, $result);
    }

    public function testSetMemoryUsageWithZero(): void
    {
        $this->record->setMemoryUsage(0);
        $this->assertSame(0, $this->record->getMemoryUsage());
    }

    public function testSetMemoryUsageWithLargeValue(): void
    {
        $bytes = 512 * 1024 * 1024;
        $this->record->setMemoryUsage($bytes);
        $this->assertSame($bytes, $this->record->getMemoryUsage());
    }

    public function testSetQueryTimeWithZero(): void
    {
        $this->record->setQueryTime(0.0);
        $this->assertSame(0.0, $this->record->getQueryTime());
    }
}
