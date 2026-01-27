<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Advanced tests for status codes functionality in PerformanceMetricsService.
 */
final class PerformanceMetricsServiceStatusCodesAdvancedTest extends TestCase
{
    private ManagerRegistry&MockObject $registry;
    private EntityManagerInterface&MockObject $entityManager;
    private RouteDataRepository&MockObject $repository;
    private PerformanceMetricsService $service;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(RouteDataRepository::class);

        $this->registry->method('getManager')
            ->with('default')
            ->willReturn($this->entityManager);

        $this->entityManager->method('getRepository')
            ->with(RouteData::class)
            ->willReturn($this->repository);

        $this->service = new PerformanceMetricsService(
            $this->registry,
            'default',
            false
        );
    }

    public function testRecordMetricsWithMultipleStatusCodesIncrementsCorrectly(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('dev');

        $this->repository->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn($routeData);

        $this->entityManager->expects($this->exactly(5))
            ->method('flush');

        // Record 5 requests with different status codes
        $statusCodes = [200, 200, 404, 500, 200];
        foreach ($statusCodes as $statusCode) {
            $this->service->recordMetrics(
                'app_home',
                'dev',
                0.5,
                10,
                0.2,
                null,
                null,
                'GET',
                $statusCode,
                [200, 404, 500, 503]
            );
        }

        $this->assertSame(3, $routeData->getStatusCodeCount(200));
        $this->assertSame(1, $routeData->getStatusCodeCount(404));
        $this->assertSame(1, $routeData->getStatusCodeCount(500));
        $this->assertSame(0, $routeData->getStatusCodeCount(503));
        $this->assertSame(5, $routeData->getTotalResponses());
    }

    public function testRecordMetricsWithStatusCodeUpdatesWasUpdatedFlag(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('dev');

        $this->repository->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn($routeData);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->recordMetrics(
            'app_home',
            'dev',
            0.5,
            10,
            0.2,
            null,
            null,
            'GET',
            404, // Error status code
            [200, 404, 500]
        );

        // Status code increment should mark as updated
        $this->assertTrue($result['was_updated']);
        $this->assertSame(1, $routeData->getStatusCodeCount(404));
    }

    public function testRecordMetricsWithStatusCodeForNewRouteSetsWasUpdated(): void
    {
        $this->repository->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(RouteData::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->recordMetrics(
            'app_home',
            'dev',
            0.5,
            10,
            0.2,
            null,
            null,
            'GET',
            200,
            [200, 404, 500]
        );

        $this->assertTrue($result['is_new']);
        // New route with status code should not be marked as updated
        $this->assertFalse($result['was_updated']);
    }

    public function testRecordMetricsWithStatusCodeOnlyUpdatesWhenInTrackList(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('dev');

        $this->repository->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn($routeData);

        $this->entityManager->expects($this->exactly(2))
            ->method('flush');

        // First: status code in track list
        $result1 = $this->service->recordMetrics(
            'app_home',
            'dev',
            0.5,
            10,
            0.2,
            null,
            null,
            'GET',
            200,
            [200, 404, 500]
        );

        $this->assertTrue($result1['was_updated']);
        $this->assertSame(1, $routeData->getStatusCodeCount(200));

        // Second: status code NOT in track list
        $result2 = $this->service->recordMetrics(
            'app_home',
            'dev',
            0.5,
            10,
            0.2,
            null,
            null,
            'GET',
            201, // Not in [200, 404, 500]
            [200, 404, 500]
        );

        // Should not increment status code, but might update other metrics
        $this->assertSame(1, $routeData->getStatusCodeCount(200));
        $this->assertSame(0, $routeData->getStatusCodeCount(201));
    }

    public function testRecordMetricsWithStatusCodeCalculatesRatiosCorrectly(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('dev');

        $this->repository->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn($routeData);

        $this->entityManager->expects($this->exactly(10))
            ->method('flush');

        // Record 10 requests: 7x 200, 2x 404, 1x 500
        for ($i = 0; $i < 7; $i++) {
            $this->service->recordMetrics(
                'app_home',
                'dev',
                0.5,
                10,
                0.2,
                null,
                null,
                'GET',
                200,
                [200, 404, 500]
            );
        }
        for ($i = 0; $i < 2; $i++) {
            $this->service->recordMetrics(
                'app_home',
                'dev',
                0.5,
                10,
                0.2,
                null,
                null,
                'GET',
                404,
                [200, 404, 500]
            );
        }
        $this->service->recordMetrics(
            'app_home',
            'dev',
            0.5,
            10,
            0.2,
            null,
            null,
            'GET',
            500,
            [200, 404, 500]
        );

        $this->assertSame(70.0, $routeData->getStatusCodeRatio(200));
        $this->assertSame(20.0, $routeData->getStatusCodeRatio(404));
        $this->assertSame(10.0, $routeData->getStatusCodeRatio(500));
        $this->assertSame(10, $routeData->getTotalResponses());
    }

    public function testRecordMetricsWithStatusCodeAndOtherMetrics(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('dev');
        $routeData->setRequestTime(0.3);
        $routeData->setTotalQueries(5);

        $this->repository->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn($routeData);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->recordMetrics(
            'app_home',
            'dev',
            0.5, // Worse request time
            8,   // More queries
            0.2,
            null,
            null,
            'POST',
            500, // Error status
            [200, 404, 500]
        );

        $this->assertTrue($result['was_updated']);
        $this->assertSame(0.5, $routeData->getRequestTime()); // Updated
        $this->assertSame(8, $routeData->getTotalQueries()); // Updated
        $this->assertSame('POST', $routeData->getHttpMethod()); // Updated
        $this->assertSame(1, $routeData->getStatusCodeCount(500)); // Status code tracked
    }
}
