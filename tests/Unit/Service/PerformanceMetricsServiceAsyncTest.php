<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\Message\RecordMetricsMessage;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests for async behaviour in PerformanceMetricsService (message bus dispatch).
 */
final class PerformanceMetricsServiceAsyncTest extends TestCase
{
    private ManagerRegistry|MockObject $registry;
    private EntityManagerInterface|MockObject $entityManager;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->registry
            ->method('getManager')
            ->with('default')
            ->willReturn($this->entityManager);
    }

    public function testRecordMetricsDispatchesMessageWhenAsyncAndMessageBusAvailable(): void
    {
        $service = new PerformanceMetricsService(
            $this->registry,
            'default',
            true,  // async enabled
            false, // enableAccessRecords
            false  // enableLogging (keep logs off for this test)
        );

        // Mock message bus as generic object with dispatch() method
        $messageBus = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['dispatch'])
            ->getMock();

        $messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($message) {
                return $message instanceof RecordMetricsMessage
                    && $message->getRouteName() === 'app_home'
                    && $message->getEnv() === 'dev'
                    && $message->getRequestTime() === 0.5
                    && $message->getTotalQueries() === 10;
            }));

        // No DB interaction should happen in async/dispatch branch
        $this->entityManager
            ->expects($this->never())
            ->method('persist');
        $this->entityManager
            ->expects($this->never())
            ->method('flush');

        $service->setMessageBus($messageBus);

        $result = $service->recordMetrics(
            'app_home',
            'dev',
            0.5,
            10,
            0.2,
            ['id' => 123],
            1048576,
            'GET'
        );

        $this->assertIsArray($result);
        $this->assertFalse($result['is_new']);
        $this->assertFalse($result['was_updated']);
    }

    public function testRecordMetricsFallsBackToSyncWhenAsyncButMessageBusNull(): void
    {
        $service = new PerformanceMetricsService(
            $this->registry,
            'default',
            true,  // async enabled
            false,
            false
        );

        // EntityManager should be used because messageBus is null
        $this->entityManager
            ->expects($this->once())
            ->method('persist');
        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $service->recordMetrics(
            'app_home',
            'dev',
            0.5,
            10,
            0.2
        );

        $this->assertIsArray($result);
        $this->assertTrue($result['is_new']);
    }
}

