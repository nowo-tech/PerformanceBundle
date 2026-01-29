<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Model;

use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Model\RouteDataWithAggregates;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

final class RouteDataWithAggregatesTest extends TestCase
{
    private RouteData|MockObject $routeData;

    protected function setUp(): void
    {
        $this->routeData = $this->createMock(RouteData::class);
        $this->routeData->method('getId')->willReturn(42);
        $this->routeData->method('getEnv')->willReturn('dev');
        $this->routeData->method('getName')->willReturn('app_home');
        $this->routeData->method('getHttpMethod')->willReturn('GET');
        $this->routeData->method('isReviewed')->willReturn(false);
        $this->routeData->method('__toString')->willReturn('app_home');
        $this->routeData->method('getAccessRecords')
            ->willReturn(new \Doctrine\Common\Collections\ArrayCollection());
    }

    public function testAggregateGetters(): void
    {
        $aggregates = [
            'request_time' => 0.15,
            'query_time' => 0.05,
            'total_queries' => 8,
            'memory_usage' => 1024,
            'access_count' => 10,
            'status_codes' => [200 => 9, 404 => 1],
        ];
        $dto = new RouteDataWithAggregates($this->routeData, $aggregates);

        $this->assertSame(0.15, $dto->getRequestTime());
        $this->assertSame(0.05, $dto->getQueryTime());
        $this->assertSame(8, $dto->getTotalQueries());
        $this->assertSame(1024, $dto->getMemoryUsage());
        $this->assertSame(10, $dto->getAccessCount());
        $this->assertSame([200 => 9, 404 => 1], $dto->getStatusCodes());
    }

    public function testAggregateGettersWithNulls(): void
    {
        $aggregates = [
            'request_time' => null,
            'query_time' => null,
            'total_queries' => null,
            'memory_usage' => null,
            'access_count' => 0,
            'status_codes' => [],
        ];
        $dto = new RouteDataWithAggregates($this->routeData, $aggregates);

        $this->assertNull($dto->getRequestTime());
        $this->assertNull($dto->getQueryTime());
        $this->assertNull($dto->getTotalQueries());
        $this->assertNull($dto->getMemoryUsage());
        $this->assertSame(0, $dto->getAccessCount());
        $this->assertNull($dto->getStatusCodes());
    }

    public function testDelegatesToRouteData(): void
    {
        $dto = new RouteDataWithAggregates($this->routeData, ['access_count' => 1, 'status_codes' => []]);

        $this->assertSame($this->routeData, $dto->getRouteData());
        $this->assertSame(42, $dto->getId());
        $this->assertSame('dev', $dto->getEnv());
        $this->assertSame('app_home', $dto->getName());
        $this->assertSame('GET', $dto->getHttpMethod());
        $this->assertFalse($dto->isReviewed());
        $this->assertSame('app_home', (string) $dto);
    }

    public function testGetStatusCodeCount(): void
    {
        $aggregates = [
            'request_time' => null,
            'query_time' => null,
            'total_queries' => null,
            'memory_usage' => null,
            'access_count' => 10,
            'status_codes' => [200 => 8, 404 => 2],
        ];
        $dto = new RouteDataWithAggregates($this->routeData, $aggregates);

        $this->assertSame(8, $dto->getStatusCodeCount(200));
        $this->assertSame(2, $dto->getStatusCodeCount(404));
        $this->assertSame(0, $dto->getStatusCodeCount(500));
    }

    public function testGetStatusCodeRatio(): void
    {
        $aggregates = [
            'request_time' => null,
            'query_time' => null,
            'total_queries' => null,
            'memory_usage' => null,
            'access_count' => 10,
            'status_codes' => [200 => 8, 404 => 2],
        ];
        $dto = new RouteDataWithAggregates($this->routeData, $aggregates);

        $this->assertEqualsWithDelta(80.0, $dto->getStatusCodeRatio(200), 0.001);
        $this->assertEqualsWithDelta(20.0, $dto->getStatusCodeRatio(404), 0.001);
        $this->assertSame(0.0, $dto->getStatusCodeRatio(500));
    }

    public function testGetStatusCodeRatioEmptyCodes(): void
    {
        $aggregates = [
            'request_time' => null,
            'query_time' => null,
            'total_queries' => null,
            'memory_usage' => null,
            'access_count' => 0,
            'status_codes' => [],
        ];
        $dto = new RouteDataWithAggregates($this->routeData, $aggregates);

        $this->assertSame(0.0, $dto->getStatusCodeRatio(200));
    }

    public function testGetTotalResponses(): void
    {
        $aggregates = [
            'request_time' => null,
            'query_time' => null,
            'total_queries' => null,
            'memory_usage' => null,
            'access_count' => 5,
            'status_codes' => [200 => 3, 404 => 2],
        ];
        $dto = new RouteDataWithAggregates($this->routeData, $aggregates);

        $this->assertSame(5, $dto->getTotalResponses());
    }

    public function testGetTotalResponsesEmpty(): void
    {
        $aggregates = [
            'request_time' => null,
            'query_time' => null,
            'total_queries' => null,
            'memory_usage' => null,
            'access_count' => 0,
            'status_codes' => [],
        ];
        $dto = new RouteDataWithAggregates($this->routeData, $aggregates);

        $this->assertSame(0, $dto->getTotalResponses());
    }

    public function testGetMemoryUsageWithZero(): void
    {
        $aggregates = [
            'request_time' => null,
            'query_time' => null,
            'total_queries' => null,
            'memory_usage' => 0,
            'access_count' => 1,
            'status_codes' => [],
        ];
        $dto = new RouteDataWithAggregates($this->routeData, $aggregates);

        $this->assertSame(0, $dto->getMemoryUsage());
    }

    public function testGetMemoryUsageWithLargeValue(): void
    {
        $aggregates = [
            'request_time' => null,
            'query_time' => null,
            'total_queries' => null,
            'memory_usage' => 512 * 1024 * 1024,
            'access_count' => 1,
            'status_codes' => [],
        ];
        $dto = new RouteDataWithAggregates($this->routeData, $aggregates);

        $this->assertSame(536870912, $dto->getMemoryUsage());
    }

    public function testAggregateGettersWithMinimalAggregatesArray(): void
    {
        $aggregates = ['access_count' => 3, 'status_codes' => [200 => 3]];
        $dto = new RouteDataWithAggregates($this->routeData, $aggregates);

        $this->assertNull($dto->getRequestTime());
        $this->assertNull($dto->getQueryTime());
        $this->assertNull($dto->getTotalQueries());
        $this->assertNull($dto->getMemoryUsage());
        $this->assertSame(3, $dto->getAccessCount());
        $this->assertSame([200 => 3], $dto->getStatusCodes());
        $this->assertSame(3, $dto->getTotalResponses());
        $this->assertSame(100.0, $dto->getStatusCodeRatio(200));
    }

    public function testGetStatusCodeRatioWithZeroTotalReturnsZero(): void
    {
        $aggregates = [
            'request_time' => null,
            'query_time' => null,
            'total_queries' => null,
            'memory_usage' => null,
            'access_count' => 0,
            'status_codes' => [200 => 0, 404 => 0],
        ];
        $dto = new RouteDataWithAggregates($this->routeData, $aggregates);

        $this->assertSame(0.0, $dto->getStatusCodeRatio(200));
        $this->assertSame(0.0, $dto->getStatusCodeRatio(404));
        $this->assertSame(0, $dto->getTotalResponses());
    }
}
