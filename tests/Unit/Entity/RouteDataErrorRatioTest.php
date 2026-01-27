<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Entity;

use Nowo\PerformanceBundle\Entity\RouteData;
use PHPUnit\Framework\TestCase;

/**
 * Tests for calculating error ratios (4xx and 5xx status codes).
 */
final class RouteDataErrorRatioTest extends TestCase
{
    public function testCalculateErrorRatioWithNoErrors(): void
    {
        $routeData = new RouteData();
        
        // Only 200 status codes
        for ($i = 0; $i < 10; $i++) {
            $routeData->incrementStatusCode(200);
        }
        
        $errorCodes = [404, 500];
        $totalErrorRatio = 0.0;
        foreach ($errorCodes as $code) {
            $totalErrorRatio += $routeData->getStatusCodeRatio($code);
        }
        
        $this->assertSame(0.0, $totalErrorRatio);
    }

    public function testCalculateErrorRatioWithOnly4xxErrors(): void
    {
        $routeData = new RouteData();
        
        // 10 requests: 7x 200, 3x 404
        for ($i = 0; $i < 7; $i++) {
            $routeData->incrementStatusCode(200);
        }
        for ($i = 0; $i < 3; $i++) {
            $routeData->incrementStatusCode(404);
        }
        
        $errorCodes = [404, 500];
        $totalErrorRatio = 0.0;
        foreach ($errorCodes as $code) {
            $totalErrorRatio += $routeData->getStatusCodeRatio($code);
        }
        
        $this->assertSame(30.0, $totalErrorRatio);
    }

    public function testCalculateErrorRatioWithOnly5xxErrors(): void
    {
        $routeData = new RouteData();
        
        // 10 requests: 8x 200, 2x 500
        for ($i = 0; $i < 8; $i++) {
            $routeData->incrementStatusCode(200);
        }
        for ($i = 0; $i < 2; $i++) {
            $routeData->incrementStatusCode(500);
        }
        
        $errorCodes = [404, 500];
        $totalErrorRatio = 0.0;
        foreach ($errorCodes as $code) {
            $totalErrorRatio += $routeData->getStatusCodeRatio($code);
        }
        
        $this->assertSame(20.0, $totalErrorRatio);
    }

    public function testCalculateErrorRatioWithBoth4xxAnd5xxErrors(): void
    {
        $routeData = new RouteData();
        
        // 20 requests: 14x 200, 3x 404, 3x 500
        for ($i = 0; $i < 14; $i++) {
            $routeData->incrementStatusCode(200);
        }
        for ($i = 0; $i < 3; $i++) {
            $routeData->incrementStatusCode(404);
        }
        for ($i = 0; $i < 3; $i++) {
            $routeData->incrementStatusCode(500);
        }
        
        $errorCodes = [404, 500];
        $totalErrorRatio = 0.0;
        foreach ($errorCodes as $code) {
            $totalErrorRatio += $routeData->getStatusCodeRatio($code);
        }
        
        $this->assertSame(30.0, $totalErrorRatio); // 15% + 15% = 30%
    }

    public function testCalculateErrorRatioAbove10PercentThreshold(): void
    {
        $routeData = new RouteData();
        
        // 100 requests: 85x 200, 10x 404, 5x 500 (15% error rate)
        for ($i = 0; $i < 85; $i++) {
            $routeData->incrementStatusCode(200);
        }
        for ($i = 0; $i < 10; $i++) {
            $routeData->incrementStatusCode(404);
        }
        for ($i = 0; $i < 5; $i++) {
            $routeData->incrementStatusCode(500);
        }
        
        $errorCodes = [404, 500];
        $totalErrorRatio = 0.0;
        foreach ($errorCodes as $code) {
            $totalErrorRatio += $routeData->getStatusCodeRatio($code);
        }
        
        $this->assertGreaterThan(10.0, $totalErrorRatio);
        $this->assertSame(15.0, $totalErrorRatio);
    }

    public function testCalculateErrorRatioExactlyAt10PercentThreshold(): void
    {
        $routeData = new RouteData();
        
        // 100 requests: 90x 200, 5x 404, 5x 500 (10% error rate)
        for ($i = 0; $i < 90; $i++) {
            $routeData->incrementStatusCode(200);
        }
        for ($i = 0; $i < 5; $i++) {
            $routeData->incrementStatusCode(404);
        }
        for ($i = 0; $i < 5; $i++) {
            $routeData->incrementStatusCode(500);
        }
        
        $errorCodes = [404, 500];
        $totalErrorRatio = 0.0;
        foreach ($errorCodes as $code) {
            $totalErrorRatio += $routeData->getStatusCodeRatio($code);
        }
        
        $this->assertSame(10.0, $totalErrorRatio);
    }

    public function testCalculateErrorRatioWithMultiple4xxCodes(): void
    {
        $routeData = new RouteData();
        
        // 20 requests: 15x 200, 2x 400, 2x 401, 1x 404
        for ($i = 0; $i < 15; $i++) {
            $routeData->incrementStatusCode(200);
        }
        for ($i = 0; $i < 2; $i++) {
            $routeData->incrementStatusCode(400);
        }
        for ($i = 0; $i < 2; $i++) {
            $routeData->incrementStatusCode(401);
        }
        $routeData->incrementStatusCode(404);
        
        // Calculate error ratio for all 4xx codes
        $errorCodes = [400, 401, 403, 404];
        $totalErrorRatio = 0.0;
        foreach ($errorCodes as $code) {
            $totalErrorRatio += $routeData->getStatusCodeRatio($code);
        }
        
        $this->assertSame(25.0, $totalErrorRatio); // 10% + 10% + 0% + 5% = 25%
    }

    public function testCalculateErrorRatioWithMultiple5xxCodes(): void
    {
        $routeData = new RouteData();
        
        // 20 requests: 16x 200, 2x 500, 2x 503
        for ($i = 0; $i < 16; $i++) {
            $routeData->incrementStatusCode(200);
        }
        for ($i = 0; $i < 2; $i++) {
            $routeData->incrementStatusCode(500);
        }
        for ($i = 0; $i < 2; $i++) {
            $routeData->incrementStatusCode(503);
        }
        
        // Calculate error ratio for all 5xx codes
        $errorCodes = [500, 503];
        $totalErrorRatio = 0.0;
        foreach ($errorCodes as $code) {
            $totalErrorRatio += $routeData->getStatusCodeRatio($code);
        }
        
        $this->assertSame(20.0, $totalErrorRatio); // 10% + 10% = 20%
    }

    public function testCalculateErrorRatioWithMixedErrorCodes(): void
    {
        $routeData = new RouteData();
        
        // 50 requests: 40x 200, 5x 400, 2x 404, 2x 500, 1x 503
        for ($i = 0; $i < 40; $i++) {
            $routeData->incrementStatusCode(200);
        }
        for ($i = 0; $i < 5; $i++) {
            $routeData->incrementStatusCode(400);
        }
        for ($i = 0; $i < 2; $i++) {
            $routeData->incrementStatusCode(404);
        }
        for ($i = 0; $i < 2; $i++) {
            $routeData->incrementStatusCode(500);
        }
        $routeData->incrementStatusCode(503);
        
        // Calculate error ratio for 4xx and 5xx
        $errorCodes = [400, 401, 403, 404, 500, 503];
        $totalErrorRatio = 0.0;
        foreach ($errorCodes as $code) {
            $totalErrorRatio += $routeData->getStatusCodeRatio($code);
        }
        
        $this->assertSame(20.0, $totalErrorRatio); // 10% + 4% + 4% + 2% = 20%
    }
}
