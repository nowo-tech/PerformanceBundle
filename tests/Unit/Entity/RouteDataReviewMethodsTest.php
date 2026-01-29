<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Entity;

use Nowo\PerformanceBundle\Entity\RouteData;
use PHPUnit\Framework\TestCase;

/**
 * Tests for RouteData review-related methods.
 */
final class RouteDataReviewMethodsTest extends TestCase
{
    public function testSetReviewedReturnsSelf(): void
    {
        $routeData = new RouteData();
        $result = $routeData->setReviewed(true);

        $this->assertSame($routeData, $result);
    }

    public function testSetReviewedAtReturnsSelf(): void
    {
        $routeData = new RouteData();
        $date = new \DateTimeImmutable();
        $result = $routeData->setReviewedAt($date);

        $this->assertSame($routeData, $result);
    }

    public function testSetQueriesImprovedReturnsSelf(): void
    {
        $routeData = new RouteData();
        $result = $routeData->setQueriesImproved(true);

        $this->assertSame($routeData, $result);
    }

    public function testSetTimeImprovedReturnsSelf(): void
    {
        $routeData = new RouteData();
        $result = $routeData->setTimeImproved(false);

        $this->assertSame($routeData, $result);
    }

    public function testSetReviewedByReturnsSelf(): void
    {
        $routeData = new RouteData();
        $result = $routeData->setReviewedBy('admin');

        $this->assertSame($routeData, $result);
    }

    public function testMarkAsReviewedReturnsSelf(): void
    {
        $routeData = new RouteData();
        $result = $routeData->markAsReviewed(true, false, 'user123');

        $this->assertSame($routeData, $result);
    }

    public function testMarkAsReviewedSetsReviewedToTrue(): void
    {
        $routeData = new RouteData();
        $routeData->markAsReviewed();

        $this->assertTrue($routeData->isReviewed());
    }

    public function testMarkAsReviewedSetsReviewedAt(): void
    {
        $routeData = new RouteData();
        $routeData->markAsReviewed();

        $this->assertInstanceOf(\DateTimeImmutable::class, $routeData->getReviewedAt());
        $this->assertNotNull($routeData->getReviewedAt());
    }

    public function testMarkAsReviewedWithOnlyQueriesImproved(): void
    {
        $routeData = new RouteData();
        $routeData->markAsReviewed(true, null, null);

        $this->assertTrue($routeData->isReviewed());
        $this->assertTrue($routeData->getQueriesImproved());
        $this->assertNull($routeData->getTimeImproved());
        $this->assertNull($routeData->getReviewedBy());
    }

    public function testMarkAsReviewedWithOnlyTimeImproved(): void
    {
        $routeData = new RouteData();
        $routeData->markAsReviewed(null, true, null);

        $this->assertTrue($routeData->isReviewed());
        $this->assertNull($routeData->getQueriesImproved());
        $this->assertTrue($routeData->getTimeImproved());
        $this->assertNull($routeData->getReviewedBy());
    }

    public function testMarkAsReviewedWithOnlyReviewedBy(): void
    {
        $routeData = new RouteData();
        $routeData->markAsReviewed(null, null, 'reviewer');

        $this->assertTrue($routeData->isReviewed());
        $this->assertNull($routeData->getQueriesImproved());
        $this->assertNull($routeData->getTimeImproved());
        $this->assertSame('reviewer', $routeData->getReviewedBy());
    }

    public function testMarkAsReviewedDoesNotOverrideExistingValuesWhenNull(): void
    {
        $routeData = new RouteData();
        $routeData->setQueriesImproved(true);
        $routeData->setTimeImproved(false);
        $routeData->setReviewedBy('admin');

        $routeData->markAsReviewed(null, null, null);

        $this->assertTrue($routeData->getQueriesImproved());
        $this->assertFalse($routeData->getTimeImproved());
        $this->assertSame('admin', $routeData->getReviewedBy());
    }

    public function testMarkAsReviewedOverridesExistingValues(): void
    {
        $routeData = new RouteData();
        $routeData->setQueriesImproved(false);
        $routeData->setTimeImproved(true);
        $routeData->setReviewedBy('old_user');

        $routeData->markAsReviewed(true, false, 'new_user');

        $this->assertTrue($routeData->getQueriesImproved());
        $this->assertFalse($routeData->getTimeImproved());
        $this->assertSame('new_user', $routeData->getReviewedBy());
    }

    public function testGetSaveAccessRecordsDefaultsToTrue(): void
    {
        $routeData = new RouteData();
        $this->assertTrue($routeData->getSaveAccessRecords());
    }

    public function testSetSaveAccessRecords(): void
    {
        $routeData = new RouteData();
        $routeData->setSaveAccessRecords(false);
        $this->assertFalse($routeData->getSaveAccessRecords());

        $routeData->setSaveAccessRecords(true);
        $this->assertTrue($routeData->getSaveAccessRecords());
    }

    public function testSetSaveAccessRecordsReturnsSelf(): void
    {
        $routeData = new RouteData();
        $result = $routeData->setSaveAccessRecords(false);
        $this->assertSame($routeData, $result);
    }
}
