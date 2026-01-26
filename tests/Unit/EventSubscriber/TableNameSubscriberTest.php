<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\EventSubscriber;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Nowo\PerformanceBundle\EventSubscriber\TableNameSubscriber;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

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
        $tableName = 'custom_performance_table';
        $subscriber = new TableNameSubscriber($tableName);
        
        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->expects($this->once())
            ->method('getName')
            ->willReturn('Nowo\PerformanceBundle\Entity\RouteData');
        
        $classMetadata->expects($this->once())
            ->method('setPrimaryTable')
            ->with($this->callback(function ($table) use ($tableName) {
                return $table['name'] === $tableName
                    && isset($table['indexes'])
                    && count($table['indexes']) === 2;
            }));
        
        $eventArgs = $this->createMock(LoadClassMetadataEventArgs::class);
        $eventArgs->expects($this->once())
            ->method('getClassMetadata')
            ->willReturn($classMetadata);
        
        $subscriber->loadClassMetadata($eventArgs);
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
}
