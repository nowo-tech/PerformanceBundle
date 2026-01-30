<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\EventSubscriber;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use Nowo\PerformanceBundle\EventSubscriber\RouteDataRecordTableNameSubscriber;
use PHPUnit\Framework\TestCase;

final class RouteDataRecordTableNameSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        $subscriber = new RouteDataRecordTableNameSubscriber('routes_data');
        $events = $subscriber->getSubscribedEvents();

        $this->assertIsArray($events);
        $this->assertContains(Events::loadClassMetadata, $events);
    }

    public function testLoadClassMetadataForOtherEntity(): void
    {
        $subscriber = new RouteDataRecordTableNameSubscriber('routes_data');

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->method('getName')->willReturn('App\Entity\Other');
        $classMetadata->expects($this->never())->method('setPrimaryTable');

        $eventArgs = $this->createMock(LoadClassMetadataEventArgs::class);
        $eventArgs->method('getClassMetadata')->willReturn($classMetadata);

        $subscriber->loadClassMetadata($eventArgs);
    }

    public function testLoadClassMetadataForRouteDataRecordSetsTableName(): void
    {
        $mainTable = 'custom_routes';
        $expectedRecordsTable = $mainTable . '_records';
        $subscriber = new RouteDataRecordTableNameSubscriber($mainTable);

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->method('getName')->willReturn('Nowo\PerformanceBundle\Entity\RouteDataRecord');
        $classMetadata->method('getTableName')->willReturn('routes_data_records');

        $ref = new \ReflectionClass($classMetadata);
        if ($ref->hasProperty('table')) {
            $p = $ref->getProperty('table');
            $p->setAccessible(true);
            $p->setValue($classMetadata, ['name' => 'routes_data_records', 'indexes' => [['name' => 'idx_foo']]]);
        }

        $actualTable = null;
        $classMetadata->expects($this->once())
            ->method('setPrimaryTable')
            ->with($this->callback(function ($arg) use ($expectedRecordsTable, &$actualTable): bool {
                $actualTable = $arg;
                return true;
            }));

        $eventArgs = $this->createMock(LoadClassMetadataEventArgs::class);
        $eventArgs->method('getClassMetadata')->willReturn($classMetadata);

        $subscriber->loadClassMetadata($eventArgs);

        $this->assertNotNull($actualTable);
        $this->assertIsArray($actualTable);
        if (!\is_array($actualTable)) {
            return;
        }
        $this->assertArrayHasKey('name', $actualTable);
        $this->assertArrayHasKey('indexes', $actualTable);
        $this->assertSame($expectedRecordsTable, $actualTable['name']);
    }

    public function testLoadClassMetadataWhenTableNameMatchesDoesNotCallSetPrimaryTable(): void
    {
        $subscriber = new RouteDataRecordTableNameSubscriber('routes_data');

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->expects($this->once())
            ->method('getName')
            ->willReturn('Nowo\PerformanceBundle\Entity\RouteDataRecord');
        $classMetadata->expects($this->any())
            ->method('getTableName')
            ->willReturn('routes_data_records');
        $classMetadata->expects($this->never())
            ->method('setPrimaryTable');

        $eventArgs = $this->createMock(LoadClassMetadataEventArgs::class);
        $eventArgs->expects($this->once())
            ->method('getClassMetadata')
            ->willReturn($classMetadata);

        $subscriber->loadClassMetadata($eventArgs);
    }

    public function testConstructorWithCustomMainTableName(): void
    {
        $subscriber = new RouteDataRecordTableNameSubscriber('perf_metrics');
        $events = $subscriber->getSubscribedEvents();

        $this->assertContains(Events::loadClassMetadata, $events);
    }

    public function testConstructorWithDefaultTableName(): void
    {
        $subscriber = new RouteDataRecordTableNameSubscriber('routes_data');
        $events = $subscriber->getSubscribedEvents();

        $this->assertIsArray($events);
        $this->assertContains(Events::loadClassMetadata, $events);
    }
}
