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

    public function testFluentInterface(): void
    {
        $routeData = new RouteData();
        $date = new \DateTimeImmutable();

        $result = $this->record
            ->setRouteData($routeData)
            ->setAccessedAt($date)
            ->setStatusCode(200)
            ->setResponseTime(0.5);

        $this->assertSame($this->record, $result);
    }
}
