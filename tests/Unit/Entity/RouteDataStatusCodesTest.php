<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Entity;

use Nowo\PerformanceBundle\Entity\RouteData;
use PHPUnit\Framework\TestCase;

/**
 * Additional tests for RouteData status codes functionality.
 */
final class RouteDataStatusCodesTest extends TestCase
{
    public function testGetStatusCodeRatioWithEmptyStatusCodes(): void
    {
        $routeData = new RouteData();
        $routeData->setStatusCodes([]);
        
        $this->assertSame(0.0, $routeData->getStatusCodeRatio(200));
        $this->assertSame(0.0, $routeData->getStatusCodeRatio(404));
    }

    public function testGetStatusCodeRatioWithNullStatusCodes(): void
    {
        $routeData = new RouteData();
        $routeData->setStatusCodes(null);
        
        $this->assertSame(0.0, $routeData->getStatusCodeRatio(200));
    }

    public function testGetTotalResponsesWithNullStatusCodes(): void
    {
        $routeData = new RouteData();
        $routeData->setStatusCodes(null);
        
        $this->assertSame(0, $routeData->getTotalResponses());
    }

    public function testGetTotalResponsesWithEmptyStatusCodes(): void
    {
        $routeData = new RouteData();
        $routeData->setStatusCodes([]);
        
        $this->assertSame(0, $routeData->getTotalResponses());
    }

    public function testIncrementStatusCodeInitializesArray(): void
    {
        $routeData = new RouteData();
        $routeData->setStatusCodes(null);
        
        $routeData->incrementStatusCode(200);
        
        $this->assertSame(1, $routeData->getStatusCodeCount(200));
        $this->assertIsArray($routeData->getStatusCodes());
    }

    public function testGetStatusCodeRatioCalculatesCorrectly(): void
    {
        $routeData = new RouteData();
        
        // 10 requests: 7x 200, 2x 404, 1x 500
        for ($i = 0; $i < 7; $i++) {
            $routeData->incrementStatusCode(200);
        }
        for ($i = 0; $i < 2; $i++) {
            $routeData->incrementStatusCode(404);
        }
        $routeData->incrementStatusCode(500);
        
        $this->assertSame(70.0, $routeData->getStatusCodeRatio(200));
        $this->assertSame(20.0, $routeData->getStatusCodeRatio(404));
        $this->assertSame(10.0, $routeData->getStatusCodeRatio(500));
        $this->assertSame(10, $routeData->getTotalResponses());
    }

    public function testGetStatusCodeRatioWithSingleStatusCode(): void
    {
        $routeData = new RouteData();
        $routeData->incrementStatusCode(200);
        
        $this->assertSame(100.0, $routeData->getStatusCodeRatio(200));
        $this->assertSame(0.0, $routeData->getStatusCodeRatio(404));
    }

    public function testIncrementStatusCodeMultipleTimes(): void
    {
        $routeData = new RouteData();
        
        for ($i = 0; $i < 100; $i++) {
            $routeData->incrementStatusCode(200);
        }
        
        $this->assertSame(100, $routeData->getStatusCodeCount(200));
        $this->assertSame(100.0, $routeData->getStatusCodeRatio(200));
        $this->assertSame(100, $routeData->getTotalResponses());
    }

    public function testSetStatusCodesReplacesExisting(): void
    {
        $routeData = new RouteData();
        $routeData->incrementStatusCode(200);
        $routeData->incrementStatusCode(200);
        $routeData->incrementStatusCode(404);
        
        $this->assertSame(2, $routeData->getStatusCodeCount(200));
        $this->assertSame(1, $routeData->getStatusCodeCount(404));
        
        // Replace with new data
        $routeData->setStatusCodes([200 => 5, 500 => 2]);
        
        $this->assertSame(5, $routeData->getStatusCodeCount(200));
        $this->assertSame(0, $routeData->getStatusCodeCount(404));
        $this->assertSame(2, $routeData->getStatusCodeCount(500));
        $this->assertSame(7, $routeData->getTotalResponses());
    }

    public function testGetStatusCodeCountWithNonExistentCode(): void
    {
        $routeData = new RouteData();
        $routeData->incrementStatusCode(200);
        
        $this->assertSame(0, $routeData->getStatusCodeCount(404));
        $this->assertSame(0, $routeData->getStatusCodeCount(500));
        $this->assertSame(0, $routeData->getStatusCodeCount(999));
    }

    public function testGetStatusCodeRatioWithLargeNumbers(): void
    {
        $routeData = new RouteData();
        
        // 1000 requests: 950x 200, 30x 404, 20x 500
        for ($i = 0; $i < 950; $i++) {
            $routeData->incrementStatusCode(200);
        }
        for ($i = 0; $i < 30; $i++) {
            $routeData->incrementStatusCode(404);
        }
        for ($i = 0; $i < 20; $i++) {
            $routeData->incrementStatusCode(500);
        }
        
        $this->assertSame(95.0, $routeData->getStatusCodeRatio(200));
        $this->assertSame(3.0, $routeData->getStatusCodeRatio(404));
        $this->assertSame(2.0, $routeData->getStatusCodeRatio(500));
        $this->assertSame(1000, $routeData->getTotalResponses());
    }

    public function testIncrementStatusCodeUpdatesTimestamp(): void
    {
        $routeData = new RouteData();
        $initialUpdatedAt = $routeData->getUpdatedAt();
        
        usleep(1000); // Small delay
        
        $routeData->incrementStatusCode(200);
        
        $this->assertNotSame($initialUpdatedAt, $routeData->getUpdatedAt());
    }

    public function testSetStatusCodesUpdatesTimestamp(): void
    {
        $routeData = new RouteData();
        $initialUpdatedAt = $routeData->getUpdatedAt();
        
        usleep(1000); // Small delay
        
        $routeData->setStatusCodes([200 => 10]);
        
        $this->assertNotSame($initialUpdatedAt, $routeData->getUpdatedAt());
    }
}
