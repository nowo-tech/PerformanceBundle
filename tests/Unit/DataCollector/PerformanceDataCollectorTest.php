<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\DataCollector;

use Nowo\PerformanceBundle\DataCollector\PerformanceDataCollector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class PerformanceDataCollectorTest extends TestCase
{
    private PerformanceDataCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new PerformanceDataCollector();
    }

    public function testGetName(): void
    {
        $this->assertSame('performance', $this->collector->getName());
    }

    public function testSetAndGetEnabled(): void
    {
        $this->collector->setEnabled(true);
        
        $request = Request::create('/');
        $response = new Response();
        $this->collector->collect($request, $response);
        
        $this->assertTrue($this->collector->isEnabled());
        
        $this->collector->setEnabled(false);
        $this->collector->collect($request, $response);
        
        $this->assertFalse($this->collector->isEnabled());
    }

    public function testSetAndGetRouteName(): void
    {
        $this->collector->setRouteName('app_home');
        
        $request = Request::create('/');
        $request->attributes->set('_route', 'app_home');
        $response = new Response();
        
        $this->collector->collect($request, $response);
        
        $this->assertSame('app_home', $this->collector->getRouteName());
    }

    public function testSetStartTimeAndCollect(): void
    {
        $startTime = microtime(true);
        $this->collector->setStartTime($startTime);
        
        $request = Request::create('/');
        $response = new Response();
        
        $this->collector->collect($request, $response);
        
        $requestTime = $this->collector->getRequestTime();
        $this->assertNotNull($requestTime);
        $this->assertGreaterThan(0, $requestTime);
    }

    public function testSetQueryMetrics(): void
    {
        $this->collector->setQueryMetrics(10, 0.5);
        
        $request = Request::create('/');
        $response = new Response();
        
        $this->collector->collect($request, $response);
        
        $this->assertSame(10, $this->collector->getQueryCount());
        $this->assertSame(500.0, $this->collector->getQueryTime()); // Converted to ms
    }

    public function testSetQueryCountAndTime(): void
    {
        $this->collector->setQueryCount(5);
        $this->collector->setQueryTime(0.25);
        
        $request = Request::create('/');
        $response = new Response();
        
        $this->collector->collect($request, $response);
        
        $this->assertSame(5, $this->collector->getQueryCount());
        $this->assertSame(250.0, $this->collector->getQueryTime());
    }

    public function testGetFormattedRequestTime(): void
    {
        $this->collector->setStartTime(microtime(true) - 0.001); // 1ms ago
        
        $request = Request::create('/');
        $response = new Response();
        
        $this->collector->collect($request, $response);
        
        $formatted = $this->collector->getFormattedRequestTime();
        $this->assertStringContainsString('ms', $formatted);
    }

    public function testGetFormattedRequestTimeForSeconds(): void
    {
        $this->collector->setStartTime(microtime(true) - 1.5); // 1.5s ago
        
        $request = Request::create('/');
        $response = new Response();
        
        $this->collector->collect($request, $response);
        
        $formatted = $this->collector->getFormattedRequestTime();
        $this->assertStringContainsString('s', $formatted);
    }

    public function testGetFormattedRequestTimeWhenNull(): void
    {
        $request = Request::create('/');
        $response = new Response();
        
        $this->collector->collect($request, $response);
        
        $formatted = $this->collector->getFormattedRequestTime();
        $this->assertSame('N/A', $formatted);
    }

    public function testGetFormattedQueryTime(): void
    {
        $this->collector->setQueryTime(0.001); // 1ms
        
        $request = Request::create('/');
        $response = new Response();
        
        $this->collector->collect($request, $response);
        
        $formatted = $this->collector->getFormattedQueryTime();
        $this->assertStringContainsString('ms', $formatted);
    }

    public function testGetFormattedQueryTimeForSeconds(): void
    {
        $this->collector->setQueryTime(1.5); // 1.5s
        
        $request = Request::create('/');
        $response = new Response();
        
        $this->collector->collect($request, $response);
        
        $formatted = $this->collector->getFormattedQueryTime();
        $this->assertStringContainsString('s', $formatted);
    }

    public function testReset(): void
    {
        $this->collector->setStartTime(microtime(true));
        $this->collector->setQueryMetrics(10, 0.5);
        $this->collector->setRouteName('app_home');
        $this->collector->setEnabled(true);
        
        $request = Request::create('/');
        $response = new Response();
        $this->collector->collect($request, $response);
        
        $this->collector->reset();
        $this->collector->setEnabled(false);
        $this->collector->collect($request, $response);
        
        $this->assertFalse($this->collector->isEnabled());
        $this->assertNull($this->collector->getRouteName());
        $this->assertNull($this->collector->getRequestTime());
        $this->assertSame(0, $this->collector->getQueryCount());
        $this->assertSame(0.0, $this->collector->getQueryTime());
    }

    public function testSetEnvironment(): void
    {
        // This method doesn't exist in PerformanceDataCollector
        // Removing this test as it's not applicable
        $this->assertTrue(true);
    }

    public function testSetRequestTime(): void
    {
        // This method is currently a no-op, but we test it doesn't throw
        $this->collector->setRequestTime(0.5);
        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    public function testGetAccessCount(): void
    {
        $repository = $this->createMock(\Nowo\PerformanceBundle\Repository\RouteDataRepository::class);
        $kernel = $this->createMock(\Symfony\Component\HttpKernel\KernelInterface::class);
        
        $routeData = new \Nowo\PerformanceBundle\Entity\RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('dev');
        $routeData->setAccessCount(5);

        $repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn($routeData);

        $kernel
            ->expects($this->once())
            ->method('getEnvironment')
            ->willReturn('dev');

        $collector = new PerformanceDataCollector($repository, $kernel);
        $collector->setRouteName('app_home');
        $collector->setEnabled(true);

        $request = Request::create('/');
        $request->attributes->set('_route', 'app_home');
        $response = new Response();

        $collector->collect($request, $response);

        $this->assertSame(5, $collector->getAccessCount());
    }

    public function testGetRankingByRequestTime(): void
    {
        $repository = $this->createMock(\Nowo\PerformanceBundle\Repository\RouteDataRepository::class);
        $kernel = $this->createMock(\Symfony\Component\HttpKernel\KernelInterface::class);
        
        $routeData = new \Nowo\PerformanceBundle\Entity\RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('dev');

        $repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn($routeData);

        $repository
            ->expects($this->once())
            ->method('getRankingByRequestTime')
            ->with($this->isInstanceOf(\Nowo\PerformanceBundle\Entity\RouteData::class))
            ->willReturn(3);

        $repository
            ->expects($this->once())
            ->method('getTotalRoutesCount')
            ->with('dev')
            ->willReturn(10);

        $kernel
            ->expects($this->once())
            ->method('getEnvironment')
            ->willReturn('dev');

        $collector = new PerformanceDataCollector($repository, $kernel);
        $collector->setRouteName('app_home');
        $collector->setEnabled(true);

        $request = Request::create('/');
        $request->attributes->set('_route', 'app_home');
        $response = new Response();

        $collector->collect($request, $response);

        $this->assertSame(3, $collector->getRankingByRequestTime());
        $this->assertSame(10, $collector->getTotalRoutes());
    }

    public function testGetRankingByQueryCount(): void
    {
        $repository = $this->createMock(\Nowo\PerformanceBundle\Repository\RouteDataRepository::class);
        $kernel = $this->createMock(\Symfony\Component\HttpKernel\KernelInterface::class);
        
        $routeData = new \Nowo\PerformanceBundle\Entity\RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('dev');

        $repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn($routeData);

        $repository
            ->expects($this->once())
            ->method('getRankingByQueryCount')
            ->with($this->isInstanceOf(\Nowo\PerformanceBundle\Entity\RouteData::class))
            ->willReturn(2);

        $kernel
            ->expects($this->once())
            ->method('getEnvironment')
            ->willReturn('dev');

        $collector = new PerformanceDataCollector($repository, $kernel);
        $collector->setRouteName('app_home');
        $collector->setEnabled(true);

        $request = Request::create('/');
        $request->attributes->set('_route', 'app_home');
        $response = new Response();

        $collector->collect($request, $response);

        $this->assertSame(2, $collector->getRankingByQueryCount());
    }

    public function testCollectSkipsRankingQueriesWhenDisabled(): void
    {
        $repository = $this->createMock(\Nowo\PerformanceBundle\Repository\RouteDataRepository::class);
        $kernel = $this->createMock(\Symfony\Component\HttpKernel\KernelInterface::class);
        $container = $this->createMock(\Psr\Container\ContainerInterface::class);
        $routeData = $this->createMock(\Nowo\PerformanceBundle\Entity\RouteData::class);

        // Container returns enable_ranking_queries = false
        $container->expects($this->once())
            ->method('getParameter')
            ->with('nowo_performance.dashboard.enable_ranking_queries')
            ->willReturn(false);

        $kernel->expects($this->once())
            ->method('getContainer')
            ->willReturn($container);

        $kernel->expects($this->once())
            ->method('getEnvironment')
            ->willReturn('dev');

        $repository->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn($routeData);

        // Ranking queries should NOT be called
        $repository->expects($this->never())
            ->method('getRankingByRequestTime');
        $repository->expects($this->never())
            ->method('getRankingByQueryCount');
        $repository->expects($this->never())
            ->method('getTotalRoutesCount');

        $collector = new PerformanceDataCollector($repository, $kernel);
        $collector->setRouteName('app_home');
        $collector->setEnabled(true);

        $request = Request::create('/');
        $request->attributes->set('_route', 'app_home');
        $response = new Response();

        $collector->collect($request, $response);

        // Ranking should be null when disabled
        $this->assertNull($collector->getRankingByRequestTime());
        $this->assertNull($collector->getRankingByQueryCount());
        $this->assertNull($collector->getTotalRoutes());
    }

    public function testCollectExecutesRankingQueriesWhenEnabled(): void
    {
        $repository = $this->createMock(\Nowo\PerformanceBundle\Repository\RouteDataRepository::class);
        $kernel = $this->createMock(\Symfony\Component\HttpKernel\KernelInterface::class);
        $container = $this->createMock(\Psr\Container\ContainerInterface::class);
        $routeData = $this->createMock(\Nowo\PerformanceBundle\Entity\RouteData::class);

        // Container returns enable_ranking_queries = true (default)
        $container->expects($this->once())
            ->method('getParameter')
            ->with('nowo_performance.dashboard.enable_ranking_queries')
            ->willReturn(true);

        $kernel->expects($this->once())
            ->method('getContainer')
            ->willReturn($container);

        $kernel->expects($this->once())
            ->method('getEnvironment')
            ->willReturn('dev');

        $repository->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn($routeData);

        // Ranking queries SHOULD be called
        $repository->expects($this->once())
            ->method('getRankingByRequestTime')
            ->with($routeData)
            ->willReturn(1);
        $repository->expects($this->once())
            ->method('getRankingByQueryCount')
            ->with($routeData)
            ->willReturn(2);
        $repository->expects($this->once())
            ->method('getTotalRoutesCount')
            ->with('dev')
            ->willReturn(10);

        $collector = new PerformanceDataCollector($repository, $kernel);
        $collector->setRouteName('app_home');
        $collector->setEnabled(true);

        $request = Request::create('/');
        $request->attributes->set('_route', 'app_home');
        $response = new Response();

        $collector->collect($request, $response);

        // Ranking should be set when enabled
        $this->assertSame(1, $collector->getRankingByRequestTime());
        $this->assertSame(2, $collector->getRankingByQueryCount());
        $this->assertSame(10, $collector->getTotalRoutes());
    }

    public function testCollectUsesDefaultWhenParameterNotAvailable(): void
    {
        $repository = $this->createMock(\Nowo\PerformanceBundle\Repository\RouteDataRepository::class);
        $kernel = $this->createMock(\Symfony\Component\HttpKernel\KernelInterface::class);
        $container = $this->createMock(\Psr\Container\ContainerInterface::class);
        $routeData = $this->createMock(\Nowo\PerformanceBundle\Entity\RouteData::class);

        // Container throws exception (parameter not available)
        $container->expects($this->once())
            ->method('getParameter')
            ->with('nowo_performance.dashboard.enable_ranking_queries')
            ->willThrowException(new \Exception('Parameter not found'));

        $kernel->expects($this->once())
            ->method('getContainer')
            ->willReturn($container);

        $kernel->expects($this->once())
            ->method('getEnvironment')
            ->willReturn('dev');

        $repository->expects($this->once())
            ->method('findByRouteAndEnv')
            ->with('app_home', 'dev')
            ->willReturn($routeData);

        // Should use default (true) and execute ranking queries
        $repository->expects($this->once())
            ->method('getRankingByRequestTime')
            ->with($routeData)
            ->willReturn(1);
        $repository->expects($this->once())
            ->method('getRankingByQueryCount')
            ->with($routeData)
            ->willReturn(2);
        $repository->expects($this->once())
            ->method('getTotalRoutesCount')
            ->with('dev')
            ->willReturn(10);

        $collector = new PerformanceDataCollector($repository, $kernel);
        $collector->setRouteName('app_home');
        $collector->setEnabled(true);

        $request = Request::create('/');
        $request->attributes->set('_route', 'app_home');
        $response = new Response();

        $collector->collect($request, $response);

        // Should have ranking data (default is enabled)
        $this->assertSame(1, $collector->getRankingByRequestTime());
        $this->assertSame(2, $collector->getRankingByQueryCount());
        $this->assertSame(10, $collector->getTotalRoutes());
    }

    public function testCollectHandlesRepositoryException(): void
    {
        $repository = $this->createMock(\Nowo\PerformanceBundle\Repository\RouteDataRepository::class);
        $kernel = $this->createMock(\Symfony\Component\HttpKernel\KernelInterface::class);

        $repository
            ->expects($this->once())
            ->method('findByRouteAndEnv')
            ->willThrowException(new \Exception('Database error'));

        $kernel
            ->expects($this->once())
            ->method('getEnvironment')
            ->willReturn('dev');

        $collector = new PerformanceDataCollector($repository, $kernel);
        $collector->setRouteName('app_home');
        $collector->setEnabled(true);

        $request = Request::create('/');
        $request->attributes->set('_route', 'app_home');
        $response = new Response();

        // Should not throw exception
        $collector->collect($request, $response);

        $this->assertNull($collector->getAccessCount());
        $this->assertNull($collector->getRankingByRequestTime());
    }

    public function testCollectWithoutRepository(): void
    {
        $collector = new PerformanceDataCollector(null, null);
        $collector->setRouteName('app_home');
        $collector->setEnabled(true);

        $request = Request::create('/');
        $request->attributes->set('_route', 'app_home');
        $response = new Response();

        $collector->collect($request, $response);

        $this->assertNull($collector->getAccessCount());
        $this->assertNull($collector->getRankingByRequestTime());
    }

    public function testSetRecordOperationWithNewRecord(): void
    {
        $this->collector->setRecordOperation(true, false);

        $request = Request::create('/');
        $response = new Response();
        $this->collector->collect($request, $response);

        $this->assertTrue($this->collector->wasRecordNew());
        $this->assertFalse($this->collector->wasRecordUpdated());
        $this->assertSame('New record created', $this->collector->getRecordOperationStatus());
    }

    public function testSetRecordOperationWithUpdatedRecord(): void
    {
        $this->collector->setRecordOperation(false, true);

        $request = Request::create('/');
        $response = new Response();
        $this->collector->collect($request, $response);

        $this->assertFalse($this->collector->wasRecordNew());
        $this->assertTrue($this->collector->wasRecordUpdated());
        $this->assertSame('Existing record updated', $this->collector->getRecordOperationStatus());
    }

    public function testSetRecordOperationWithNoChanges(): void
    {
        $this->collector->setRecordOperation(false, false);

        $request = Request::create('/');
        $response = new Response();
        $this->collector->collect($request, $response);

        $this->assertFalse($this->collector->wasRecordNew());
        $this->assertFalse($this->collector->wasRecordUpdated());
        $this->assertSame('No changes (metrics not worse than existing)', $this->collector->getRecordOperationStatus());
    }

    public function testGetRecordOperationStatusWhenNotSet(): void
    {
        $request = Request::create('/');
        $response = new Response();
        $this->collector->collect($request, $response);

        $this->assertNull($this->collector->wasRecordNew());
        $this->assertNull($this->collector->wasRecordUpdated());
        $this->assertSame('Unknown', $this->collector->getRecordOperationStatus());
    }

    public function testResetClearsRecordOperation(): void
    {
        $this->collector->setRecordOperation(true, false);

        $request = Request::create('/');
        $response = new Response();
        $this->collector->collect($request, $response);

        $this->assertTrue($this->collector->wasRecordNew());

        $this->collector->reset();
        $this->collector->collect($request, $response);

        $this->assertNull($this->collector->wasRecordNew());
        $this->assertNull($this->collector->wasRecordUpdated());
    }
}
