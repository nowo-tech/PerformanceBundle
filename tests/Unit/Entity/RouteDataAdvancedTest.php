<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Entity;

use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Entity\RouteDataRecord;
use PHPUnit\Framework\TestCase;

/**
 * Advanced tests for RouteData entity edge cases.
 */
final class RouteDataAdvancedTest extends TestCase
{
    public function testShouldUpdateWithNullExistingValues(): void
    {
        $routeData = new RouteData();
        $routeData->setRequestTime(null);
        $routeData->setTotalQueries(null);

        // Should update when we have new data but no existing data
        $this->assertTrue($routeData->shouldUpdate(0.5, null));
        $this->assertTrue($routeData->shouldUpdate(null, 10));
        $this->assertTrue($routeData->shouldUpdate(0.5, 10));
    }

    public function testShouldUpdateWithNullNewValues(): void
    {
        $routeData = new RouteData();
        $routeData->setRequestTime(0.5);
        $routeData->setTotalQueries(10);

        // Should not update when new values are null
        $this->assertFalse($routeData->shouldUpdate(null, null));
        $this->assertFalse($routeData->shouldUpdate(null, 10));
        $this->assertFalse($routeData->shouldUpdate(0.5, null));
    }

    public function testShouldUpdateWithWorseRequestTime(): void
    {
        $routeData = new RouteData();
        $routeData->setRequestTime(0.5);

        // Should update when new request time is worse (higher)
        $this->assertTrue($routeData->shouldUpdate(0.6, null));
        $this->assertTrue($routeData->shouldUpdate(1.0, null));
    }

    public function testShouldUpdateWithBetterRequestTime(): void
    {
        $routeData = new RouteData();
        $routeData->setRequestTime(0.5);

        // Should not update when new request time is better (lower)
        $this->assertFalse($routeData->shouldUpdate(0.4, null));
        $this->assertFalse($routeData->shouldUpdate(0.1, null));
    }

    public function testShouldUpdateWithWorseQueryCount(): void
    {
        $routeData = new RouteData();
        $routeData->setTotalQueries(10);

        // Should update when new query count is worse (higher)
        $this->assertTrue($routeData->shouldUpdate(null, 11));
        $this->assertTrue($routeData->shouldUpdate(null, 20));
    }

    public function testShouldUpdateWithBetterQueryCount(): void
    {
        $routeData = new RouteData();
        $routeData->setTotalQueries(10);

        // Should not update when new query count is better (lower)
        $this->assertFalse($routeData->shouldUpdate(null, 9));
        $this->assertFalse($routeData->shouldUpdate(null, 5));
    }

    public function testShouldUpdateWithEqualValues(): void
    {
        $routeData = new RouteData();
        $routeData->setRequestTime(0.5);
        $routeData->setTotalQueries(10);

        // Should not update when values are equal
        $this->assertFalse($routeData->shouldUpdate(0.5, 10));
    }

    public function testShouldUpdateWithMixedWorseValues(): void
    {
        $routeData = new RouteData();
        $routeData->setRequestTime(0.5);
        $routeData->setTotalQueries(10);

        // Should update if either value is worse
        $this->assertTrue($routeData->shouldUpdate(0.6, 10));
        $this->assertTrue($routeData->shouldUpdate(0.5, 11));
        $this->assertTrue($routeData->shouldUpdate(0.6, 11));
    }

    public function testGetStatusCodeRatioWithEmptyStatusCodes(): void
    {
        $routeData = new RouteData();
        $routeData->setStatusCodes([]);

        $this->assertSame(0.0, $routeData->getStatusCodeRatio(200));
    }

    public function testGetStatusCodeRatioWithNullStatusCodes(): void
    {
        $routeData = new RouteData();
        $routeData->setStatusCodes(null);

        $this->assertSame(0.0, $routeData->getStatusCodeRatio(200));
    }

    public function testGetStatusCodeRatioWithSingleStatusCode(): void
    {
        $routeData = new RouteData();
        $routeData->incrementStatusCode(200);

        $this->assertSame(100.0, $routeData->getStatusCodeRatio(200));
        $this->assertSame(0.0, $routeData->getStatusCodeRatio(404));
    }

    public function testGetStatusCodeRatioWithMultipleStatusCodes(): void
    {
        $routeData = new RouteData();
        $routeData->incrementStatusCode(200);
        $routeData->incrementStatusCode(200);
        $routeData->incrementStatusCode(404);
        $routeData->incrementStatusCode(500);

        // 200: 2 out of 4 = 50%
        $this->assertSame(50.0, $routeData->getStatusCodeRatio(200));
        // 404: 1 out of 4 = 25%
        $this->assertSame(25.0, $routeData->getStatusCodeRatio(404));
        // 500: 1 out of 4 = 25%
        $this->assertSame(25.0, $routeData->getStatusCodeRatio(500));
    }

    public function testGetTotalResponsesWithEmptyStatusCodes(): void
    {
        $routeData = new RouteData();
        $routeData->setStatusCodes([]);

        $this->assertSame(0, $routeData->getTotalResponses());
    }

    public function testGetTotalResponsesWithNullStatusCodes(): void
    {
        $routeData = new RouteData();
        $routeData->setStatusCodes(null);

        $this->assertSame(0, $routeData->getTotalResponses());
    }

    public function testGetTotalResponsesWithMultipleStatusCodes(): void
    {
        $routeData = new RouteData();
        $routeData->incrementStatusCode(200);
        $routeData->incrementStatusCode(200);
        $routeData->incrementStatusCode(404);
        $routeData->incrementStatusCode(500);
        $routeData->incrementStatusCode(500);

        $this->assertSame(5, $routeData->getTotalResponses());
    }

    public function testIncrementStatusCodeWithNullStatusCodes(): void
    {
        $routeData = new RouteData();
        $routeData->setStatusCodes(null);

        $routeData->incrementStatusCode(200);

        $this->assertSame(1, $routeData->getStatusCodeCount(200));
        $this->assertNotNull($routeData->getUpdatedAt());
    }

    public function testIncrementStatusCodeMultipleTimes(): void
    {
        $routeData = new RouteData();

        for ($i = 0; $i < 100; ++$i) {
            $routeData->incrementStatusCode(200);
        }

        $this->assertSame(100, $routeData->getStatusCodeCount(200));
        $this->assertSame(100, $routeData->getTotalResponses());
    }

    public function testIncrementStatusCodeWithDifferentCodes(): void
    {
        $routeData = new RouteData();

        $routeData->incrementStatusCode(200);
        $routeData->incrementStatusCode(201);
        $routeData->incrementStatusCode(404);
        $routeData->incrementStatusCode(500);
        $routeData->incrementStatusCode(503);

        $this->assertSame(1, $routeData->getStatusCodeCount(200));
        $this->assertSame(1, $routeData->getStatusCodeCount(201));
        $this->assertSame(1, $routeData->getStatusCodeCount(404));
        $this->assertSame(1, $routeData->getStatusCodeCount(500));
        $this->assertSame(1, $routeData->getStatusCodeCount(503));
        $this->assertSame(5, $routeData->getTotalResponses());
    }

    public function testMarkAsReviewedWithAllParameters(): void
    {
        $routeData = new RouteData();

        $routeData->markAsReviewed(true, false, 'admin');

        $this->assertTrue($routeData->isReviewed());
        $this->assertNotNull($routeData->getReviewedAt());
        $this->assertTrue($routeData->getQueriesImproved());
        $this->assertFalse($routeData->getTimeImproved());
        $this->assertSame('admin', $routeData->getReviewedBy());
        $this->assertNotNull($routeData->getUpdatedAt());
    }

    public function testMarkAsReviewedWithNullParameters(): void
    {
        $routeData = new RouteData();

        $routeData->markAsReviewed(null, null, null);

        $this->assertTrue($routeData->isReviewed());
        $this->assertNotNull($routeData->getReviewedAt());
        $this->assertNull($routeData->getQueriesImproved());
        $this->assertNull($routeData->getTimeImproved());
        $this->assertNull($routeData->getReviewedBy());
    }

    public function testMarkAsReviewedWithPartialParameters(): void
    {
        $routeData = new RouteData();

        $routeData->markAsReviewed(true, null, 'user1');

        $this->assertTrue($routeData->isReviewed());
        $this->assertTrue($routeData->getQueriesImproved());
        $this->assertNull($routeData->getTimeImproved());
        $this->assertSame('user1', $routeData->getReviewedBy());
    }

    public function testIncrementAccessCountUpdatesTimestamps(): void
    {
        $routeData = new RouteData();
        $originalUpdatedAt = $routeData->getUpdatedAt();
        $originalLastAccessedAt = $routeData->getLastAccessedAt();

        // Wait a bit to ensure different timestamps
        usleep(1000);
        $routeData->incrementAccessCount();

        $this->assertSame(2, $routeData->getAccessCount());
        $this->assertNotSame($originalUpdatedAt, $routeData->getUpdatedAt());
        $this->assertNotSame($originalLastAccessedAt, $routeData->getLastAccessedAt());
    }

    public function testIncrementAccessCountMultipleTimes(): void
    {
        $routeData = new RouteData();

        for ($i = 0; $i < 10; ++$i) {
            $routeData->incrementAccessCount();
        }

        $this->assertSame(11, $routeData->getAccessCount()); // 1 initial + 10 increments
    }

    public function testAddAccessRecord(): void
    {
        $routeData = new RouteData();
        $record = new RouteDataRecord();

        $routeData->addAccessRecord($record);

        $this->assertTrue($routeData->getAccessRecords()->contains($record));
        $this->assertSame($routeData, $record->getRouteData());
    }

    public function testAddAccessRecordTwice(): void
    {
        $routeData = new RouteData();
        $record = new RouteDataRecord();

        $routeData->addAccessRecord($record);
        $routeData->addAccessRecord($record);

        // Should only be added once
        $this->assertCount(1, $routeData->getAccessRecords());
    }

    public function testRemoveAccessRecord(): void
    {
        $routeData = new RouteData();
        $record = new RouteDataRecord();
        $record->setRouteData($routeData);

        $routeData->addAccessRecord($record);
        $routeData->removeAccessRecord($record);

        $this->assertFalse($routeData->getAccessRecords()->contains($record));
        $this->assertNull($record->getRouteData());
    }

    public function testRemoveAccessRecordNotInCollection(): void
    {
        $routeData = new RouteData();
        $record = new RouteDataRecord();

        // Should not throw error
        $routeData->removeAccessRecord($record);

        $this->assertCount(0, $routeData->getAccessRecords());
    }

    public function testToStringWithAllFields(): void
    {
        $routeData = new RouteData();
        $routeData->setHttpMethod('POST');
        $routeData->setName('app_home');
        $routeData->setEnv('prod');
        $routeData->setRequestTime(0.5);
        $routeData->setTotalQueries(10);

        $result = (string) $routeData;

        $this->assertStringContainsString('POST', $result);
        $this->assertStringContainsString('app_home', $result);
        $this->assertStringContainsString('(prod)', $result);
        $this->assertStringContainsString('500.00ms', $result);
        $this->assertStringContainsString('10q', $result);
    }

    public function testToStringWithNoFields(): void
    {
        $routeData = new RouteData();

        $result = (string) $routeData;

        $this->assertStringContainsString('RouteData#new', $result);
    }

    public function testToStringWithId(): void
    {
        $routeData = new RouteData();
        
        // Use reflection to set ID
        $reflection = new \ReflectionClass($routeData);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($routeData, 123);

        $result = (string) $routeData;

        $this->assertStringContainsString('RouteData#123', $result);
    }

    public function testSetRequestTimeUpdatesUpdatedAt(): void
    {
        $routeData = new RouteData();
        $originalUpdatedAt = $routeData->getUpdatedAt();

        usleep(1000);
        $routeData->setRequestTime(0.5);

        $this->assertNotSame($originalUpdatedAt, $routeData->getUpdatedAt());
    }

    public function testSetQueryTimeUpdatesUpdatedAt(): void
    {
        $routeData = new RouteData();
        $originalUpdatedAt = $routeData->getUpdatedAt();

        usleep(1000);
        $routeData->setQueryTime(0.2);

        $this->assertNotSame($originalUpdatedAt, $routeData->getUpdatedAt());
    }

    public function testSetMemoryUsageUpdatesUpdatedAt(): void
    {
        $routeData = new RouteData();
        $originalUpdatedAt = $routeData->getUpdatedAt();

        usleep(1000);
        $routeData->setMemoryUsage(1024);

        $this->assertNotSame($originalUpdatedAt, $routeData->getUpdatedAt());
    }

    public function testSetAccessCountUpdatesUpdatedAt(): void
    {
        $routeData = new RouteData();
        $originalUpdatedAt = $routeData->getUpdatedAt();

        usleep(1000);
        $routeData->setAccessCount(5);

        $this->assertNotSame($originalUpdatedAt, $routeData->getUpdatedAt());
    }

    public function testSetLastAccessedAtUpdatesUpdatedAt(): void
    {
        $routeData = new RouteData();
        $originalUpdatedAt = $routeData->getUpdatedAt();

        usleep(1000);
        $routeData->setLastAccessedAt(new \DateTimeImmutable());

        $this->assertNotSame($originalUpdatedAt, $routeData->getUpdatedAt());
    }

    public function testSetStatusCodesUpdatesUpdatedAt(): void
    {
        $routeData = new RouteData();
        $originalUpdatedAt = $routeData->getUpdatedAt();

        usleep(1000);
        $routeData->setStatusCodes([200 => 10]);

        $this->assertNotSame($originalUpdatedAt, $routeData->getUpdatedAt());
    }
}
