<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\EventSubscriber;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Nowo\PerformanceBundle\EventSubscriber\RouteDataRecordTableNameSubscriber;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests for RouteDataRecordTableNameSubscriber.
 */
final class RouteDataRecordTableNameSubscriberTest extends TestCase
{
    private RouteDataRecordTableNameSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->subscriber = new RouteDataRecordTableNameSubscriber('routes_data');
    }

    public function testGetSubscribedEvents(): void
    {
        $events = $this->subscriber->getSubscribedEvents();

        $this->assertIsArray($events);
        $this->assertContains('loadClassMetadata', $events);
    }

    public function testLoadClassMetadataWithRouteDataRecordEntity(): void
    {
        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->name = 'Nowo\PerformanceBundle\Entity\RouteDataRecord';

        // Mock getTableName method (DBAL 3.x)
        if (method_exists($classMetadata, 'getTableName')) {
            $classMetadata->method('getTableName')->willReturn('routes_data_records');
        }

        // Mock table property access
        $reflection = new \ReflectionClass($classMetadata);
        if ($reflection->hasProperty('table')) {
            $tableProperty = $reflection->getProperty('table');
            $tableProperty->setAccessible(true);
            $tableProperty->setValue($classMetadata, ['name' => 'routes_data_records', 'indexes' => []]);
        }

        $classMetadata->expects($this->once())
            ->method('setPrimaryTable')
            ->with($this->callback(function ($table) {
                return isset($table['name']) && $table['name'] === 'routes_data_records';
            }));

        $eventArgs = $this->createMock(LoadClassMetadataEventArgs::class);
        $eventArgs->method('getClassMetadata')->willReturn($classMetadata);

        $this->subscriber->loadClassMetadata($eventArgs);
    }

    public function testLoadClassMetadataWithDifferentEntity(): void
    {
        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->name = 'Nowo\PerformanceBundle\Entity\RouteData';

        $classMetadata->expects($this->never())
            ->method('setPrimaryTable');

        $eventArgs = $this->createMock(LoadClassMetadataEventArgs::class);
        $eventArgs->method('getClassMetadata')->willReturn($classMetadata);

        $this->subscriber->loadClassMetadata($eventArgs);
    }

    public function testLoadClassMetadataWithCustomTableName(): void
    {
        $subscriber = new RouteDataRecordTableNameSubscriber('custom_routes');
        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->name = 'Nowo\PerformanceBundle\Entity\RouteDataRecord';

        if (method_exists($classMetadata, 'getTableName')) {
            $classMetadata->method('getTableName')->willReturn('routes_data_records');
        }

        $reflection = new \ReflectionClass($classMetadata);
        if ($reflection->hasProperty('table')) {
            $tableProperty = $reflection->getProperty('table');
            $tableProperty->setAccessible(true);
            $tableProperty->setValue($classMetadata, ['name' => 'routes_data_records', 'indexes' => []]);
        }

        $classMetadata->expects($this->once())
            ->method('setPrimaryTable')
            ->with($this->callback(function ($table) {
                return isset($table['name']) && $table['name'] === 'custom_routes_records';
            }));

        $eventArgs = $this->createMock(LoadClassMetadataEventArgs::class);
        $eventArgs->method('getClassMetadata')->willReturn($classMetadata);

        $subscriber->loadClassMetadata($eventArgs);
    }

    public function testLoadClassMetadataPreservesIndexes(): void
    {
        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->name = 'Nowo\PerformanceBundle\Entity\RouteDataRecord';

        $existingIndexes = [
            'idx_record_route_data_id' => ['columns' => ['route_data_id']],
            'idx_record_accessed_at' => ['columns' => ['accessed_at']],
        ];

        if (method_exists($classMetadata, 'getTableName')) {
            $classMetadata->method('getTableName')->willReturn('routes_data_records');
        }

        $reflection = new \ReflectionClass($classMetadata);
        if ($reflection->hasProperty('table')) {
            $tableProperty = $reflection->getProperty('table');
            $tableProperty->setAccessible(true);
            $tableProperty->setValue($classMetadata, [
                'name' => 'routes_data_records',
                'indexes' => $existingIndexes,
            ]);
        }

        $classMetadata->expects($this->once())
            ->method('setPrimaryTable')
            ->with($this->callback(function ($table) use ($existingIndexes) {
                return isset($table['name'])
                    && $table['name'] === 'routes_data_records'
                    && isset($table['indexes'])
                    && $table['indexes'] === $existingIndexes;
            }));

        $eventArgs = $this->createMock(LoadClassMetadataEventArgs::class);
        $eventArgs->method('getClassMetadata')->willReturn($classMetadata);

        $this->subscriber->loadClassMetadata($eventArgs);
    }

    public function testLoadClassMetadataWithTableNameAlreadySet(): void
    {
        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->name = 'Nowo\PerformanceBundle\Entity\RouteDataRecord';

        if (method_exists($classMetadata, 'getTableName')) {
            $classMetadata->method('getTableName')->willReturn('routes_data_records');
        }

        $reflection = new \ReflectionClass($classMetadata);
        if ($reflection->hasProperty('table')) {
            $tableProperty = $reflection->getProperty('table');
            $tableProperty->setAccessible(true);
            $tableProperty->setValue($classMetadata, ['name' => 'routes_data_records', 'indexes' => []]);
        }

        // If table name is already correct, setPrimaryTable might not be called
        // This depends on the implementation, but we test that it doesn't break
        $eventArgs = $this->createMock(LoadClassMetadataEventArgs::class);
        $eventArgs->method('getClassMetadata')->willReturn($classMetadata);

        // Should not throw an error
        $this->subscriber->loadClassMetadata($eventArgs);
    }
}
