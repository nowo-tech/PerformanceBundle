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

final class PerformanceMetricsServiceStatusCodesTest extends TestCase
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

    public function testRecordMetricsWithStatusCodeForNewRoute(): void
    {
        $this->repository->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (RouteData $routeData) {
                return $routeData->getName() === 'app_home' &&
                       $routeData->getStatusCodeCount(200) === 1;
            }));

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
        $this->assertFalse($result['was_updated']);
    }

    public function testRecordMetricsWithStatusCodeForExistingRoute(): void
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
            0.4,
            8,
            0.15,
            null,
            null,
            'GET',
            200,
            [200, 404, 500]
        );

        $this->assertFalse($result['is_new']);
        $this->assertTrue($result['was_updated']);
        $this->assertSame(1, $routeData->getStatusCodeCount(200));
    }

    public function testRecordMetricsIgnoresStatusCodeNotInTrackList(): void
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
            201, // Not in track list
            [200, 404, 500] // Only tracking these
        );

        $this->assertSame(0, $routeData->getStatusCodeCount(201));
        $this->assertFalse($result['was_updated']);
    }

    public function testRecordMetricsWithMultipleStatusCodes(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('dev');

        $this->repository->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn($routeData);

        $this->entityManager->expects($this->exactly(3))
            ->method('flush');

        // First request: 200
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

        // Second request: 200 again
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

        // Third request: 404
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

        $this->assertSame(2, $routeData->getStatusCodeCount(200));
        $this->assertSame(1, $routeData->getStatusCodeCount(404));
        $this->assertSame(66.67, round($routeData->getStatusCodeRatio(200), 2));
        $this->assertSame(33.33, round($routeData->getStatusCodeRatio(404), 2));
    }

    public function testRecordMetricsWithNullStatusCode(): void
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
            null, // No status code
            [200, 404, 500]
        );

        $this->assertSame(0, $routeData->getTotalResponses());
        $this->assertFalse($result['was_updated']);
    }

    public function testRecordMetricsWithEmptyTrackStatusCodes(): void
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
            200,
            [] // Empty track list
        );

        $this->assertSame(0, $routeData->getTotalResponses());
        $this->assertFalse($result['was_updated']);
    }
}
