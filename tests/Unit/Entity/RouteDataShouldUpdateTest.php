<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Entity;

use Nowo\PerformanceBundle\Entity\RouteData;
use PHPUnit\Framework\TestCase;

/**
 * Tests for RouteData::shouldUpdate() method edge cases.
 */
final class RouteDataShouldUpdateTest extends TestCase
{
    public function testShouldUpdateReturnsTrueWhenNewRequestTimeIsWorse(): void
    {
        $routeData = new RouteData();
        $routeData->setRequestTime(0.3);
        $routeData->setTotalQueries(10);

        $this->assertTrue($routeData->shouldUpdate(0.5, 10));
    }

    public function testShouldUpdateReturnsTrueWhenNewQueryCountIsWorse(): void
    {
        $routeData = new RouteData();
        $routeData->setRequestTime(0.5);
        $routeData->setTotalQueries(5);

        $this->assertTrue($routeData->shouldUpdate(0.5, 10));
    }

    public function testShouldUpdateReturnsFalseWhenMetricsAreBetter(): void
    {
        $routeData = new RouteData();
        $routeData->setRequestTime(0.5);
        $routeData->setTotalQueries(10);

        $this->assertFalse($routeData->shouldUpdate(0.3, 5));
    }

    public function testShouldUpdateReturnsFalseWhenMetricsAreEqual(): void
    {
        $routeData = new RouteData();
        $routeData->setRequestTime(0.5);
        $routeData->setTotalQueries(10);

        $this->assertFalse($routeData->shouldUpdate(0.5, 10));
    }

    public function testShouldUpdateReturnsTrueWhenExistingRequestTimeIsNull(): void
    {
        $routeData = new RouteData();
        $routeData->setRequestTime(null);
        $routeData->setTotalQueries(10);

        $this->assertTrue($routeData->shouldUpdate(0.5, 10));
    }

    public function testShouldUpdateReturnsTrueWhenExistingQueryCountIsNull(): void
    {
        $routeData = new RouteData();
        $routeData->setRequestTime(0.5);
        $routeData->setTotalQueries(null);

        $this->assertTrue($routeData->shouldUpdate(0.5, 10));
    }

    public function testShouldUpdateReturnsFalseWhenNewRequestTimeIsNull(): void
    {
        $routeData = new RouteData();
        $routeData->setRequestTime(0.5);
        $routeData->setTotalQueries(10);

        $this->assertFalse($routeData->shouldUpdate(null, 10));
    }

    public function testShouldUpdateReturnsFalseWhenNewQueryCountIsNull(): void
    {
        $routeData = new RouteData();
        $routeData->setRequestTime(0.5);
        $routeData->setTotalQueries(10);

        $this->assertFalse($routeData->shouldUpdate(0.5, null));
    }

    public function testShouldUpdateReturnsFalseWhenBothNewValuesAreNull(): void
    {
        $routeData = new RouteData();
        $routeData->setRequestTime(0.5);
        $routeData->setTotalQueries(10);

        $this->assertFalse($routeData->shouldUpdate(null, null));
    }

    public function testShouldUpdateReturnsTrueWhenBothExistingValuesAreNull(): void
    {
        $routeData = new RouteData();
        $routeData->setRequestTime(null);
        $routeData->setTotalQueries(null);

        $this->assertTrue($routeData->shouldUpdate(0.5, 10));
    }

    public function testShouldUpdateWithVerySmallDifferences(): void
    {
        $routeData = new RouteData();
        $routeData->setRequestTime(0.5000000);
        $routeData->setTotalQueries(10);

        // Very small difference (floating point precision)
        $this->assertFalse($routeData->shouldUpdate(0.5000001, 10));
    }

    public function testShouldUpdateWithVeryLargeDifferences(): void
    {
        $routeData = new RouteData();
        $routeData->setRequestTime(0.5);
        $routeData->setTotalQueries(10);

        $this->assertTrue($routeData->shouldUpdate(3600.0, 999999));
    }

    public function testShouldUpdateWithZeroValues(): void
    {
        $routeData = new RouteData();
        $routeData->setRequestTime(0.0);
        $routeData->setTotalQueries(0);

        $this->assertFalse($routeData->shouldUpdate(0.0, 0));
    }

    public function testShouldUpdateWithZeroExistingAndPositiveNew(): void
    {
        $routeData = new RouteData();
        $routeData->setRequestTime(0.0);
        $routeData->setTotalQueries(0);

        $this->assertTrue($routeData->shouldUpdate(0.1, 1));
    }
}
