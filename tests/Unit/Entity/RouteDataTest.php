<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Entity;

use Nowo\PerformanceBundle\Entity\RouteData;
use PHPUnit\Framework\TestCase;

final class RouteDataTest extends TestCase
{
    public function testConstructor(): void
    {
        $routeData = new RouteData();
        
        $this->assertNotNull($routeData->getCreatedAt());
        $this->assertNotNull($routeData->getUpdatedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $routeData->getCreatedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $routeData->getUpdatedAt());
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
        
        // Test httpMethod
        $this->assertNull($routeData->getHttpMethod());
        $routeData->setHttpMethod('GET');
        $this->assertSame('GET', $routeData->getHttpMethod());
        $routeData->setHttpMethod('POST');
        $this->assertSame('POST', $routeData->getHttpMethod());
        $routeData->setHttpMethod(null);
        $this->assertNull($routeData->getHttpMethod());
        
        // Test totalQueries
        $this->assertNull($routeData->getTotalQueries());
        $routeData->setTotalQueries(10);
        $this->assertSame(10, $routeData->getTotalQueries());
        $routeData->setTotalQueries(null);
        $this->assertNull($routeData->getTotalQueries());
        
        // Test params
        $this->assertNull($routeData->getParams());
        $params = ['id' => 123, 'slug' => 'test'];
        $routeData->setParams($params);
        $this->assertSame($params, $routeData->getParams());
        $routeData->setParams(null);
        $this->assertNull($routeData->getParams());
        
        // Test requestTime
        $this->assertNull($routeData->getRequestTime());
        $routeData->setRequestTime(0.5);
        $this->assertSame(0.5, $routeData->getRequestTime());
        $this->assertNotNull($routeData->getUpdatedAt());
        $routeData->setRequestTime(null);
        $this->assertNull($routeData->getRequestTime());
        
        // Test queryTime
        $this->assertNull($routeData->getQueryTime());
        $routeData->setQueryTime(0.2);
        $this->assertSame(0.2, $routeData->getQueryTime());
        $this->assertNotNull($routeData->getUpdatedAt());
        $routeData->setQueryTime(null);
        $this->assertNull($routeData->getQueryTime());
        
        // Test createdAt
        $createdAt = new \DateTimeImmutable('2025-01-01 12:00:00');
        $routeData->setCreatedAt($createdAt);
        $this->assertSame($createdAt, $routeData->getCreatedAt());
        $routeData->setCreatedAt(null);
        $this->assertNull($routeData->getCreatedAt());
        
        // Test updatedAt
        $updatedAt = new \DateTimeImmutable('2025-01-01 13:00:00');
        $routeData->setUpdatedAt($updatedAt);
        $this->assertSame($updatedAt, $routeData->getUpdatedAt());
        $routeData->setUpdatedAt(null);
        $this->assertNull($routeData->getUpdatedAt());
    }

    public function testShouldUpdateWithWorseRequestTime(): void
    {
        $routeData = new RouteData();
        $routeData->setRequestTime(0.5);
        $routeData->setTotalQueries(10);

        // Worse request time (higher)
        $this->assertTrue($routeData->shouldUpdate(0.8, 10));
    }

    public function testShouldUpdateWithMoreQueries(): void
    {
        $routeData = new RouteData();
        $routeData->setRequestTime(0.5);
        $routeData->setTotalQueries(10);

        // More queries
        $this->assertTrue($routeData->shouldUpdate(0.5, 15));
    }

    public function testShouldNotUpdateWithBetterMetrics(): void
    {
        $routeData = new RouteData();
        $routeData->setRequestTime(0.5);
        $routeData->setTotalQueries(10);

        // Better metrics (lower time, fewer queries)
        $this->assertFalse($routeData->shouldUpdate(0.3, 5));
    }

    public function testShouldUpdateWhenNoExistingData(): void
    {
        $routeData = new RouteData();

        // No existing data, should update
        $this->assertTrue($routeData->shouldUpdate(0.5, 10));
    }

    public function testShouldUpdateWhenNoExistingRequestTime(): void
    {
        $routeData = new RouteData();
        $routeData->setTotalQueries(10);
        $routeData->setRequestTime(null);

        // New request time provided
        $this->assertTrue($routeData->shouldUpdate(0.5, 10));
    }

    public function testShouldUpdateWhenNoExistingTotalQueries(): void
    {
        $routeData = new RouteData();
        $routeData->setRequestTime(0.5);
        $routeData->setTotalQueries(null);

        // New query count provided
        $this->assertTrue($routeData->shouldUpdate(0.5, 10));
    }

    public function testShouldNotUpdateWhenMetricsAreBetter(): void
    {
        $routeData = new RouteData();
        $routeData->setRequestTime(0.5);
        $routeData->setTotalQueries(10);

        // Both metrics are better (lower)
        $this->assertFalse($routeData->shouldUpdate(0.3, 5));
    }

    public function testShouldNotUpdateWhenMetricsAreEqual(): void
    {
        $routeData = new RouteData();
        $routeData->setRequestTime(0.5);
        $routeData->setTotalQueries(10);

        // Metrics are equal
        $this->assertFalse($routeData->shouldUpdate(0.5, 10));
    }

    public function testShouldNotUpdateWhenNewMetricsAreNull(): void
    {
        $routeData = new RouteData();
        $routeData->setRequestTime(0.5);
        $routeData->setTotalQueries(10);

        // New metrics are null
        $this->assertFalse($routeData->shouldUpdate(null, null));
    }

    public function testMemoryUsageGettersAndSetters(): void
    {
        $routeData = new RouteData();
        
        $this->assertNull($routeData->getMemoryUsage());
        $routeData->setMemoryUsage(1048576); // 1MB
        $this->assertSame(1048576, $routeData->getMemoryUsage());
        $this->assertNotNull($routeData->getUpdatedAt());
        $routeData->setMemoryUsage(null);
        $this->assertNull($routeData->getMemoryUsage());
    }

    public function testAccessCountGettersAndSetters(): void
    {
        $routeData = new RouteData();
        
        $this->assertSame(1, $routeData->getAccessCount()); // Default is 1
        $routeData->setAccessCount(5);
        $this->assertSame(5, $routeData->getAccessCount());
        $this->assertNotNull($routeData->getUpdatedAt());
    }

    public function testIncrementAccessCount(): void
    {
        $routeData = new RouteData();
        $initialCount = $routeData->getAccessCount();
        $initialUpdatedAt = $routeData->getUpdatedAt();
        
        // Wait a tiny bit to ensure timestamp changes
        usleep(1000);
        
        $routeData->incrementAccessCount();
        
        $this->assertSame($initialCount + 1, $routeData->getAccessCount());
        $this->assertNotNull($routeData->getLastAccessedAt());
        $this->assertNotSame($initialUpdatedAt, $routeData->getUpdatedAt());
    }

    public function testLastAccessedAtGettersAndSetters(): void
    {
        $routeData = new RouteData();
        
        $this->assertNotNull($routeData->getLastAccessedAt()); // Set in constructor
        $lastAccessedAt = new \DateTimeImmutable('2025-01-01 14:00:00');
        $routeData->setLastAccessedAt($lastAccessedAt);
        $this->assertSame($lastAccessedAt, $routeData->getLastAccessedAt());
        $this->assertNotNull($routeData->getUpdatedAt());
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
        $reviewedAt = new \DateTimeImmutable('2025-01-01 15:00:00');
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

    public function testToStringWithAllData(): void
    {
        $routeData = new RouteData();
        $routeData->setHttpMethod('GET');
        $routeData->setName('app_home');
        $routeData->setEnv('dev');
        $routeData->setRequestTime(0.5);
        $routeData->setTotalQueries(10);
        
        $result = (string) $routeData;
        $this->assertStringContainsString('GET', $result);
        $this->assertStringContainsString('app_home', $result);
        $this->assertStringContainsString('(dev)', $result);
        $this->assertStringContainsString('500.00ms', $result);
        $this->assertStringContainsString('10q', $result);
    }

    public function testToStringWithMinimalData(): void
    {
        $routeData = new RouteData();
        $routeData->setId(1);
        
        $result = (string) $routeData;
        $this->assertStringContainsString('RouteData#1', $result);
    }

    public function testToStringWithPartialData(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setRequestTime(0.25);
        
        $result = (string) $routeData;
        $this->assertStringContainsString('app_home', $result);
        $this->assertStringContainsString('250.00ms', $result);
    }

    public function testConstructorInitializesTimestamps(): void
    {
        $routeData = new RouteData();
        
        $this->assertNotNull($routeData->getCreatedAt());
        $this->assertNotNull($routeData->getUpdatedAt());
        $this->assertNotNull($routeData->getLastAccessedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $routeData->getCreatedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $routeData->getUpdatedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $routeData->getLastAccessedAt());
    }

    public function testIdGetter(): void
    {
        $routeData = new RouteData();
        
        // ID is null until persisted
        $this->assertNull($routeData->getId());
        
        // Use reflection to set ID for testing
        $reflection = new \ReflectionClass($routeData);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($routeData, 123);
        
        $this->assertSame(123, $routeData->getId());
    }

    public function testStatusCodesGettersAndSetters(): void
    {
        $routeData = new RouteData();
        
        $this->assertIsArray($routeData->getStatusCodes());
        $this->assertEmpty($routeData->getStatusCodes());
        
        $statusCodes = [200 => 100, 404 => 5, 500 => 2];
        $routeData->setStatusCodes($statusCodes);
        $this->assertSame($statusCodes, $routeData->getStatusCodes());
        $this->assertNotNull($routeData->getUpdatedAt());
        
        $routeData->setStatusCodes(null);
        $this->assertNull($routeData->getStatusCodes());
    }

    public function testIncrementStatusCode(): void
    {
        $routeData = new RouteData();
        
        // First increment creates the entry
        $routeData->incrementStatusCode(200);
        $this->assertSame(1, $routeData->getStatusCodeCount(200));
        $this->assertNotNull($routeData->getUpdatedAt());
        
        // Second increment increases the count
        $routeData->incrementStatusCode(200);
        $this->assertSame(2, $routeData->getStatusCodeCount(200));
        
        // Different status code
        $routeData->incrementStatusCode(404);
        $this->assertSame(1, $routeData->getStatusCodeCount(404));
        $this->assertSame(2, $routeData->getStatusCodeCount(200));
    }

    public function testGetStatusCodeCount(): void
    {
        $routeData = new RouteData();
        
        // Returns 0 for non-existent code
        $this->assertSame(0, $routeData->getStatusCodeCount(200));
        
        // After incrementing
        $routeData->incrementStatusCode(200);
        $this->assertSame(1, $routeData->getStatusCodeCount(200));
        
        $routeData->incrementStatusCode(200);
        $this->assertSame(2, $routeData->getStatusCodeCount(200));
    }

    public function testGetStatusCodeRatio(): void
    {
        $routeData = new RouteData();
        
        // Returns 0.0 when no status codes
        $this->assertSame(0.0, $routeData->getStatusCodeRatio(200));
        
        // Set up some status codes
        $routeData->incrementStatusCode(200);
        $routeData->incrementStatusCode(200);
        $routeData->incrementStatusCode(200);
        $routeData->incrementStatusCode(404);
        
        // 200: 3 out of 4 = 75%
        $this->assertSame(75.0, $routeData->getStatusCodeRatio(200));
        
        // 404: 1 out of 4 = 25%
        $this->assertSame(25.0, $routeData->getStatusCodeRatio(404));
        
        // 500: 0 out of 4 = 0%
        $this->assertSame(0.0, $routeData->getStatusCodeRatio(500));
    }

    public function testGetTotalResponses(): void
    {
        $routeData = new RouteData();
        
        // Returns 0 when no status codes
        $this->assertSame(0, $routeData->getTotalResponses());
        
        // After incrementing
        $routeData->incrementStatusCode(200);
        $routeData->incrementStatusCode(200);
        $routeData->incrementStatusCode(404);
        $routeData->incrementStatusCode(500);
        
        $this->assertSame(4, $routeData->getTotalResponses());
    }

    public function testStatusCodesInitializedInConstructor(): void
    {
        $routeData = new RouteData();
        
        $this->assertIsArray($routeData->getStatusCodes());
        $this->assertEmpty($routeData->getStatusCodes());
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
        $this->assertNotNull($routeData->getUpdatedAt());
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

    public function testMarkAsReviewedUpdatesTimestamp(): void
    {
        $routeData = new RouteData();
        $initialUpdatedAt = $routeData->getUpdatedAt();
        
        usleep(1000); // Small delay to ensure timestamp changes
        
        $routeData->markAsReviewed();
        
        $this->assertNotSame($initialUpdatedAt, $routeData->getUpdatedAt());
    }
}
