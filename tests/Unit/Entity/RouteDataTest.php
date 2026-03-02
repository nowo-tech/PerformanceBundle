<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Entity;

use DateTimeImmutable;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Entity\RouteDataRecord;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests for RouteData entity (identity and metadata only; metrics are in RouteDataRecord/aggregates).
 */
final class RouteDataTest extends TestCase
{
    public function testConstructor(): void
    {
        $routeData = new RouteData();

        $this->assertNotNull($routeData->getCreatedAt());
        $this->assertNotNull($routeData->getLastAccessedAt());
        $this->assertInstanceOf(DateTimeImmutable::class, $routeData->getCreatedAt());
        $this->assertInstanceOf(DateTimeImmutable::class, $routeData->getLastAccessedAt());
        $this->assertCount(0, $routeData->getAccessRecords());
        $this->assertTrue($routeData->getSaveAccessRecords());
    }

    public function testGettersAndSetters(): void
    {
        $routeData = new RouteData();

        // Test env
        $this->assertNull($routeData->getEnv());
        $routeData->setEnv('dev');
        $this->assertSame('dev', $routeData->getEnv());
        $routeData->setEnv(null);
        $this->assertNull($routeData->getEnv());

        // Test name
        $this->assertNull($routeData->getName());
        $routeData->setName('app_home');
        $this->assertSame('app_home', $routeData->getName());
        $routeData->setName(null);
        $this->assertNull($routeData->getName());
        $routeData->setName('');
        $this->assertSame('', $routeData->getName());

        // Test httpMethod
        $this->assertNull($routeData->getHttpMethod());
        $routeData->setHttpMethod('GET');
        $this->assertSame('GET', $routeData->getHttpMethod());
        $routeData->setHttpMethod('POST');
        $this->assertSame('POST', $routeData->getHttpMethod());
        $routeData->setHttpMethod('');
        $this->assertSame('', $routeData->getHttpMethod());
        $routeData->setHttpMethod(null);
        $this->assertNull($routeData->getHttpMethod());

        // Test params
        $this->assertNull($routeData->getParams());
        $params = ['id' => 123, 'slug' => 'test'];
        $routeData->setParams($params);
        $this->assertSame($params, $routeData->getParams());
        $routeData->setParams(null);
        $this->assertNull($routeData->getParams());
        $routeData->setParams([]);
        $this->assertSame([], $routeData->getParams());

        // Test createdAt
        $createdAt = new DateTimeImmutable('2025-01-01 12:00:00');
        $routeData->setCreatedAt($createdAt);
        $this->assertSame($createdAt, $routeData->getCreatedAt());
        $routeData->setCreatedAt(null);
        $this->assertNull($routeData->getCreatedAt());

        // Test lastAccessedAt
        $lastAccessedAt = new DateTimeImmutable('2025-01-01 14:00:00');
        $routeData->setLastAccessedAt($lastAccessedAt);
        $this->assertSame($lastAccessedAt, $routeData->getLastAccessedAt());
        $routeData->setLastAccessedAt(null);
        $this->assertNull($routeData->getLastAccessedAt());
    }

    public function testReviewGettersAndSetters(): void
    {
        $routeData = new RouteData();

        $this->assertFalse($routeData->isReviewed());
        $routeData->setReviewed(true);
        $this->assertTrue($routeData->isReviewed());
        $routeData->setReviewed(false);
        $this->assertFalse($routeData->isReviewed());
    }

    public function testReviewedAtGettersAndSetters(): void
    {
        $routeData = new RouteData();

        $this->assertNull($routeData->getReviewedAt());
        $reviewedAt = new DateTimeImmutable('2025-01-01 15:00:00');
        $routeData->setReviewedAt($reviewedAt);
        $this->assertSame($reviewedAt, $routeData->getReviewedAt());
        $routeData->setReviewedAt(null);
        $this->assertNull($routeData->getReviewedAt());
    }

    public function testQueriesImprovedGettersAndSetters(): void
    {
        $routeData = new RouteData();

        $this->assertNull($routeData->getQueriesImproved());
        $routeData->setQueriesImproved(true);
        $this->assertTrue($routeData->getQueriesImproved());
        $routeData->setQueriesImproved(false);
        $this->assertFalse($routeData->getQueriesImproved());
        $routeData->setQueriesImproved(null);
        $this->assertNull($routeData->getQueriesImproved());
    }

    public function testTimeImprovedGettersAndSetters(): void
    {
        $routeData = new RouteData();

        $this->assertNull($routeData->getTimeImproved());
        $routeData->setTimeImproved(true);
        $this->assertTrue($routeData->getTimeImproved());
        $routeData->setTimeImproved(false);
        $this->assertFalse($routeData->getTimeImproved());
        $routeData->setTimeImproved(null);
        $this->assertNull($routeData->getTimeImproved());
    }

    public function testReviewedByGettersAndSetters(): void
    {
        $routeData = new RouteData();

        $this->assertNull($routeData->getReviewedBy());
        $routeData->setReviewedBy('admin');
        $this->assertSame('admin', $routeData->getReviewedBy());
        $routeData->setReviewedBy(null);
        $this->assertNull($routeData->getReviewedBy());
    }

    public function testSetEnvWithStageEnvironment(): void
    {
        $routeData = new RouteData();
        $routeData->setEnv('stage');
        $this->assertSame('stage', $routeData->getEnv());
    }

    public function testSetReviewedByWithEmptyString(): void
    {
        $routeData = new RouteData();
        $routeData->setReviewedBy('');
        $this->assertSame('', $routeData->getReviewedBy());
    }

    public function testToStringWithAllData(): void
    {
        $routeData = new RouteData();
        $routeData->setHttpMethod('GET');
        $routeData->setName('app_home');
        $routeData->setEnv('dev');

        $result = (string) $routeData;
        $this->assertStringContainsString('GET', $result);
        $this->assertStringContainsString('app_home', $result);
        $this->assertStringContainsString('(dev)', $result);
    }

    public function testToStringWithMinimalData(): void
    {
        $routeData  = new RouteData();
        $reflection = new ReflectionClass($routeData);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($routeData, 1);

        $result = (string) $routeData;
        $this->assertStringContainsString('RouteData#1', $result);
    }

    public function testToStringWithPartialData(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home');

        $result = (string) $routeData;
        $this->assertStringContainsString('app_home', $result);
    }

    public function testConstructorInitializesTimestamps(): void
    {
        $routeData = new RouteData();

        $this->assertNotNull($routeData->getCreatedAt());
        $this->assertNotNull($routeData->getLastAccessedAt());
        $this->assertInstanceOf(DateTimeImmutable::class, $routeData->getCreatedAt());
        $this->assertInstanceOf(DateTimeImmutable::class, $routeData->getLastAccessedAt());
    }

    public function testIdGetter(): void
    {
        $routeData = new RouteData();

        $this->assertNull($routeData->getId());

        $reflection = new ReflectionClass($routeData);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($routeData, 123);

        $this->assertSame(123, $routeData->getId());
    }

    public function testMarkAsReviewed(): void
    {
        $routeData = new RouteData();

        $this->assertFalse($routeData->isReviewed());
        $this->assertNull($routeData->getReviewedAt());

        $routeData->markAsReviewed(true, false, 'admin');

        $this->assertTrue($routeData->isReviewed());
        $this->assertNotNull($routeData->getReviewedAt());
        $this->assertTrue($routeData->getQueriesImproved());
        $this->assertFalse($routeData->getTimeImproved());
        $this->assertSame('admin', $routeData->getReviewedBy());
    }

    public function testMarkAsReviewedWithNullValues(): void
    {
        $routeData = new RouteData();

        $routeData->markAsReviewed(null, null, null);

        $this->assertTrue($routeData->isReviewed());
        $this->assertNotNull($routeData->getReviewedAt());
        $this->assertNull($routeData->getQueriesImproved());
        $this->assertNull($routeData->getTimeImproved());
        $this->assertNull($routeData->getReviewedBy());
    }

    public function testMarkAsReviewedUpdatesReviewedAt(): void
    {
        $routeData = new RouteData();
        $routeData->setReviewedAt(new DateTimeImmutable('2025-01-01 00:00:00'));
        $initialReviewedAt = $routeData->getReviewedAt();

        usleep(2000);

        $routeData->markAsReviewed();

        $this->assertNotSame($initialReviewedAt, $routeData->getReviewedAt());
    }

    public function testSaveAccessRecordsGettersAndSetters(): void
    {
        $routeData = new RouteData();

        $this->assertTrue($routeData->getSaveAccessRecords());
        $routeData->setSaveAccessRecords(false);
        $this->assertFalse($routeData->getSaveAccessRecords());
        $routeData->setSaveAccessRecords(true);
        $this->assertTrue($routeData->getSaveAccessRecords());
    }

    public function testGetAccessRecordsReturnsCollection(): void
    {
        $routeData = new RouteData();

        $this->assertCount(0, $routeData->getAccessRecords());
        $this->assertSame($routeData->getAccessRecords(), $routeData->getAccessRecords());
    }

    public function testAddAccessRecord(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home')->setEnv('dev');
        $record = new RouteDataRecord();

        $result = $routeData->addAccessRecord($record);

        $this->assertSame($routeData, $result);
        $this->assertCount(1, $routeData->getAccessRecords());
        $this->assertTrue($routeData->getAccessRecords()->contains($record));
        $this->assertSame($routeData, $record->getRouteData());
    }

    public function testAddAccessRecordIdempotentWhenAlreadyContained(): void
    {
        $routeData = new RouteData();
        $record    = new RouteDataRecord();
        $routeData->addAccessRecord($record);
        $routeData->addAccessRecord($record);

        $this->assertCount(1, $routeData->getAccessRecords());
    }

    public function testRemoveAccessRecord(): void
    {
        $routeData = new RouteData();
        $record    = new RouteDataRecord();
        $routeData->addAccessRecord($record);

        $result = $routeData->removeAccessRecord($record);

        $this->assertSame($routeData, $result);
        $this->assertCount(0, $routeData->getAccessRecords());
        $this->assertNull($record->getRouteData());
    }

    public function testRemoveAccessRecordNotContainedNoOp(): void
    {
        $routeData = new RouteData();
        $record    = new RouteDataRecord();

        $result = $routeData->removeAccessRecord($record);

        $this->assertSame($routeData, $result);
        $this->assertCount(0, $routeData->getAccessRecords());
    }

    public function testAddAccessRecordWithMultipleRecords(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home')->setEnv('dev');
        $record1 = new RouteDataRecord();
        $record2 = new RouteDataRecord();

        $routeData->addAccessRecord($record1);
        $routeData->addAccessRecord($record2);

        $this->assertCount(2, $routeData->getAccessRecords());
        $this->assertTrue($routeData->getAccessRecords()->contains($record1));
        $this->assertTrue($routeData->getAccessRecords()->contains($record2));
    }

    public function testSetParamsWithSingleKey(): void
    {
        $routeData = new RouteData();
        $routeData->setParams(['id' => 1]);

        $this->assertSame(['id' => 1], $routeData->getParams());
    }
}
