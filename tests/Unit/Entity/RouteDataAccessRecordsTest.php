<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Entity;

use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Entity\RouteDataRecord;
use PHPUnit\Framework\TestCase;

/**
 * Tests for RouteData access records collection methods.
 */
final class RouteDataAccessRecordsTest extends TestCase
{
    public function testGetAccessRecordsReturnsCollection(): void
    {
        $routeData = new RouteData();
        $collection = $routeData->getAccessRecords();

        $this->assertInstanceOf(\Doctrine\Common\Collections\Collection::class, $collection);
        $this->assertCount(0, $collection);
    }

    public function testAddAccessRecordSetsRouteDataOnRecord(): void
    {
        $routeData = new RouteData();
        $record = new RouteDataRecord();

        $routeData->addAccessRecord($record);

        $this->assertSame($routeData, $record->getRouteData());
    }

    public function testAddAccessRecordReturnsSelf(): void
    {
        $routeData = new RouteData();
        $record = new RouteDataRecord();
        $result = $routeData->addAccessRecord($record);

        $this->assertSame($routeData, $result);
    }

    public function testAddAccessRecordDoesNotAddDuplicate(): void
    {
        $routeData = new RouteData();
        $record = new RouteDataRecord();

        $routeData->addAccessRecord($record);
        $routeData->addAccessRecord($record);

        $this->assertCount(1, $routeData->getAccessRecords());
    }

    public function testAddAccessRecordWhenRecordAlreadyHasDifferentRouteData(): void
    {
        $routeData1 = new RouteData();
        $routeData2 = new RouteData();
        $record = new RouteDataRecord();
        $record->setRouteData($routeData1);

        $routeData2->addAccessRecord($record);

        $this->assertSame($routeData2, $record->getRouteData());
        $this->assertTrue($routeData2->getAccessRecords()->contains($record));
        $this->assertFalse($routeData1->getAccessRecords()->contains($record));
    }

    public function testRemoveAccessRecordReturnsSelf(): void
    {
        $routeData = new RouteData();
        $record = new RouteDataRecord();
        $routeData->addAccessRecord($record);

        $result = $routeData->removeAccessRecord($record);

        $this->assertSame($routeData, $result);
    }

    public function testRemoveAccessRecordSetsRouteDataToNull(): void
    {
        $routeData = new RouteData();
        $record = new RouteDataRecord();
        $routeData->addAccessRecord($record);

        $routeData->removeAccessRecord($record);

        $this->assertNull($record->getRouteData());
    }

    public function testRemoveAccessRecordWhenRecordNotInCollection(): void
    {
        $routeData = new RouteData();
        $record = new RouteDataRecord();
        $record->setRouteData($routeData);

        $routeData->removeAccessRecord($record);

        $this->assertNull($record->getRouteData());
        $this->assertCount(0, $routeData->getAccessRecords());
    }

    public function testRemoveAccessRecordWhenRecordHasDifferentRouteData(): void
    {
        $routeData1 = new RouteData();
        $routeData2 = new RouteData();
        $record = new RouteDataRecord();
        $routeData1->addAccessRecord($record);

        $routeData2->removeAccessRecord($record);

        $this->assertSame($routeData1, $record->getRouteData());
        $this->assertCount(1, $routeData1->getAccessRecords());
    }

    public function testMultipleAccessRecords(): void
    {
        $routeData = new RouteData();
        $record1 = new RouteDataRecord();
        $record2 = new RouteDataRecord();
        $record3 = new RouteDataRecord();

        $routeData->addAccessRecord($record1);
        $routeData->addAccessRecord($record2);
        $routeData->addAccessRecord($record3);

        $this->assertCount(3, $routeData->getAccessRecords());
        $this->assertTrue($routeData->getAccessRecords()->contains($record1));
        $this->assertTrue($routeData->getAccessRecords()->contains($record2));
        $this->assertTrue($routeData->getAccessRecords()->contains($record3));
    }

    public function testRemoveMultipleAccessRecords(): void
    {
        $routeData = new RouteData();
        $record1 = new RouteDataRecord();
        $record2 = new RouteDataRecord();
        $record3 = new RouteDataRecord();

        $routeData->addAccessRecord($record1);
        $routeData->addAccessRecord($record2);
        $routeData->addAccessRecord($record3);

        $routeData->removeAccessRecord($record2);

        $this->assertCount(2, $routeData->getAccessRecords());
        $this->assertTrue($routeData->getAccessRecords()->contains($record1));
        $this->assertFalse($routeData->getAccessRecords()->contains($record2));
        $this->assertTrue($routeData->getAccessRecords()->contains($record3));
    }
}
