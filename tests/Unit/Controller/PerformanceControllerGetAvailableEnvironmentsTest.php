<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Controller;

use Exception;
use Nowo\PerformanceBundle\Controller\PerformanceController;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use Nowo\PerformanceBundle\Service\PerformanceCacheService;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests for PerformanceController::getAvailableEnvironments() method.
 *
 * This test class specifically tests the getAvailableEnvironments() method
 * with various scenarios including cache, database, and fallback cases.
 */
final class PerformanceControllerGetAvailableEnvironmentsTest extends TestCase
{
    private PerformanceMetricsService|MockObject $metricsService;
    private PerformanceCacheService|MockObject|null $cacheService;
    private RouteDataRepository|MockObject $repository;

    protected function setUp(): void
    {
        $this->metricsService = $this->createMock(PerformanceMetricsService::class);
        $this->repository     = $this->createMock(RouteDataRepository::class);
        $this->cacheService   = null;
    }

    /**
     * Helper method to create a controller with specific configuration.
     */
    private function createController(
        ?PerformanceCacheService $cacheService = null,
        ?array $allowedEnvironments = null,
        bool $mockGetParameter = false,
        ?string $kernelEnvironment = null,
        bool $getParameterThrows = false
    ): PerformanceController {
        $allowed    = $allowedEnvironments ?? ['dev', 'test'];
        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null,
                true,
                [],
                'bootstrap',
                $cacheService,
                null,
                null,
                false,
                false,
                null,
                0.5,
                1.0,
                20,
                50,
                20.0,
                50.0,
                'Y-m-d H:i:s',
                'Y-m-d H:i',
                0,
                [200, 404, 500, 503],
                null,
                false,
                true,
                $allowed,
                'default',
                true,
                true,
                false,
                [],
                false,
                1.0,
                true,   // checkTableStatus
                true,   // enableLogging
                null,   // accessRecordsRetentionDays
            ])
            ->onlyMethods($mockGetParameter ? ['getParameter'] : [])
            ->getMock();

        if ($mockGetParameter) {
            $expectation = $controller->method('getParameter')->with('kernel.environment');
            if ($getParameterThrows) {
                $expectation->willThrowException(new Exception('Parameter not found'));
            } else {
                $expectation->willReturn($kernelEnvironment);
            }
        }

        return $controller;
    }

    /**
     * Helper method to call protected method using reflection.
     */
    private function callGetAvailableEnvironments(PerformanceController $controller): array
    {
        $reflection = new ReflectionClass($controller);
        $method     = $reflection->getMethod('getAvailableEnvironments');
        $method->setAccessible(true);

        return $method->invoke($controller);
    }

    public function testGetAvailableEnvironmentsFromCache(): void
    {
        $cachedEnvironments = ['dev', 'test', 'prod'];
        $this->cacheService = $this->createMock(PerformanceCacheService::class);
        $this->cacheService->method('getCachedEnvironments')
            ->willReturn($cachedEnvironments);

        $controller = $this->createController($this->cacheService, $cachedEnvironments);

        $result = $this->callGetAvailableEnvironments($controller);

        $this->assertSame($cachedEnvironments, $result);
        // Repository should not be called when cache is available
        $this->metricsService->expects($this->never())->method('getRepository');
    }

    public function testGetAvailableEnvironmentsFromDatabase(): void
    {
        $databaseEnvironments = ['dev', 'test', 'stage', 'prod'];
        $this->cacheService   = $this->createMock(PerformanceCacheService::class);
        $this->cacheService->method('getCachedEnvironments')
            ->willReturn(null); // No cache
        $this->cacheService->expects($this->once())
            ->method('cacheEnvironments')
            ->with($databaseEnvironments);

        $this->metricsService->method('getRepository')
            ->willReturn($this->repository);
        $this->repository->method('getDistinctEnvironments')
            ->willReturn($databaseEnvironments);

        $controller = $this->createController($this->cacheService, $databaseEnvironments);

        $result = $this->callGetAvailableEnvironments($controller);

        $this->assertSame($databaseEnvironments, $result);
    }

    public function testGetAvailableEnvironmentsEmptyDatabaseUsesAllowedEnvironments(): void
    {
        $allowedEnvironments = ['prod', 'dev', 'test', 'stage'];
        $this->cacheService  = $this->createMock(PerformanceCacheService::class);
        $this->cacheService->method('getCachedEnvironments')
            ->willReturn(null);
        $this->cacheService->expects($this->once())
            ->method('cacheEnvironments')
            ->with($allowedEnvironments);

        $this->metricsService->method('getRepository')
            ->willReturn($this->repository);
        $this->repository->method('getDistinctEnvironments')
            ->willReturn([]); // Empty database

        $controller = $this->createController($this->cacheService, $allowedEnvironments);

        $result = $this->callGetAvailableEnvironments($controller);

        $this->assertSame($allowedEnvironments, $result);
    }

    public function testGetAvailableEnvironmentsEmptyDatabaseUsesCurrentEnvironment(): void
    {
        $currentEnv         = 'prod';
        $this->cacheService = $this->createMock(PerformanceCacheService::class);
        $this->cacheService->method('getCachedEnvironments')
            ->willReturn(null);
        $this->cacheService->expects($this->once())
            ->method('cacheEnvironments')
            ->with([$currentEnv]);

        $this->metricsService->method('getRepository')
            ->willReturn($this->repository);
        $this->repository->method('getDistinctEnvironments')
            ->willReturn([]); // Empty database

        $controller = $this->createController(
            $this->cacheService,
            [], // no allowed → use current env as fallback
            true,
            $currentEnv,
        );

        $result = $this->callGetAvailableEnvironments($controller);

        $this->assertSame([$currentEnv], $result);
    }

    public function testGetAvailableEnvironmentsEmptyDatabaseUsesDefaultFallback(): void
    {
        $defaultEnvironments = ['dev', 'test', 'prod'];
        $this->cacheService  = $this->createMock(PerformanceCacheService::class);
        $this->cacheService->method('getCachedEnvironments')
            ->willReturn(null);
        $this->cacheService->expects($this->once())
            ->method('cacheEnvironments')
            ->with($defaultEnvironments);

        $this->metricsService->method('getRepository')
            ->willReturn($this->repository);
        $this->repository->method('getDistinctEnvironments')
            ->willReturn([]); // Empty database

        $controller = $this->createController(
            $this->cacheService,
            [], // No allowed environments
            true, // Mock getParameter
            null, // No kernel environment
        );

        $result = $this->callGetAvailableEnvironments($controller);

        $this->assertSame($defaultEnvironments, $result);
    }

    public function testGetAvailableEnvironmentsDatabaseExceptionUsesAllowedEnvironments(): void
    {
        $allowedEnvironments = ['prod', 'dev'];
        $this->cacheService  = $this->createMock(PerformanceCacheService::class);
        $this->cacheService->method('getCachedEnvironments')
            ->willReturn(null);
        $this->cacheService->expects($this->once())
            ->method('cacheEnvironments')
            ->with($allowedEnvironments);

        $this->metricsService->method('getRepository')
            ->willThrowException(new Exception('Database error'));

        $controller = $this->createController($this->cacheService, $allowedEnvironments);

        $result = $this->callGetAvailableEnvironments($controller);

        $this->assertSame($allowedEnvironments, $result);
    }

    public function testGetAvailableEnvironmentsDatabaseExceptionUsesDefaultFallback(): void
    {
        $defaultEnvironments = ['dev', 'test', 'prod'];
        $this->cacheService  = $this->createMock(PerformanceCacheService::class);
        $this->cacheService->method('getCachedEnvironments')
            ->willReturn(null);
        $this->cacheService->expects($this->once())
            ->method('cacheEnvironments')
            ->with($defaultEnvironments);

        $this->metricsService->method('getRepository')
            ->willThrowException(new Exception('Database error'));

        $controller = $this->createController($this->cacheService, []);

        $result = $this->callGetAvailableEnvironments($controller);

        $this->assertSame($defaultEnvironments, $result);
    }

    public function testGetAvailableEnvironmentsWithoutCacheService(): void
    {
        $databaseEnvironments = ['dev', 'test'];
        $this->metricsService->method('getRepository')
            ->willReturn($this->repository);
        $this->repository->method('getDistinctEnvironments')
            ->willReturn($databaseEnvironments);

        $controller = $this->createController(null); // No cache service

        $result = $this->callGetAvailableEnvironments($controller);

        $this->assertSame($databaseEnvironments, $result);
    }

    public function testGetAvailableEnvironmentsWithoutCacheServiceUsesAllowedEnvironments(): void
    {
        $allowedEnvironments = ['prod', 'stage'];
        $this->metricsService->method('getRepository')
            ->willReturn($this->repository);
        $this->repository->method('getDistinctEnvironments')
            ->willReturn([]); // Empty database

        $controller = $this->createController(null, $allowedEnvironments);

        $result = $this->callGetAvailableEnvironments($controller);

        $this->assertSame($allowedEnvironments, $result);
    }

    public function testGetAvailableEnvironmentsWithoutCacheServiceUsesCurrentEnvironment(): void
    {
        $currentEnv = 'test';
        $this->metricsService->method('getRepository')
            ->willReturn($this->repository);
        $this->repository->method('getDistinctEnvironments')
            ->willReturn([]); // Empty database

        $controller = $this->createController(
            null,
            [], // No allowed environments
            true, // Mock getParameter
            $currentEnv,
        );

        $result = $this->callGetAvailableEnvironments($controller);

        $this->assertSame([$currentEnv], $result);
    }

    public function testGetAvailableEnvironmentsGetParameterException(): void
    {
        $defaultEnvironments = ['dev', 'test', 'prod'];
        $this->cacheService  = $this->createMock(PerformanceCacheService::class);
        $this->cacheService->method('getCachedEnvironments')
            ->willReturn(null);
        $this->cacheService->expects($this->once())
            ->method('cacheEnvironments')
            ->with($defaultEnvironments);

        $this->metricsService->method('getRepository')
            ->willReturn($this->repository);
        $this->repository->method('getDistinctEnvironments')
            ->willReturn([]); // Empty database

        $controller = $this->createController(
            $this->cacheService,
            [], // No allowed environments
            true, // mock getParameter
            null,
            true,  // getParameter throws
        );

        $result = $this->callGetAvailableEnvironments($controller);

        $this->assertSame($defaultEnvironments, $result);
    }
}
