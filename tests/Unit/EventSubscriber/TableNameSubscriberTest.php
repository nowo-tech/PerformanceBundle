<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\EventSubscriber;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Nowo\PerformanceBundle\EventSubscriber\TableNameSubscriber;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class TableNameSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        $subscriber = new TableNameSubscriber('custom_table');

        $events = $subscriber->getSubscribedEvents();

        $this->assertIsArray($events);
        $this->assertContains('loadClassMetadata', $events);
    }

    public function testLoadClassMetadataForRouteData(): void
    {
        $tableName  = 'custom_performance_table';
        $subscriber = new TableNameSubscriber($tableName);

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->expects($this->once())
            ->method('getName')
            ->willReturn('Nowo\PerformanceBundle\Entity\RouteData');

        // Initialize table property to avoid "must not be accessed before initialization" error
        $reflection = new ReflectionClass($classMetadata);
        if ($reflection->hasProperty('table')) {
            $tableProperty = $reflection->getProperty('table');
            $tableProperty->setAccessible(true);
            $tableProperty->setValue($classMetadata, ['name' => 'route_data', 'indexes' => []]);
        }

        $classMetadata->expects($this->any())
            ->method('getTableName')
            ->willReturn('route_data');

        $actualTable = null;
        $classMetadata->expects($this->once())
            ->method('setPrimaryTable')
            ->with($this->callback(static function ($table) use (&$actualTable) {
                $actualTable = $table;

                return true; // Always return true, we'll assert separately
            }));

        $eventArgs = $this->createMock(LoadClassMetadataEventArgs::class);
        $eventArgs->expects($this->once())
            ->method('getClassMetadata')
            ->willReturn($classMetadata);

        $subscriber->loadClassMetadata($eventArgs);

        // Assertions to avoid risky test
        $this->assertNotNull($actualTable, 'setPrimaryTable should have been called with a table array');
        $this->assertIsArray($actualTable, 'setPrimaryTable should receive an array');
        $this->assertArrayHasKey('name', $actualTable, 'Table array should have a name key');
        $this->assertSame($tableName, $actualTable['name'], 'Table name should match configured name');
    }

    public function testLoadClassMetadataForOtherEntity(): void
    {
        $subscriber = new TableNameSubscriber('custom_table');

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->expects($this->once())
            ->method('getName')
            ->willReturn('App\Entity\OtherEntity');

        $classMetadata->expects($this->never())
            ->method('setPrimaryTable');

        $eventArgs = $this->createMock(LoadClassMetadataEventArgs::class);
        $eventArgs->expects($this->once())
            ->method('getClassMetadata')
            ->willReturn($classMetadata);

        $subscriber->loadClassMetadata($eventArgs);
    }

    public function testLoadClassMetadataWhenTableNameMatchesDoesNotCallSetPrimaryTable(): void
    {
        $tableName  = 'routes_data';
        $subscriber = new TableNameSubscriber($tableName);

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->expects($this->once())
            ->method('getName')
            ->willReturn('Nowo\PerformanceBundle\Entity\RouteData');
        $classMetadata->expects($this->any())
            ->method('getTableName')
            ->willReturn($tableName);
        $classMetadata->expects($this->never())
            ->method('setPrimaryTable');

        $eventArgs = $this->createMock(LoadClassMetadataEventArgs::class);
        $eventArgs->expects($this->once())
            ->method('getClassMetadata')
            ->willReturn($classMetadata);

        $subscriber->loadClassMetadata($eventArgs);
    }

    public function testConstructorWithDifferentTableName(): void
    {
        $subscriber = new TableNameSubscriber('performance_metrics');

        $events = $subscriber->getSubscribedEvents();

        $this->assertContains('loadClassMetadata', $events);
    }
}
