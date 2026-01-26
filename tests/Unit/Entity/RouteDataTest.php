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
}
