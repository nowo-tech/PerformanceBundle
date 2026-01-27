<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Controller;

use Nowo\PerformanceBundle\Controller\PerformanceController;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Tests for PerformanceController export methods (CSV and JSON).
 */
final class PerformanceControllerExportTest extends TestCase
{
    private PerformanceMetricsService|MockObject $metricsService;
    private RouteDataRepository|MockObject $repository;

    protected function setUp(): void
    {
        $this->metricsService = $this->createMock(PerformanceMetricsService::class);
        $this->repository = $this->createMock(RouteDataRepository::class);
    }

    /**
     * Helper method to create a controller with default parameters.
     */
    private function createController(
        bool $enabled = true,
        array $requiredRoles = [],
        array $overrides = []
    ): PerformanceController {
        $defaults = [
            'analysisService' => null,
            'template' => 'bootstrap',
            'cacheService' => null,
            'dependencyChecker' => null,
            'tableStatusChecker' => null,
            'enableRecordManagement' => false,
            'enableReviewSystem' => false,
            'eventDispatcher' => null,
            'requestTimeWarning' => 0.5,
            'requestTimeCritical' => 1.0,
            'queryCountWarning' => 20,
            'queryCountCritical' => 50,
            'memoryUsageWarning' => 20.0,
            'memoryUsageCritical' => 50.0,
            'dateTimeFormat' => 'Y-m-d H:i:s',
            'dateFormat' => 'Y-m-d H:i',
            'autoRefreshInterval' => 0,
            'trackStatusCodes' => [200, 404, 500, 503],
            'recordRepository' => null,
            'enableAccessRecords' => false,
            'bundleEnabled' => true,
            'allowedEnvironments' => ['dev', 'test', 'prod'],
            'connectionName' => 'default',
            'trackQueries' => true,
            'trackRequestTime' => true,
            'trackSubRequests' => false,
            'ignoreRoutes' => [],
            'async' => false,
            'samplingRate' => 1.0,
            'enableLogging' => true,
        ];

        $params = array_merge($defaults, $overrides);

        return new PerformanceController(
            $this->metricsService,
            $params['analysisService'],
            $enabled,
            $requiredRoles,
            $params['template'],
            $params['cacheService'],
            $params['dependencyChecker'],
            $params['tableStatusChecker'],
            $params['enableRecordManagement'],
            $params['enableReviewSystem'],
            $params['eventDispatcher'],
            $params['requestTimeWarning'],
            $params['requestTimeCritical'],
            $params['queryCountWarning'],
            $params['queryCountCritical'],
            $params['memoryUsageWarning'],
            $params['memoryUsageCritical'],
            $params['dateTimeFormat'],
            $params['dateFormat'],
            $params['autoRefreshInterval'],
            $params['trackStatusCodes'],
            $params['recordRepository'],
            $params['enableAccessRecords'],
            $params['bundleEnabled'],
            $params['allowedEnvironments'],
            $params['connectionName'],
            $params['trackQueries'],
            $params['trackRequestTime'],
            $params['trackSubRequests'],
            $params['ignoreRoutes'],
            $params['async'],
            $params['samplingRate'],
            $params['enableLogging'],
        );
    }

    // ========== exportCsv() tests ==========

    public function testExportCsvThrowsExceptionWhenDisabled(): void
    {
        $controller = $this->createController(enabled: false);
        $request = new Request();

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Performance dashboard is disabled.');

        $controller->exportCsv($request);
    }

    public function testExportCsvThrowsAccessDeniedWhenUserLacksRequiredRoles(): void
    {
        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null,
                true,
                ['ROLE_ADMIN'],
                'bootstrap',
                null,
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
                ['dev', 'test'],
                'default',
                true,
                true,
                false,
                [],
                false,
                1.0,
                true,
            ])
            ->onlyMethods(['isGranted', 'createAccessDeniedException'])
            ->getMock();

        $request = new Request();

        $controller->expects($this->once())
            ->method('isGranted')
            ->with('ROLE_ADMIN')
            ->willReturn(false);

        $exception = new AccessDeniedException('You do not have permission to export performance data.');
        $controller->expects($this->once())
            ->method('createAccessDeniedException')
            ->with('You do not have permission to export performance data.')
            ->willReturn($exception);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('You do not have permission to export performance data.');

        $controller->exportCsv($request);
    }

    public function testExportCsvReturnsStreamedResponse(): void
    {
        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null,
                true,
                [],
                'bootstrap',
                null,
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
                ['dev', 'test'],
                'default',
                true,
                true,
                false,
                [],
                false,
                1.0,
                true,
            ])
            ->onlyMethods(['getParameter'])
            ->getMock();

        $request = new Request();
        $request->query->set('env', 'dev');

        $route = new RouteData();
        $route->setName('test_route');
        $route->setEnv('dev');
        $route->setRequestTime(0.5);
        $route->setTotalQueries(10);

        $this->metricsService->method('getRepository')->willReturn($this->repository);
        $this->repository->method('findWithFilters')->willReturn([$route]);

        $controller->expects($this->any())
            ->method('getParameter')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'kernel.environment' => 'dev',
                    default => null,
                };
            });

        $response = $controller->exportCsv($request);

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('performance_metrics', $response->headers->get('Content-Disposition'));
    }

    public function testExportCsvHandlesEmptyRoutes(): void
    {
        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null,
                true,
                [],
                'bootstrap',
                null,
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
                ['dev', 'test'],
                'default',
                true,
                true,
                false,
                [],
                false,
                1.0,
                true,
            ])
            ->onlyMethods(['getParameter'])
            ->getMock();

        $request = new Request();
        $request->query->set('env', 'dev');

        $this->metricsService->method('getRepository')->willReturn($this->repository);
        $this->repository->method('findWithFilters')->willReturn([]);

        $controller->expects($this->any())
            ->method('getParameter')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'kernel.environment' => 'dev',
                    default => null,
                };
            });

        $response = $controller->exportCsv($request);

        $this->assertInstanceOf(StreamedResponse::class, $response);
    }

    public function testExportCsvHandlesRepositoryException(): void
    {
        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null,
                true,
                [],
                'bootstrap',
                null,
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
                ['dev', 'test'],
                'default',
                true,
                true,
                false,
                [],
                false,
                1.0,
                true,
            ])
            ->onlyMethods(['getParameter'])
            ->getMock();

        $request = new Request();

        $this->metricsService->method('getRepository')->willReturn($this->repository);
        $this->repository->method('findWithFilters')
            ->willThrowException(new \Exception('Database error'));

        $controller->expects($this->any())
            ->method('getParameter')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'kernel.environment' => 'dev',
                    default => null,
                };
            });

        $response = $controller->exportCsv($request);

        $this->assertInstanceOf(StreamedResponse::class, $response);
    }

    // ========== exportJson() tests ==========

    public function testExportJsonThrowsExceptionWhenDisabled(): void
    {
        $controller = $this->createController(enabled: false);
        $request = new Request();

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Performance dashboard is disabled.');

        $controller->exportJson($request);
    }

    public function testExportJsonThrowsAccessDeniedWhenUserLacksRequiredRoles(): void
    {
        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null,
                true,
                ['ROLE_ADMIN'],
                'bootstrap',
                null,
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
                ['dev', 'test'],
                'default',
                true,
                true,
                false,
                [],
                false,
                1.0,
                true,
            ])
            ->onlyMethods(['isGranted', 'createAccessDeniedException'])
            ->getMock();

        $request = new Request();

        $controller->expects($this->once())
            ->method('isGranted')
            ->with('ROLE_ADMIN')
            ->willReturn(false);

        $exception = new AccessDeniedException('You do not have permission to export performance data.');
        $controller->expects($this->once())
            ->method('createAccessDeniedException')
            ->with('You do not have permission to export performance data.')
            ->willReturn($exception);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('You do not have permission to export performance data.');

        $controller->exportJson($request);
    }

    public function testExportJsonReturnsJsonResponse(): void
    {
        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null,
                true,
                [],
                'bootstrap',
                null,
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
                ['dev', 'test'],
                'default',
                true,
                true,
                false,
                [],
                false,
                1.0,
                true,
            ])
            ->onlyMethods(['getParameter'])
            ->getMock();

        $request = new Request();
        $request->query->set('env', 'dev');

        $route = new RouteData();
        $route->setName('test_route');
        $route->setEnv('dev');
        $route->setRequestTime(0.5);
        $route->setTotalQueries(10);
        $route->setHttpMethod('GET');

        $this->metricsService->method('getRepository')->willReturn($this->repository);
        $this->repository->method('findWithFilters')->willReturn([$route]);

        $controller->expects($this->any())
            ->method('getParameter')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'kernel.environment' => 'dev',
                    default => null,
                };
            });

        $response = $controller->exportJson($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('performance_metrics', $response->headers->get('Content-Disposition'));

        $content = json_decode($response->getContent(), true);
        $this->assertIsArray($content);
        $this->assertArrayHasKey('environment', $content);
        $this->assertArrayHasKey('exported_at', $content);
        $this->assertArrayHasKey('total_records', $content);
        $this->assertArrayHasKey('data', $content);
        $this->assertSame('dev', $content['environment']);
        $this->assertSame(1, $content['total_records']);
        $this->assertCount(1, $content['data']);
    }

    public function testExportJsonHandlesEmptyRoutes(): void
    {
        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null,
                true,
                [],
                'bootstrap',
                null,
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
                ['dev', 'test'],
                'default',
                true,
                true,
                false,
                [],
                false,
                1.0,
                true,
            ])
            ->onlyMethods(['getParameter'])
            ->getMock();

        $request = new Request();
        $request->query->set('env', 'dev');

        $this->metricsService->method('getRepository')->willReturn($this->repository);
        $this->repository->method('findWithFilters')->willReturn([]);

        $controller->expects($this->any())
            ->method('getParameter')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'kernel.environment' => 'dev',
                    default => null,
                };
            });

        $response = $controller->exportJson($request);

        $this->assertInstanceOf(Response::class, $response);
        $content = json_decode($response->getContent(), true);
        $this->assertSame(0, $content['total_records']);
        $this->assertEmpty($content['data']);
    }

    public function testExportJsonHandlesRepositoryException(): void
    {
        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null,
                true,
                [],
                'bootstrap',
                null,
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
                ['dev', 'test'],
                'default',
                true,
                true,
                false,
                [],
                false,
                1.0,
                true,
            ])
            ->onlyMethods(['getParameter'])
            ->getMock();

        $request = new Request();

        $this->metricsService->method('getRepository')->willReturn($this->repository);
        $this->repository->method('findWithFilters')
            ->willThrowException(new \Exception('Database error'));

        $controller->expects($this->any())
            ->method('getParameter')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'kernel.environment' => 'dev',
                    default => null,
                };
            });

        $response = $controller->exportJson($request);

        $this->assertInstanceOf(Response::class, $response);
        $content = json_decode($response->getContent(), true);
        $this->assertSame(0, $content['total_records']);
        $this->assertEmpty($content['data']);
    }

    public function testExportJsonIncludesAllRouteData(): void
    {
        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null,
                true,
                [],
                'bootstrap',
                null,
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
                ['dev', 'test'],
                'default',
                true,
                true,
                false,
                [],
                false,
                1.0,
                true,
            ])
            ->onlyMethods(['getParameter'])
            ->getMock();

        $request = new Request();
        $request->query->set('env', 'dev');

        $route = new RouteData();
        $route->setName('test_route');
        $route->setEnv('dev');
        $route->setHttpMethod('POST');
        $route->setRequestTime(0.75);
        $route->setQueryTime(0.25);
        $route->setTotalQueries(15);
        $route->setMemoryUsage(2097152); // 2MB
        $route->setAccessCount(100);
        $route->setParams(['id' => 123]);

        $this->metricsService->method('getRepository')->willReturn($this->repository);
        $this->repository->method('findWithFilters')->willReturn([$route]);

        $controller->expects($this->any())
            ->method('getParameter')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'kernel.environment' => 'dev',
                    default => null,
                };
            });

        $response = $controller->exportJson($request);
        $content = json_decode($response->getContent(), true);

        $this->assertCount(1, $content['data']);
        $data = $content['data'][0];
        $this->assertSame('test_route', $data['route_name']);
        $this->assertSame('POST', $data['http_method']);
        $this->assertSame('dev', $data['environment']);
        $this->assertSame(0.75, $data['request_time']);
        $this->assertSame(0.25, $data['query_time']);
        $this->assertSame(15, $data['total_queries']);
        $this->assertSame(2097152, $data['memory_usage']);
        $this->assertEqualsWithDelta(2.0, $data['memory_usage_mb'], 0.01);
        $this->assertSame(100, $data['access_count']);
        $this->assertArrayHasKey('params', $data);
        $this->assertSame(['id' => 123], $data['params']);
    }
}
