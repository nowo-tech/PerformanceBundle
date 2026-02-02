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

    public function testGetIdReturnsValueWhenSet(): void
    {
        $reflection = new \ReflectionClass($this->record);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($this->record, 42);

        $this->assertSame(42, $this->record->getId());
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

    public function testSetRouteDataReturnsSelf(): void
    {
        $routeData = new RouteData();
        $routeData->setName('api_foo')->setEnv('dev');

        $result = $this->record->setRouteData($routeData);

        $this->assertSame($this->record, $result);
    }

    public function testSetRouteDataNull(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home')->setEnv('dev');
        $this->record->setRouteData($routeData);
        $this->assertSame($routeData, $this->record->getRouteData());

        $result = $this->record->setRouteData(null);

        $this->assertSame($this->record, $result);
        $this->assertNull($this->record->getRouteData());
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
            ->setMemoryUsage(2048)
            ->setRequestId('req-fluent');

        $this->assertSame($this->record, $result);
        $this->assertSame('req-fluent', $this->record->getRequestId());
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

    public function testSetResponseTimeWithZero(): void
    {
        $this->record->setResponseTime(0.0);
        $this->assertSame(0.0, $this->record->getResponseTime());
    }

    public function testSetResponseTimeWithNull(): void
    {
        $this->record->setResponseTime(0.5);
        $this->assertSame(0.5, $this->record->getResponseTime());

        $this->record->setResponseTime(null);
        $this->assertNull($this->record->getResponseTime());
    }

    public function testRequestIdIsInitiallyNull(): void
    {
        $this->assertNull($this->record->getRequestId());
    }

    public function testSetAndGetRequestId(): void
    {
        $this->record->setRequestId('abc123def456');
        $this->assertSame('abc123def456', $this->record->getRequestId());

        $this->record->setRequestId(null);
        $this->assertNull($this->record->getRequestId());
    }

    public function testSetRequestIdReturnsSelf(): void
    {
        $result = $this->record->setRequestId('req-xyz');
        $this->assertSame($this->record, $result);
    }

    public function testRefererIsInitiallyNull(): void
    {
        $this->assertNull($this->record->getReferer());
    }

    public function testSetAndGetReferer(): void
    {
        $url = 'https://example.com/page';
        $this->record->setReferer($url);
        $this->assertSame($url, $this->record->getReferer());

        $this->record->setReferer(null);
        $this->assertNull($this->record->getReferer());
    }

    public function testSetRefererReturnsSelf(): void
    {
        $result = $this->record->setReferer('https://other.com');
        $this->assertSame($this->record, $result);
    }

    public function testFluentInterfaceIncludesReferer(): void
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
            ->setMemoryUsage(2048)
            ->setRequestId('req-fluent')
            ->setReferer('https://referer.example/');

        $this->assertSame($this->record, $result);
        $this->assertSame('https://referer.example/', $this->record->getReferer());
    }

    public function testUserIdentifierIsInitiallyNull(): void
    {
        $this->assertNull($this->record->getUserIdentifier());
    }

    public function testSetAndGetUserIdentifier(): void
    {
        $this->record->setUserIdentifier('john@example.com');
        $this->assertSame('john@example.com', $this->record->getUserIdentifier());

        $this->record->setUserIdentifier(null);
        $this->assertNull($this->record->getUserIdentifier());
    }

    public function testSetUserIdentifierReturnsSelf(): void
    {
        $result = $this->record->setUserIdentifier('admin@example.com');
        $this->assertSame($this->record, $result);
    }

    public function testUserIdIsInitiallyNull(): void
    {
        $this->assertNull($this->record->getUserId());
    }

    public function testSetAndGetUserId(): void
    {
        $this->record->setUserId('uuid-42');
        $this->assertSame('uuid-42', $this->record->getUserId());

        $this->record->setUserId('12345');
        $this->assertSame('12345', $this->record->getUserId());

        $this->record->setUserId(null);
        $this->assertNull($this->record->getUserId());
    }

    public function testSetUserIdReturnsSelf(): void
    {
        $result = $this->record->setUserId('user-1');
        $this->assertSame($this->record, $result);
    }

    public function testSetResponseTimeReturnsSelf(): void
    {
        $result = $this->record->setResponseTime(0.5);
        $this->assertSame($this->record, $result);
    }

    public function testSetStatusCodeReturnsSelf(): void
    {
        $result = $this->record->setStatusCode(200);
        $this->assertSame($this->record, $result);
    }

    public function testSetStatusCodeWith503(): void
    {
        $this->record->setStatusCode(503);
        $this->assertSame(503, $this->record->getStatusCode());
    }

    public function testSetStatusCodeWith500(): void
    {
        $this->record->setStatusCode(500);
        $this->assertSame(500, $this->record->getStatusCode());
    }

    public function testSetStatusCodeWith502(): void
    {
        $this->record->setStatusCode(502);
        $this->assertSame(502, $this->record->getStatusCode());
    }

    public function testSetStatusCodeWith504(): void
    {
        $this->record->setStatusCode(504);
        $this->assertSame(504, $this->record->getStatusCode());
    }

    public function testSetStatusCodeWith501(): void
    {
        $this->record->setStatusCode(501);
        $this->assertSame(501, $this->record->getStatusCode());
    }

    public function testSetStatusCodeWith400(): void
    {
        $this->record->setStatusCode(400);
        $this->assertSame(400, $this->record->getStatusCode());
    }

    public function testSetAccessedAtReturnsSelf(): void
    {
        $date = new \DateTimeImmutable('2024-06-01 10:00:00');
        $result = $this->record->setAccessedAt($date);
        $this->assertSame($this->record, $result);
    }

    public function testSetTotalQueriesReturnsSelf(): void
    {
        $result = $this->record->setTotalQueries(15);
        $this->assertSame($this->record, $result);
    }

    public function testSetQueryTimeReturnsSelf(): void
    {
        $result = $this->record->setQueryTime(0.1);
        $this->assertSame($this->record, $result);
    }

    public function testSetMemoryUsageReturnsSelf(): void
    {
        $result = $this->record->setMemoryUsage(1024);
        $this->assertSame($this->record, $result);
    }

    public function testSetRefererWithEmptyString(): void
    {
        $this->record->setReferer('');
        $this->assertSame('', $this->record->getReferer());
    }

    public function testSetTotalQueriesWithZero(): void
    {
        $this->record->setTotalQueries(0);
        $this->assertSame(0, $this->record->getTotalQueries());
    }

    public function testSetTotalQueriesWithLargeNumber(): void
    {
        $this->record->setTotalQueries(999);
        $this->assertSame(999, $this->record->getTotalQueries());
    }

    public function testSetRequestIdWithEmptyString(): void
    {
        $this->record->setRequestId('');
        $this->assertSame('', $this->record->getRequestId());
    }

    public function testSetUserIdentifierWithEmptyString(): void
    {
        $this->record->setUserIdentifier('');
        $this->assertSame('', $this->record->getUserIdentifier());
    }

    public function testSetUserIdWithEmptyString(): void
    {
        $this->record->setUserId('');
        $this->assertSame('', $this->record->getUserId());
    }

    public function testSetQueryTimeWithNull(): void
    {
        $this->record->setQueryTime(0.1);
        $this->assertSame(0.1, $this->record->getQueryTime());

        $this->record->setQueryTime(null);
        $this->assertNull($this->record->getQueryTime());
    }

    public function testSetTotalQueriesWithNull(): void
    {
        $this->record->setTotalQueries(10);
        $this->assertSame(10, $this->record->getTotalQueries());

        $this->record->setTotalQueries(null);
        $this->assertNull($this->record->getTotalQueries());
    }

    public function testSetMemoryUsageWithNull(): void
    {
        $this->record->setMemoryUsage(1024);
        $this->assertSame(1024, $this->record->getMemoryUsage());

        $this->record->setMemoryUsage(null);
        $this->assertNull($this->record->getMemoryUsage());
    }

    public function testSetRefererTruncatesWhenExceedsMaxLength(): void
    {
        $longUrl = str_repeat('a', 2100);
        $this->record->setReferer($longUrl);

        $result = $this->record->getReferer();
        $this->assertSame(2048, \strlen($result));
        $this->assertSame(substr($longUrl, 0, 2048), $result);
    }

    public function testSetUserIdentifierTruncatesWhenExceedsMaxLength(): void
    {
        $longIdentifier = str_repeat('x', 300);
        $this->record->setUserIdentifier($longIdentifier);

        $result = $this->record->getUserIdentifier();
        $this->assertSame(255, \strlen($result));
        $this->assertSame(substr($longIdentifier, 0, 255), $result);
    }

    public function testSetUserIdTruncatesWhenExceedsMaxLength(): void
    {
        $longUserId = str_repeat('u', 80);
        $this->record->setUserId($longUserId);

        $result = $this->record->getUserId();
        $this->assertSame(64, \strlen($result));
        $this->assertSame(substr($longUserId, 0, 64), $result);
    }

    public function testRouteParamsIsInitiallyNull(): void
    {
        $this->assertNull($this->record->getRouteParams());
    }

    public function testSetAndGetRouteParams(): void
    {
        $params = ['id' => 123, 'slug' => 'foo-bar'];
        $this->record->setRouteParams($params);
        $this->assertSame($params, $this->record->getRouteParams());

        $this->record->setRouteParams(null);
        $this->assertNull($this->record->getRouteParams());
    }

    public function testRoutePathIsInitiallyNull(): void
    {
        $this->assertNull($this->record->getRoutePath());
    }

    public function testSetAndGetRoutePath(): void
    {
        $this->record->setRoutePath('/user/123');
        $this->assertSame('/user/123', $this->record->getRoutePath());

        $this->record->setRoutePath(null);
        $this->assertNull($this->record->getRoutePath());
    }

    public function testSetRoutePathTruncatesWhenExceedsMaxLength(): void
    {
        $longPath = '/'.str_repeat('a', 2100);
        $this->record->setRoutePath($longPath);

        $result = $this->record->getRoutePath();
        $this->assertSame(2048, \strlen($result));
    }
}
