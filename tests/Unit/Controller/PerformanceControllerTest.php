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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
final class PerformanceControllerTest extends TestCase
{
    private PerformanceMetricsService|MockObject $metricsService;

    protected function setUp(): void
    {
        $this->metricsService = $this->createMock(PerformanceMetricsService::class);
    }

    /**
     * Helper method to create a PerformanceController with default parameters.
     *
     * @param bool $enabled Whether the dashboard is enabled
     * @param array $requiredRoles Required roles
     * @param array<string, mixed> $overrides Override specific parameters
     * @return PerformanceController
     */
    private function createController(bool $enabled = true, array $requiredRoles = [], array $overrides = []): PerformanceController
    {
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
            'allowedEnvironments' => ['dev', 'test'],
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

    public function testIndexThrowsExceptionWhenDisabled(): void
    {
        $controller = $this->createController(enabled: false);

        $request = new Request();

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Performance dashboard is disabled.');

        $controller->index($request);
    }

    public function testIndexAllowsAccessWhenNoRolesRequired(): void
    {
        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null, // analysisService
                true, // enabled
                [], // no roles required
                'bootstrap', // template
                null, // cacheService
                null, // dependencyChecker
                false, // enableRecordManagement
                false, // enableReviewSystem
                null, // eventDispatcher
                0.5, // requestTimeWarning
                1.0, // requestTimeCritical
                20, // queryCountWarning
                50, // queryCountCritical
                20.0, // memoryUsageWarning
                50.0, // memoryUsageCritical
                'Y-m-d H:i:s', // dateTimeFormat
                'Y-m-d H:i', // dateFormat
                0, // autoRefreshInterval
                [200, 404, 500, 503], // trackStatusCodes
                null, // recordRepository
                false, // enableAccessRecords
            ])
            ->onlyMethods(['getParameter', 'render', 'createForm', 'getAvailableEnvironments'])
            ->getMock();

        $request = new Request();
        $request->query->set('env', 'dev');

        $routeData = new RouteData();
        $routeData->setName('test_route');
        $routeData->setEnv('dev');

        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('getDistinctEnvironments')->willReturn(['dev']);
        $repository->method('findWithFilters')->willReturn([$routeData]);

        $this->metricsService->method('getRoutesByEnvironment')->willReturn([$routeData]);
        $this->metricsService->method('getRepository')->willReturn($repository);

        $form = $this->createMock(\Symfony\Component\Form\FormInterface::class);
        $formView = $this->createMock(\Symfony\Component\Form\FormView::class);
        $form->method('createView')->willReturn($formView);
        $form->method('handleRequest')->willReturnSelf();
        $form->method('getData')->willReturn(['env' => 'dev']);

        $controller->expects($this->any())
            ->method('getParameter')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'kernel.environment' => 'dev',
                    default => null,
                };
            });

        $controller->expects($this->once())
            ->method('getAvailableEnvironments')
            ->willReturn(['dev']);

        $controller->expects($this->once())
            ->method('createForm')
            ->willReturn($form);

        $controller->expects($this->once())
            ->method('render')
            ->with(
                '@NowoPerformanceBundle/Performance/index.html.twig',
                $this->callback(function ($vars) {
                    return isset($vars['routes']) && isset($vars['stats']);
                })
            )
            ->willReturn(new Response());

        $result = $controller->index($request);

        $this->assertInstanceOf(Response::class, $result);
    }

    public function testIndexAllowsAccessWhenUserHasRequiredRole(): void
    {
        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null, // analysisService
                true, // enabled
                ['ROLE_ADMIN'], // required role
                'bootstrap', // template
                null, // cacheService
                null, // dependencyChecker
                false, // enableRecordManagement
                false, // enableReviewSystem
                null, // eventDispatcher
                0.5, // requestTimeWarning
                1.0, // requestTimeCritical
                20, // queryCountWarning
                50, // queryCountCritical
                20.0, // memoryUsageWarning
                50.0, // memoryUsageCritical
                'Y-m-d H:i:s', // dateTimeFormat
                'Y-m-d H:i', // dateFormat
                0, // autoRefreshInterval
                [200, 404, 500, 503], // trackStatusCodes
                null, // recordRepository
                false, // enableAccessRecords
            ])
            ->onlyMethods(['isGranted', 'getParameter', 'render', 'createForm', 'getAvailableEnvironments'])
            ->getMock();

        $request = new Request();
        $request->query->set('env', 'dev');

        $routeData = new RouteData();
        $routeData->setName('test_route');
        $routeData->setEnv('dev');

        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('getDistinctEnvironments')->willReturn(['dev']);
        $repository->method('findWithFilters')->willReturn([$routeData]);

        $this->metricsService->method('getRoutesByEnvironment')->willReturn([$routeData]);
        $this->metricsService->method('getRepository')->willReturn($repository);

        $form = $this->createMock(\Symfony\Component\Form\FormInterface::class);
        $formView = $this->createMock(\Symfony\Component\Form\FormView::class);
        $form->method('createView')->willReturn($formView);
        $form->method('handleRequest')->willReturnSelf();
        $form->method('getData')->willReturn(['env' => 'dev']);

        $controller->expects($this->once())
            ->method('isGranted')
            ->with('ROLE_ADMIN')
            ->willReturn(true);

        $controller->expects($this->any())
            ->method('getParameter')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'kernel.environment' => 'dev',
                    default => null,
                };
            });

        $controller->expects($this->once())
            ->method('getAvailableEnvironments')
            ->willReturn(['dev']);

        $controller->expects($this->once())
            ->method('createForm')
            ->willReturn($form);

        $controller->expects($this->once())
            ->method('render')
            ->willReturn(new Response());

        $result = $controller->index($request);

        $this->assertInstanceOf(Response::class, $result);
    }

    public function testIndexAllowsAccessWhenUserHasOneOfMultipleRoles(): void
    {
        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null, // analysisService
                true, // enabled
                ['ROLE_ADMIN', 'ROLE_PERFORMANCE_VIEWER'], // multiple roles
                'bootstrap', // template
                null, // cacheService
                null, // dependencyChecker
                false, // enableRecordManagement
                false, // enableReviewSystem
                null, // eventDispatcher
                0.5, // requestTimeWarning
                1.0, // requestTimeCritical
                20, // queryCountWarning
                50, // queryCountCritical
                20.0, // memoryUsageWarning
                50.0, // memoryUsageCritical
                'Y-m-d H:i:s', // dateTimeFormat
                'Y-m-d H:i', // dateFormat
                0, // autoRefreshInterval
                [200, 404, 500, 503], // trackStatusCodes
                null, // recordRepository
                false, // enableAccessRecords
            ])
            ->onlyMethods(['isGranted', 'getParameter', 'render', 'createForm', 'getAvailableEnvironments'])
            ->getMock();

        $request = new Request();
        $request->query->set('env', 'dev');

        $routeData = new RouteData();
        $routeData->setName('test_route');
        $routeData->setEnv('dev');

        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('getDistinctEnvironments')->willReturn(['dev']);
        $repository->method('findWithFilters')->willReturn([$routeData]);

        $this->metricsService->method('getRoutesByEnvironment')->willReturn([$routeData]);
        $this->metricsService->method('getRepository')->willReturn($repository);

        $form = $this->createMock(\Symfony\Component\Form\FormInterface::class);
        $formView = $this->createMock(\Symfony\Component\Form\FormView::class);
        $form->method('createView')->willReturn($formView);
        $form->method('handleRequest')->willReturnSelf();
        $form->method('getData')->willReturn(['env' => 'dev']);

        // User has ROLE_PERFORMANCE_VIEWER but not ROLE_ADMIN
        $controller->expects($this->exactly(2))
            ->method('isGranted')
            ->willReturnCallback(function ($role) {
                return $role === 'ROLE_PERFORMANCE_VIEWER';
            });

        $controller->expects($this->any())
            ->method('getParameter')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'kernel.environment' => 'dev',
                    default => null,
                };
            });

        $controller->expects($this->once())
            ->method('getAvailableEnvironments')
            ->willReturn(['dev']);

        $controller->expects($this->once())
            ->method('createForm')
            ->willReturn($form);

        $controller->expects($this->once())
            ->method('render')
            ->willReturn(new Response());

        $result = $controller->index($request);

        $this->assertInstanceOf(Response::class, $result);
    }

    public function testIndexThrowsAccessDeniedWhenUserLacksRequiredRoles(): void
    {
        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null, // analysisService
                true, // enabled
                ['ROLE_ADMIN'], // required role
                'bootstrap', // template
                null, // cacheService
                null, // dependencyChecker
                false, // enableRecordManagement
                false, // enableReviewSystem
                null, // eventDispatcher
                0.5, // requestTimeWarning
                1.0, // requestTimeCritical
                20, // queryCountWarning
                50, // queryCountCritical
                20.0, // memoryUsageWarning
                50.0, // memoryUsageCritical
                'Y-m-d H:i:s', // dateTimeFormat
                'Y-m-d H:i', // dateFormat
                0, // autoRefreshInterval
                [200, 404, 500, 503], // trackStatusCodes
                null, // recordRepository
                false, // enableAccessRecords
            ])
            ->onlyMethods(['isGranted', 'createAccessDeniedException'])
            ->getMock();

        $request = new Request();

        $controller->expects($this->once())
            ->method('isGranted')
            ->with('ROLE_ADMIN')
            ->willReturn(false);

        $exception = new AccessDeniedException('You do not have permission to access the performance dashboard.');
        $controller->expects($this->once())
            ->method('createAccessDeniedException')
            ->with('You do not have permission to access the performance dashboard.')
            ->willReturn($exception);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('You do not have permission to access the performance dashboard.');

        $controller->index($request);
    }

    public function testIndexThrowsAccessDeniedWhenUserLacksAllRequiredRoles(): void
    {
        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null, // analysisService
                true, // enabled
                ['ROLE_ADMIN', 'ROLE_PERFORMANCE_VIEWER'], // multiple roles
                'bootstrap', // template
                null, // cacheService
                null, // dependencyChecker
                false, // enableRecordManagement
                false, // enableReviewSystem
                null, // eventDispatcher
                0.5, // requestTimeWarning
                1.0, // requestTimeCritical
                20, // queryCountWarning
                50, // queryCountCritical
                20.0, // memoryUsageWarning
                50.0, // memoryUsageCritical
                'Y-m-d H:i:s', // dateTimeFormat
                'Y-m-d H:i', // dateFormat
                0, // autoRefreshInterval
                [200, 404, 500, 503], // trackStatusCodes
                null, // recordRepository
                false, // enableAccessRecords
            ])
            ->onlyMethods(['isGranted', 'createAccessDeniedException'])
            ->getMock();

        $request = new Request();

        // User has neither role
        $controller->expects($this->exactly(2))
            ->method('isGranted')
            ->willReturn(false);

        $exception = new AccessDeniedException('You do not have permission to access the performance dashboard.');
        $controller->expects($this->once())
            ->method('createAccessDeniedException')
            ->with('You do not have permission to access the performance dashboard.')
            ->willReturn($exception);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('You do not have permission to access the performance dashboard.');

        $controller->index($request);
    }

    public function testIndexFiltersRoutesByName(): void
    {
        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null, // analysisService
                true, // enabled
                [], // no roles required
                'bootstrap', // template
                null, // cacheService
                null, // dependencyChecker
                false, // enableRecordManagement
                false, // enableReviewSystem
                null, // eventDispatcher
                0.5, // requestTimeWarning
                1.0, // requestTimeCritical
                20, // queryCountWarning
                50, // queryCountCritical
                20.0, // memoryUsageWarning
                50.0, // memoryUsageCritical
                'Y-m-d H:i:s', // dateTimeFormat
                'Y-m-d H:i', // dateFormat
                0, // autoRefreshInterval
                [200, 404, 500, 503], // trackStatusCodes
                null, // recordRepository
                false, // enableAccessRecords
            ])
            ->onlyMethods(['getParameter', 'render', 'createForm', 'getAvailableEnvironments'])
            ->getMock();

        $request = new Request();
        $request->query->set('env', 'dev');
        $request->query->set('route', 'test');

        $route1 = new RouteData();
        $route1->setName('test_route');
        $route1->setEnv('dev');

        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('getDistinctEnvironments')->willReturn(['dev']);
        $repository->method('findWithFilters')->willReturn([$route1]);

        $this->metricsService->method('getRoutesByEnvironment')->willReturn([$route1]);
        $this->metricsService->method('getRepository')->willReturn($repository);

        $form = $this->createMock(\Symfony\Component\Form\FormInterface::class);
        $formView = $this->createMock(\Symfony\Component\Form\FormView::class);
        $form->method('createView')->willReturn($formView);
        $form->method('handleRequest')->willReturnSelf();
        $form->method('getData')->willReturn(['env' => 'dev', 'route' => 'test']);

        $controller->expects($this->any())
            ->method('getParameter')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'kernel.environment' => 'dev',
                    default => null,
                };
            });

        $controller->expects($this->once())
            ->method('getAvailableEnvironments')
            ->willReturn(['dev']);

        $controller->expects($this->once())
            ->method('createForm')
            ->willReturn($form);

        $controller->expects($this->once())
            ->method('render')
            ->with(
                '@NowoPerformanceBundle/Performance/index.html.twig',
                $this->callback(function ($vars) {
                    // Should only contain routes matching 'test'
                    return isset($vars['routes']) && count($vars['routes']) === 1;
                })
            )
            ->willReturn(new Response());

        $controller->index($request);
    }

    public function testExportCsvThrowsExceptionWhenDisabled(): void
    {
        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null, // analysisService
                false, // disabled
                [],
                'bootstrap', // template
                null, // cacheService
                null, // dependencyChecker
                false, // enableRecordManagement
                false, // enableReviewSystem
                null, // eventDispatcher
                0.5, // requestTimeWarning
                1.0, // requestTimeCritical
                20, // queryCountWarning
                50, // queryCountCritical
                20.0, // memoryUsageWarning
                50.0, // memoryUsageCritical
                'Y-m-d H:i:s', // dateTimeFormat
                'Y-m-d H:i', // dateFormat
                0, // autoRefreshInterval
                [200, 404, 500, 503], // trackStatusCodes
                null, // recordRepository
                false, // enableAccessRecords
            ])
            ->onlyMethods([])
            ->getMock();

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
                null, // analysisService
                true, // enabled
                ['ROLE_ADMIN'], // required role
                'bootstrap', // template
                null, // cacheService
                null, // dependencyChecker
                false, // enableRecordManagement
                false, // enableReviewSystem
                null, // eventDispatcher
                0.5, // requestTimeWarning
                1.0, // requestTimeCritical
                20, // queryCountWarning
                50, // queryCountCritical
                20.0, // memoryUsageWarning
                50.0, // memoryUsageCritical
                'Y-m-d H:i:s', // dateTimeFormat
                'Y-m-d H:i', // dateFormat
                0, // autoRefreshInterval
                [200, 404, 500, 503], // trackStatusCodes
                null, // recordRepository
                false, // enableAccessRecords
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
                null, // analysisService
                true, // enabled
                [], // no roles required
                'bootstrap', // template
                null, // cacheService
                null, // dependencyChecker
                false, // enableRecordManagement
                false, // enableReviewSystem
                null, // eventDispatcher
                0.5, // requestTimeWarning
                1.0, // requestTimeCritical
                20, // queryCountWarning
                50, // queryCountCritical
                20.0, // memoryUsageWarning
                50.0, // memoryUsageCritical
                'Y-m-d H:i:s', // dateTimeFormat
                'Y-m-d H:i', // dateFormat
                0, // autoRefreshInterval
                [200, 404, 500, 503], // trackStatusCodes
                null, // recordRepository
                false, // enableAccessRecords
            ])
            ->onlyMethods(['getParameter'])
            ->getMock();

        $request = new Request();
        $request->query->set('env', 'dev');

        $routeData = new RouteData();
        $routeData->setName('test_route');
        $routeData->setEnv('dev');
        $routeData->setRequestTime(0.5);
        $routeData->setQueryTime(0.2);
        $routeData->setTotalQueries(10);
        $routeData->setAccessCount(5);
        $routeData->setLastAccessedAt(new \DateTimeImmutable('2024-01-01 12:00:00'));
        $routeData->setCreatedAt(new \DateTimeImmutable('2024-01-01 12:00:00'));
        $routeData->setUpdatedAt(new \DateTimeImmutable('2024-01-01 12:00:00'));

        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('findWithFilters')->willReturn([$routeData]);

        $this->metricsService->method('getRepository')->willReturn($repository);

        $controller->expects($this->any())
            ->method('getParameter')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'kernel.environment' => 'dev',
                    default => null,
                };
            });

        $response = $controller->exportCsv($request);

        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class, $response);
        $this->assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
    }

    public function testExportJsonThrowsExceptionWhenDisabled(): void
    {
        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null, // analysisService
                false, // disabled
                [],
                'bootstrap', // template
                null, // cacheService
                null, // dependencyChecker
                false, // enableRecordManagement
                false, // enableReviewSystem
                null, // eventDispatcher
                0.5, // requestTimeWarning
                1.0, // requestTimeCritical
                20, // queryCountWarning
                50, // queryCountCritical
                20.0, // memoryUsageWarning
                50.0, // memoryUsageCritical
                'Y-m-d H:i:s', // dateTimeFormat
                'Y-m-d H:i', // dateFormat
                0, // autoRefreshInterval
                [200, 404, 500, 503], // trackStatusCodes
                null, // recordRepository
                false, // enableAccessRecords
            ])
            ->onlyMethods([])
            ->getMock();

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
                null, // analysisService
                true, // enabled
                ['ROLE_ADMIN'], // required role
                'bootstrap', // template
                null, // cacheService
                null, // dependencyChecker
                false, // enableRecordManagement
                false, // enableReviewSystem
                null, // eventDispatcher
                0.5, // requestTimeWarning
                1.0, // requestTimeCritical
                20, // queryCountWarning
                50, // queryCountCritical
                20.0, // memoryUsageWarning
                50.0, // memoryUsageCritical
                'Y-m-d H:i:s', // dateTimeFormat
                'Y-m-d H:i', // dateFormat
                0, // autoRefreshInterval
                [200, 404, 500, 503], // trackStatusCodes
                null, // recordRepository
                false, // enableAccessRecords
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
                null, // analysisService
                true, // enabled
                [], // no roles required
                'bootstrap', // template
                null, // cacheService
                null, // dependencyChecker
                false, // enableRecordManagement
                false, // enableReviewSystem
                null, // eventDispatcher
                0.5, // requestTimeWarning
                1.0, // requestTimeCritical
                20, // queryCountWarning
                50, // queryCountCritical
                20.0, // memoryUsageWarning
                50.0, // memoryUsageCritical
                'Y-m-d H:i:s', // dateTimeFormat
                'Y-m-d H:i', // dateFormat
                0, // autoRefreshInterval
                [200, 404, 500, 503], // trackStatusCodes
                null, // recordRepository
                false, // enableAccessRecords
            ])
            ->onlyMethods(['getParameter'])
            ->getMock();

        $request = new Request();
        $request->query->set('env', 'dev');

        $routeData = new RouteData();
        $routeData->setName('test_route');
        $routeData->setEnv('dev');
        $routeData->setRequestTime(0.5);
        $routeData->setQueryTime(0.2);
        $routeData->setTotalQueries(10);
        $routeData->setParams(['id' => 123]);
        $routeData->setAccessCount(5);
        $routeData->setLastAccessedAt(new \DateTimeImmutable('2024-01-01 12:00:00'));
        $routeData->setCreatedAt(new \DateTimeImmutable('2024-01-01 12:00:00'));
        $routeData->setUpdatedAt(new \DateTimeImmutable('2024-01-01 12:00:00'));

        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('findWithFilters')->willReturn([$routeData]);

        $this->metricsService->method('getRepository')->willReturn($repository);

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
        $this->assertSame('application/json; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));

        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('environment', $data);
        $this->assertArrayHasKey('exported_at', $data);
        $this->assertArrayHasKey('total_records', $data);
        $this->assertArrayHasKey('data', $data);
        $this->assertSame('dev', $data['environment']);
        $this->assertSame(1, $data['total_records']);
        $this->assertCount(1, $data['data']);
        $this->assertArrayHasKey('access_count', $data['data'][0]);
        $this->assertArrayHasKey('last_accessed_at', $data['data'][0]);
        $this->assertSame(5, $data['data'][0]['access_count']);
    }

    public function testStatisticsThrowsExceptionWhenDisabled(): void
    {
        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null, // analysisService
                false, // disabled
                [],
                'bootstrap', // template
                null, // cacheService
                null, // dependencyChecker
                false, // enableRecordManagement
                false, // enableReviewSystem
                null, // eventDispatcher
                0.5, // requestTimeWarning
                1.0, // requestTimeCritical
                20, // queryCountWarning
                50, // queryCountCritical
                20.0, // memoryUsageWarning
                50.0, // memoryUsageCritical
                'Y-m-d H:i:s', // dateTimeFormat
                'Y-m-d H:i', // dateFormat
                0, // autoRefreshInterval
                [200, 404, 500, 503], // trackStatusCodes
                null, // recordRepository
                false, // enableAccessRecords
            ])
            ->onlyMethods([])
            ->getMock();

        $request = new Request();

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Performance dashboard is disabled.');

        $controller->statistics($request);
    }

    public function testStatisticsThrowsAccessDeniedWhenUserLacksRequiredRoles(): void
    {
        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null, // analysisService
                true, // enabled
                ['ROLE_ADMIN'], // required role
                'bootstrap', // template
                null, // cacheService
                null, // dependencyChecker
                false, // enableRecordManagement
                false, // enableReviewSystem
                null, // eventDispatcher
                0.5, // requestTimeWarning
                1.0, // requestTimeCritical
                20, // queryCountWarning
                50, // queryCountCritical
                20.0, // memoryUsageWarning
                50.0, // memoryUsageCritical
                'Y-m-d H:i:s', // dateTimeFormat
                'Y-m-d H:i', // dateFormat
                0, // autoRefreshInterval
                [200, 404, 500, 503], // trackStatusCodes
                null, // recordRepository
                false, // enableAccessRecords
            ])
            ->onlyMethods(['isGranted', 'createAccessDeniedException'])
            ->getMock();

        $request = new Request();

        $controller->expects($this->once())
            ->method('isGranted')
            ->with('ROLE_ADMIN')
            ->willReturn(false);

        $exception = new AccessDeniedException('You do not have permission to access the performance statistics.');
        $controller->expects($this->once())
            ->method('createAccessDeniedException')
            ->with('You do not have permission to access the performance statistics.')
            ->willReturn($exception);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('You do not have permission to access the performance statistics.');

        $controller->statistics($request);
    }

    public function testStatisticsReturnsResponse(): void
    {
        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null, // analysisService
                true, // enabled
                [], // no roles required
                'bootstrap', // template
                null, // cacheService
                null, // dependencyChecker
                false, // enableRecordManagement
                false, // enableReviewSystem
                null, // eventDispatcher
                0.5, // requestTimeWarning
                1.0, // requestTimeCritical
                20, // queryCountWarning
                50, // queryCountCritical
                20.0, // memoryUsageWarning
                50.0, // memoryUsageCritical
                'Y-m-d H:i:s', // dateTimeFormat
                'Y-m-d H:i', // dateFormat
                0, // autoRefreshInterval
                [200, 404, 500, 503], // trackStatusCodes
                null, // recordRepository
                false, // enableAccessRecords
            ])
            ->onlyMethods(['getParameter', 'render', 'getAvailableEnvironments'])
            ->getMock();

        $request = new Request();
        $request->query->set('env', 'dev');

        $routeData = new RouteData();
        $routeData->setName('test_route');
        $routeData->setEnv('dev');
        $routeData->setRequestTime(0.5);
        $routeData->setTotalQueries(10);

        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('findAllForStatistics')->willReturn([$routeData]);

        $this->metricsService->method('getRepository')->willReturn($repository);

        $controller->expects($this->any())
            ->method('getParameter')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'kernel.environment' => 'dev',
                    default => null,
                };
            });

        $controller->expects($this->once())
            ->method('getAvailableEnvironments')
            ->willReturn(['dev']);

        $controller->expects($this->once())
            ->method('render')
            ->with(
                '@NowoPerformanceBundle/Performance/statistics.html.twig',
                $this->callback(function ($vars) {
                    return isset($vars['advanced_stats']) 
                        && isset($vars['routes_needing_attention'])
                        && isset($vars['environment'])
                        && isset($vars['environments']);
                })
            )
            ->willReturn(new Response());

        $result = $controller->statistics($request);

        $this->assertInstanceOf(Response::class, $result);
    }

    public function testChartDataThrowsExceptionWhenDisabled(): void
    {
        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null, // analysisService
                false, // disabled
                [],
                'bootstrap', // template
                null, // cacheService
                null, // dependencyChecker
                false, // enableRecordManagement
                false, // enableReviewSystem
                null, // eventDispatcher
                0.5, // requestTimeWarning
                1.0, // requestTimeCritical
                20, // queryCountWarning
                50, // queryCountCritical
                20.0, // memoryUsageWarning
                50.0, // memoryUsageCritical
                'Y-m-d H:i:s', // dateTimeFormat
                'Y-m-d H:i', // dateFormat
                0, // autoRefreshInterval
                [200, 404, 500, 503], // trackStatusCodes
                null, // recordRepository
                false, // enableAccessRecords
            ])
            ->onlyMethods([])
            ->getMock();

        $request = new Request();

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Performance dashboard is disabled.');

        $controller->chartData($request);
    }

    public function testChartDataReturnsJsonResponse(): void
    {
        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null, // analysisService
                true, // enabled
                [], // no roles required
                'bootstrap', // template
                null, // cacheService
                null, // dependencyChecker
                false, // enableRecordManagement
                false, // enableReviewSystem
                null, // eventDispatcher
                0.5, // requestTimeWarning
                1.0, // requestTimeCritical
                20, // queryCountWarning
                50, // queryCountCritical
                20.0, // memoryUsageWarning
                50.0, // memoryUsageCritical
                'Y-m-d H:i:s', // dateTimeFormat
                'Y-m-d H:i', // dateFormat
                0, // autoRefreshInterval
                [200, 404, 500, 503], // trackStatusCodes
                null, // recordRepository
                false, // enableAccessRecords
            ])
            ->onlyMethods(['getParameter'])
            ->getMock();

        $request = new Request();
        $request->query->set('env', 'dev');
        $request->query->set('metric', 'requestTime');
        $request->query->set('days', '7');

        $routeData = new RouteData();
        $routeData->setName('test_route');
        $routeData->setEnv('dev');
        $routeData->setRequestTime(0.5);
        $routeData->setCreatedAt(new \DateTimeImmutable('2024-01-01 12:00:00'));

        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('findWithFilters')->willReturn([$routeData]);

        $this->metricsService->method('getRepository')->willReturn($repository);

        $controller->expects($this->any())
            ->method('getParameter')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'kernel.environment' => 'dev',
                    default => null,
                };
            });

        $response = $controller->chartData($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
        
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('labels', $data);
        $this->assertArrayHasKey('datasets', $data);
    }

    public function testDeleteThrowsExceptionWhenDisabled(): void
    {
        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null, // analysisService
                false, // disabled
                [],
                'bootstrap', // template
                null, // cacheService
                null, // dependencyChecker
                false, // enableRecordManagement
                false, // enableReviewSystem
                null, // eventDispatcher
                0.5, // requestTimeWarning
                1.0, // requestTimeCritical
                20, // queryCountWarning
                50, // queryCountCritical
                20.0, // memoryUsageWarning
                50.0, // memoryUsageCritical
                'Y-m-d H:i:s', // dateTimeFormat
                'Y-m-d H:i', // dateFormat
                0, // autoRefreshInterval
                [200, 404, 500, 503], // trackStatusCodes
                null, // recordRepository
                false, // enableAccessRecords
            ])
            ->onlyMethods([])
            ->getMock();

        $request = new Request();

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Performance dashboard is disabled.');

        $controller->delete(1, $request);
    }

    public function testDeleteThrowsExceptionWhenRecordManagementDisabled(): void
    {
        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null, // analysisService
                true, // enabled
                [], // no roles required
                'bootstrap', // template
                null, // cacheService
                null, // dependencyChecker
                false, // enableRecordManagement
                false, // enableReviewSystem
                null, // eventDispatcher
                0.5, // requestTimeWarning
                1.0, // requestTimeCritical
                20, // queryCountWarning
                50, // queryCountCritical
                20.0, // memoryUsageWarning
                50.0, // memoryUsageCritical
                'Y-m-d H:i:s', // dateTimeFormat
                'Y-m-d H:i', // dateFormat
                0, // autoRefreshInterval
                [200, 404, 500, 503], // trackStatusCodes
                null, // recordRepository
                false, // enableAccessRecords
            ])
            ->onlyMethods(['createAccessDeniedException'])
            ->getMock();

        $request = new Request();

        $exception = new AccessDeniedException('Record management is not enabled.');
        $controller->expects($this->once())
            ->method('createAccessDeniedException')
            ->with('Record management is not enabled.')
            ->willReturn($exception);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Record management is not enabled.');

        $controller->delete(1, $request);
    }

    public function testClearThrowsExceptionWhenDisabled(): void
    {
        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null, // analysisService
                false, // disabled
                [],
                'bootstrap', // template
                null, // cacheService
                null, // dependencyChecker
                false, // enableRecordManagement
                false, // enableReviewSystem
                null, // eventDispatcher
                0.5, // requestTimeWarning
                1.0, // requestTimeCritical
                20, // queryCountWarning
                50, // queryCountCritical
                20.0, // memoryUsageWarning
                50.0, // memoryUsageCritical
                'Y-m-d H:i:s', // dateTimeFormat
                'Y-m-d H:i', // dateFormat
                0, // autoRefreshInterval
                [200, 404, 500, 503], // trackStatusCodes
                null, // recordRepository
                false, // enableAccessRecords
            ])
            ->onlyMethods([])
            ->getMock();

        $request = new Request();
        $request->setMethod('POST');

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Performance dashboard is disabled.');

        $controller->clear($request);
    }

    public function testReviewThrowsExceptionWhenDisabled(): void
    {
        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null, // analysisService
                false, // disabled
                [],
                'bootstrap', // template
                null, // cacheService
                null, // dependencyChecker
                false, // enableRecordManagement
                false, // enableReviewSystem
                null, // eventDispatcher
                0.5, // requestTimeWarning
                1.0, // requestTimeCritical
                20, // queryCountWarning
                50, // queryCountCritical
                20.0, // memoryUsageWarning
                50.0, // memoryUsageCritical
                'Y-m-d H:i:s', // dateTimeFormat
                'Y-m-d H:i', // dateFormat
                0, // autoRefreshInterval
                [200, 404, 500, 503], // trackStatusCodes
                null, // recordRepository
                false, // enableAccessRecords
            ])
            ->onlyMethods([])
            ->getMock();

        $request = new Request();
        $request->setMethod('POST');

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Performance dashboard is disabled.');

        $controller->review(1, $request);
    }

    public function testReviewThrowsExceptionWhenReviewSystemDisabled(): void
    {
        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                true, // enabled
                [], // no roles required
                'bootstrap', // template
                null, // cacheService
                null, // dependencyChecker
                false, // enableRecordManagement
                false, // enableReviewSystem
            ])
            ->onlyMethods(['createAccessDeniedException'])
            ->getMock();

        $request = new Request();
        $request->setMethod('POST');

        $exception = new AccessDeniedException('Review system is not enabled.');
        $controller->expects($this->once())
            ->method('createAccessDeniedException')
            ->with('Review system is not enabled.')
            ->willReturn($exception);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Review system is not enabled.');

        $controller->review(1, $request);
    }

    public function testAccessStatisticsThrowsExceptionWhenDisabled(): void
    {
        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null, // analysisService
                false, // disabled
                [],
                'bootstrap', // template
                null, // cacheService
                null, // dependencyChecker
                false, // enableRecordManagement
                false, // enableReviewSystem
                null, // eventDispatcher
                0.5, // requestTimeWarning
                1.0, // requestTimeCritical
                20, // queryCountWarning
                50, // queryCountCritical
                20.0, // memoryUsageWarning
                50.0, // memoryUsageCritical
                'Y-m-d H:i:s', // dateTimeFormat
                'Y-m-d H:i', // dateFormat
                0, // autoRefreshInterval
                [200, 404, 500, 503], // trackStatusCodes
                null, // recordRepository
                false, // enableAccessRecords
            ])
            ->onlyMethods([])
            ->getMock();

        $request = new Request();

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Temporal access records are disabled.');

        $controller->accessStatistics($request);
    }

    public function testAccessStatisticsThrowsExceptionWhenAccessRecordsDisabled(): void
    {
        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null, // analysisService
                true, // enabled
                [], // no roles required
                'bootstrap', // template
                null, // cacheService
                null, // dependencyChecker
                false, // enableRecordManagement
                false, // enableReviewSystem
                null, // eventDispatcher
                0.5, // requestTimeWarning
                1.0, // requestTimeCritical
                20, // queryCountWarning
                50, // queryCountCritical
                20.0, // memoryUsageWarning
                50.0, // memoryUsageCritical
                'Y-m-d H:i:s', // dateTimeFormat
                'Y-m-d H:i', // dateFormat
                0, // autoRefreshInterval
                [200, 404, 500, 503], // trackStatusCodes
                null, // recordRepository
                false, // enableAccessRecords
            ])
            ->onlyMethods([])
            ->getMock();

        $request = new Request();

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Temporal access records are disabled.');

        $controller->accessStatistics($request);
    }

    public function testAccessStatisticsRendersViewWithData(): void
    {
        $recordRepository = $this->createMock(\Nowo\PerformanceBundle\Repository\RouteDataRecordRepository::class);
        
        $recordRepository->expects($this->once())
            ->method('getStatisticsByHour')
            ->willReturn([
                ['hour' => 10, 'count' => 5, 'avg_response_time' => 0.5, 'status_codes' => [200 => 4, 404 => 1]],
            ]);

        $recordRepository->expects($this->once())
            ->method('getTotalAccessCount')
            ->willReturn(5);

        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null, // analysisService
                true, // enabled
                [], // no roles required
                'bootstrap', // template
                null, // cacheService
                null, // dependencyChecker
                false, // enableRecordManagement
                false, // enableReviewSystem
                null, // eventDispatcher
                0.5, // requestTimeWarning
                1.0, // requestTimeCritical
                20, // queryCountWarning
                50, // queryCountCritical
                20.0, // memoryUsageWarning
                50.0, // memoryUsageCritical
                'Y-m-d H:i:s', // dateTimeFormat
                'Y-m-d H:i', // dateFormat
                0, // autoRefreshInterval
                [200, 404, 500, 503], // trackStatusCodes
                $recordRepository, // recordRepository
                true, // enableAccessRecords
            ])
            ->onlyMethods(['getParameter', 'render', 'getAvailableEnvironments'])
            ->getMock();

        $request = new Request();
        $request->query->set('env', 'dev');

        $controller->expects($this->once())
            ->method('getParameter')
            ->with('kernel.environment')
            ->willReturn('dev');

        $controller->expects($this->once())
            ->method('getAvailableEnvironments')
            ->willReturn(['dev', 'test', 'prod']);

        $controller->expects($this->once())
            ->method('render')
            ->with(
                '@NowoPerformanceBundle/Performance/access_statistics.html.twig',
                $this->callback(function ($vars) {
                    return isset($vars['statistics_by_hour'])
                        && isset($vars['total_access_count'])
                        && isset($vars['environment'])
                        && $vars['environment'] === 'dev';
                })
            )
            ->willReturn(new Response());

        $result = $controller->accessStatistics($request);

        $this->assertInstanceOf(Response::class, $result);
    }

    public function testAccessStatisticsWithDateFilters(): void
    {
        $recordRepository = $this->createMock(\Nowo\PerformanceBundle\Repository\RouteDataRecordRepository::class);
        
        $recordRepository->expects($this->once())
            ->method('getStatisticsByHour')
            ->with(
                'dev',
                $this->isInstanceOf(\DateTimeImmutable::class),
                $this->isInstanceOf(\DateTimeImmutable::class)
            )
            ->willReturn([]);

        $recordRepository->expects($this->once())
            ->method('getTotalAccessCount')
            ->willReturn(0);

        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                true,
                [],
                'bootstrap',
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
                $recordRepository,
                true,
            ])
            ->onlyMethods(['getParameter', 'render', 'getAvailableEnvironments'])
            ->getMock();

        $request = new Request();
        $request->query->set('env', 'dev');
        $request->query->set('start_date', '2024-01-01');
        $request->query->set('end_date', '2024-01-31');

        $controller->method('getParameter')->willReturn('dev');
        $controller->method('getAvailableEnvironments')->willReturn(['dev']);
        $controller->method('render')->willReturn(new Response());

        $result = $controller->accessStatistics($request);

        $this->assertInstanceOf(Response::class, $result);
    }

    public function testAccessStatisticsHandlesRepositoryException(): void
    {
        $recordRepository = $this->createMock(\Nowo\PerformanceBundle\Repository\RouteDataRecordRepository::class);
        
        $recordRepository->expects($this->once())
            ->method('getStatisticsByHour')
            ->willThrowException(new \Exception('Database error'));

        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                true,
                [],
                'bootstrap',
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
                $recordRepository,
                true,
            ])
            ->onlyMethods(['getParameter', 'render', 'getAvailableEnvironments', 'addFlash'])
            ->getMock();

        $request = new Request();

        $controller->method('getParameter')->willReturn('dev');
        $controller->method('getAvailableEnvironments')->willReturn(['dev']);
        $controller->expects($this->once())
            ->method('addFlash')
            ->with('error', $this->stringContains('Error fetching hourly access statistics'));
        $controller->method('render')->willReturn(new Response());

        $result = $controller->accessStatistics($request);

        $this->assertInstanceOf(Response::class, $result);
    }

    public function testDiagnoseThrowsExceptionWhenDisabled(): void
    {
        $controller = $this->createController(enabled: false);

        $request = new Request();

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Performance dashboard is disabled.');

        $controller->diagnose($request);
    }

    public function testDiagnoseThrowsAccessDeniedWhenUserLacksRequiredRoles(): void
    {
        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null, // analysisService
                true, // enabled
                ['ROLE_ADMIN'], // required role
                'bootstrap', // template
                null, // cacheService
                null, // dependencyChecker
                false, // enableRecordManagement
                false, // enableReviewSystem
                null, // eventDispatcher
                0.5, // requestTimeWarning
                1.0, // requestTimeCritical
                20, // queryCountWarning
                50, // queryCountCritical
                20.0, // memoryUsageWarning
                50.0, // memoryUsageCritical
                'Y-m-d H:i:s', // dateTimeFormat
                'Y-m-d H:i', // dateFormat
                0, // autoRefreshInterval
                [200, 404, 500, 503], // trackStatusCodes
                null, // recordRepository
                false, // enableAccessRecords
            ])
            ->onlyMethods(['isGranted', 'createAccessDeniedException'])
            ->getMock();

        $request = new Request();

        $controller->expects($this->once())
            ->method('isGranted')
            ->with('ROLE_ADMIN')
            ->willReturn(false);

        $exception = new AccessDeniedException('You do not have permission to access the diagnostic page.');
        $controller->expects($this->once())
            ->method('createAccessDeniedException')
            ->with('You do not have permission to access the diagnostic page.')
            ->willReturn($exception);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('You do not have permission to access the diagnostic page.');

        $controller->diagnose($request);
    }

    public function testDiagnoseReturnsResponseWithDiagnosticData(): void
    {
        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('count')->willReturn(5);
        
        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('getOneOrNullResult')->willReturn(null);
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $query->method('getSingleScalarResult')->willReturn(0);
        
        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        $entityManager = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $connection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $result = $this->createMock(\Doctrine\DBAL\Result::class);
        $connection->method('executeQuery')->with('SELECT 1')->willReturn($result);
        $connection->method('getDatabase')->willReturn('test_db');
        $driver = $this->createMock(\Doctrine\DBAL\Driver::class);
        $driver->method('getName')->willReturn('pdo_mysql');
        $connection->method('getDriver')->willReturn($driver);
        $entityManager->method('getConnection')->willReturn($connection);

        $this->metricsService->method('getRepository')->willReturn($repository);
        $repository->method('getEntityManager')->willReturn($entityManager);

        $tableStatusChecker = $this->createMock(\Nowo\PerformanceBundle\Service\TableStatusChecker::class);
        $tableStatusChecker->method('tableExists')->willReturn(true);
        $tableStatusChecker->method('tableIsComplete')->willReturn(true);
        $tableStatusChecker->method('getTableName')->willReturn('route_data');
        $tableStatusChecker->method('getMissingColumns')->willReturn([]);

        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null,
                true,
                [],
                'bootstrap',
                null,
                null,
                $tableStatusChecker,
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
            ->onlyMethods(['getParameter', 'render'])
            ->getMock();

        $request = new Request();

        $controller->expects($this->any())
            ->method('getParameter')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'kernel.environment' => 'dev',
                    default => null,
                };
            });

        $controller->expects($this->once())
            ->method('render')
            ->with(
                '@NowoPerformanceBundle/Performance/diagnose.html.twig',
                $this->callback(function ($vars) {
                    return isset($vars['diagnostic'])
                        && isset($vars['diagnostic']['configuration'])
                        && isset($vars['diagnostic']['environment'])
                        && isset($vars['diagnostic']['database_connection'])
                        && isset($vars['diagnostic']['table_status'])
                        && isset($vars['diagnostic']['data_status'])
                        && isset($vars['diagnostic']['route_tracking'])
                        && isset($vars['issues'])
                        && isset($vars['warnings'])
                        && isset($vars['suggestions']);
                })
            )
            ->willReturn(new Response());

        $result = $controller->diagnose($request);

        $this->assertInstanceOf(Response::class, $result);
    }

    public function testDiagnoseHandlesDatabaseConnectionError(): void
    {
        $repository = $this->createMock(RouteDataRepository::class);
        $entityManager = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $connection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $connection->method('executeQuery')
            ->with('SELECT 1')
            ->willThrowException(new \Exception('Connection failed'));
        $entityManager->method('getConnection')->willReturn($connection);

        $this->metricsService->method('getRepository')->willReturn($repository);
        $repository->method('getEntityManager')->willReturn($entityManager);

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
            ->onlyMethods(['getParameter', 'render'])
            ->getMock();

        $request = new Request();

        $controller->expects($this->any())
            ->method('getParameter')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'kernel.environment' => 'dev',
                    default => null,
                };
            });

        $controller->expects($this->once())
            ->method('render')
            ->with(
                '@NowoPerformanceBundle/Performance/diagnose.html.twig',
                $this->callback(function ($vars) {
                    return isset($vars['diagnostic']['database_connection'])
                        && $vars['diagnostic']['database_connection']['connected'] === false
                        && isset($vars['diagnostic']['database_connection']['error']);
                })
            )
            ->willReturn(new Response());

        $result = $controller->diagnose($request);

        $this->assertInstanceOf(Response::class, $result);
    }

    public function testClearReturnsRedirectWithValidCsrfToken(): void
    {
        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('deleteAll')->with('dev')->willReturn(5);

        $this->metricsService->method('getRepository')->willReturn($repository);

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
            ->onlyMethods(['isCsrfTokenValid', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $request = new Request();
        $request->setMethod('POST');
        $request->request->set('_token', 'valid_token');
        $request->request->set('env', 'dev');

        $controller->expects($this->once())
            ->method('isCsrfTokenValid')
            ->with('clear_performance_data', 'valid_token')
            ->willReturn(true);

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('success', $this->stringContains('Successfully deleted 5 performance record(s)'));

        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->with('nowo_performance.index')
            ->willReturn(new RedirectResponse('/performance'));

        $result = $controller->clear($request);

        $this->assertInstanceOf(RedirectResponse::class, $result);
    }

    public function testClearHandlesInvalidCsrfToken(): void
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
            ->onlyMethods(['isCsrfTokenValid', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $request = new Request();
        $request->setMethod('POST');
        $request->request->set('_token', 'invalid_token');

        $controller->expects($this->once())
            ->method('isCsrfTokenValid')
            ->with('clear_performance_data', 'invalid_token')
            ->willReturn(false);

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('error', 'Invalid security token. Please try again.');

        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->with('nowo_performance.index')
            ->willReturn(new RedirectResponse('/performance'));

        $result = $controller->clear($request);

        $this->assertInstanceOf(RedirectResponse::class, $result);
    }

    public function testClearWithEventDispatcher(): void
    {
        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('deleteAll')->with('dev')->willReturn(3);

        $this->metricsService->method('getRepository')->willReturn($repository);

        $eventDispatcher = $this->createMock(\Symfony\Contracts\EventDispatcher\EventDispatcherInterface::class);
        $eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($event) {
                return $event;
            });

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
                $eventDispatcher,
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
            ->onlyMethods(['isCsrfTokenValid', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $request = new Request();
        $request->setMethod('POST');
        $request->request->set('_token', 'valid_token');
        $request->request->set('env', 'dev');

        $controller->expects($this->once())
            ->method('isCsrfTokenValid')
            ->willReturn(true);

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('success', $this->stringContains('Successfully deleted 3 performance record(s)'));

        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->willReturn(new RedirectResponse('/performance'));

        $result = $controller->clear($request);

        $this->assertInstanceOf(RedirectResponse::class, $result);
    }

    public function testDeleteReturnsRedirectWithValidCsrfToken(): void
    {
        $routeData = new RouteData();
        $routeData->setId(1);
        $routeData->setName('test_route');
        $routeData->setEnv('dev');

        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('find')->with(1)->willReturn($routeData);
        $repository->method('deleteById')->with(1)->willReturn(true);

        $this->metricsService->method('getRepository')->willReturn($repository);

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
                true, // enableRecordManagement
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
            ->onlyMethods(['isCsrfTokenValid', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $request = new Request();
        $request->setMethod('POST');
        $request->request->set('_token', 'valid_token');

        $controller->expects($this->once())
            ->method('isCsrfTokenValid')
            ->with('delete_performance_record_1', 'valid_token')
            ->willReturn(true);

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('success', 'Performance record deleted successfully.');

        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->with('nowo_performance.index')
            ->willReturn(new RedirectResponse('/performance'));

        $result = $controller->delete(1, $request);

        $this->assertInstanceOf(RedirectResponse::class, $result);
    }

    public function testDeleteHandlesRecordNotFound(): void
    {
        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('find')->with(999)->willReturn(null);

        $this->metricsService->method('getRepository')->willReturn($repository);

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
                true, // enableRecordManagement
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
            ->onlyMethods(['isCsrfTokenValid', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $request = new Request();
        $request->setMethod('POST');
        $request->request->set('_token', 'valid_token');

        $controller->expects($this->once())
            ->method('isCsrfTokenValid')
            ->willReturn(true);

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('error', 'Record with ID 999 not found.');

        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->with('nowo_performance.index')
            ->willReturn(new RedirectResponse('/performance'));

        $result = $controller->delete(999, $request);

        $this->assertInstanceOf(RedirectResponse::class, $result);
    }

    public function testReviewReturnsRedirectWithValidFormData(): void
    {
        $routeData = new RouteData();
        $routeData->setId(1);
        $routeData->setName('test_route');
        $routeData->setEnv('dev');

        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('find')->with(1)->willReturn($routeData);
        $repository->method('markAsReviewed')->with(1, true, false, null)->willReturn(true);

        $this->metricsService->method('getRepository')->willReturn($repository);

        $form = $this->createMock(\Symfony\Component\Form\FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn([
            'queries_improved' => '1',
            'time_improved' => '0',
        ]);

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
                true, // enableReviewSystem
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
            ->onlyMethods(['createForm', 'addFlash', 'redirectToRoute', 'getUser'])
            ->getMock();

        $request = new Request();
        $request->setMethod('POST');

        $controller->expects($this->once())
            ->method('createForm')
            ->willReturn($form);

        $controller->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('success', 'Performance record marked as reviewed successfully.');

        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->with('nowo_performance.index')
            ->willReturn(new RedirectResponse('/performance'));

        $result = $controller->review(1, $request);

        $this->assertInstanceOf(RedirectResponse::class, $result);
    }

    public function testReviewHandlesInvalidForm(): void
    {
        $routeData = new RouteData();
        $routeData->setId(1);
        $routeData->setName('test_route');
        $routeData->setEnv('dev');

        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('find')->with(1)->willReturn($routeData);

        $this->metricsService->method('getRepository')->willReturn($repository);

        $form = $this->createMock(\Symfony\Component\Form\FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(false);

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
                true, // enableReviewSystem
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
            ->onlyMethods(['createForm', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $request = new Request();
        $request->setMethod('POST');

        $controller->expects($this->once())
            ->method('createForm')
            ->willReturn($form);

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('error', 'Invalid form data. Please try again.');

        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->with('nowo_performance.index')
            ->willReturn(new RedirectResponse('/performance'));

        $result = $controller->review(1, $request);

        $this->assertInstanceOf(RedirectResponse::class, $result);
    }

    public function testChartDataWithDifferentMetrics(): void
    {
        $routeData = new RouteData();
        $routeData->setName('test_route');
        $routeData->setEnv('dev');
        $routeData->setRequestTime(0.5);
        $routeData->setQueryTime(0.2);
        $routeData->setTotalQueries(10);
        $routeData->setCreatedAt(new \DateTimeImmutable('2024-01-01 12:00:00'));

        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('findWithFilters')->willReturn([$routeData]);

        $this->metricsService->method('getRepository')->willReturn($repository);

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

        $metrics = ['requestTime', 'queryTime', 'totalQueries', 'memoryUsage'];
        
        foreach ($metrics as $metric) {
            $request = new Request();
            $request->query->set('env', 'dev');
            $request->query->set('metric', $metric);
            $request->query->set('days', '7');

            $controller->expects($this->any())
                ->method('getParameter')
                ->willReturnCallback(function ($key) {
                    return match ($key) {
                        'kernel.environment' => 'dev',
                        default => null,
                    };
                });

            $response = $controller->chartData($request);

            $this->assertInstanceOf(Response::class, $response);
            $this->assertSame('application/json', $response->headers->get('Content-Type'));
            
            $data = json_decode($response->getContent(), true);
            $this->assertIsArray($data);
            $this->assertArrayHasKey('labels', $data);
            $this->assertArrayHasKey('datasets', $data);
        }
    }

    public function testChartDataWithNoData(): void
    {
        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('findWithFilters')->willReturn([]);

        $this->metricsService->method('getRepository')->willReturn($repository);

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
        $request->query->set('metric', 'requestTime');
        $request->query->set('days', '7');

        $controller->expects($this->any())
            ->method('getParameter')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'kernel.environment' => 'dev',
                    default => null,
                };
            });

        $response = $controller->chartData($request);

        $this->assertInstanceOf(Response::class, $response);
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertEmpty($data['labels']);
        $this->assertEmpty($data['datasets'][0]['data']);
    }

    public function testDiagnoseWithTableStatusChecker(): void
    {
        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('count')->willReturn(0);
        $repository->method('createQueryBuilder')->willReturn(
            $this->createMock(\Doctrine\ORM\QueryBuilder::class)
        );

        $entityManager = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $connection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $result = $this->createMock(\Doctrine\DBAL\Result::class);
        $connection->method('executeQuery')->with('SELECT 1')->willReturn($result);
        $connection->method('getDatabase')->willReturn('test_db');
        $driver = $this->createMock(\Doctrine\DBAL\Driver::class);
        $driver->method('getName')->willReturn('pdo_mysql');
        $connection->method('getDriver')->willReturn($driver);
        $entityManager->method('getConnection')->willReturn($connection);

        $this->metricsService->method('getRepository')->willReturn($repository);
        $repository->method('getEntityManager')->willReturn($entityManager);

        $tableStatusChecker = $this->createMock(\Nowo\PerformanceBundle\Service\TableStatusChecker::class);
        $tableStatusChecker->method('tableExists')->willReturn(false);
        $tableStatusChecker->method('tableIsComplete')->willReturn(false);
        $tableStatusChecker->method('getTableName')->willReturn('route_data');
        $tableStatusChecker->method('getMissingColumns')->willReturn(['column1', 'column2']);

        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null,
                true,
                [],
                'bootstrap',
                null,
                null,
                $tableStatusChecker,
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
            ->onlyMethods(['getParameter', 'render'])
            ->getMock();

        $request = new Request();

        $controller->expects($this->any())
            ->method('getParameter')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'kernel.environment' => 'dev',
                    default => null,
                };
            });

        $controller->expects($this->once())
            ->method('render')
            ->with(
                '@NowoPerformanceBundle/Performance/diagnose.html.twig',
                $this->callback(function ($vars) {
                    return isset($vars['diagnostic']['table_status'])
                        && $vars['diagnostic']['table_status']['main_table_exists'] === false
                        && isset($vars['diagnostic']['table_status']['missing_columns'])
                        && count($vars['diagnostic']['table_status']['missing_columns']) === 2;
                })
            )
            ->willReturn(new Response());

        $result = $controller->diagnose($request);

        $this->assertInstanceOf(Response::class, $result);
    }

    public function testDiagnoseWithAccessRecordsEnabled(): void
    {
        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('count')->willReturn(0);

        $entityManager = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $connection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $result = $this->createMock(\Doctrine\DBAL\Result::class);
        $connection->method('executeQuery')->with('SELECT 1')->willReturn($result);
        $connection->method('getDatabase')->willReturn('test_db');
        $driver = $this->createMock(\Doctrine\DBAL\Driver::class);
        $driver->method('getName')->willReturn('pdo_mysql');
        $connection->method('getDriver')->willReturn($driver);
        
        $schemaManager = $this->createMock(\Doctrine\DBAL\Schema\AbstractSchemaManager::class);
        $schemaManager->method('tablesExist')->willReturn(true);
        $connection->method('createSchemaManager')->willReturn($schemaManager);
        
        $entityManager->method('getConnection')->willReturn($connection);
        $metadataFactory = $this->createMock(\Doctrine\ORM\Mapping\ClassMetadataFactory::class);
        $metadata = $this->createMock(\Doctrine\ORM\Mapping\ClassMetadata::class);
        $metadata->table = ['name' => 'route_data_record'];
        $metadataFactory->method('getMetadataFor')->willReturn($metadata);
        $entityManager->method('getMetadataFactory')->willReturn($metadataFactory);

        $this->metricsService->method('getRepository')->willReturn($repository);
        $repository->method('getEntityManager')->willReturn($entityManager);

        $recordRepository = $this->createMock(\Nowo\PerformanceBundle\Repository\RouteDataRecordRepository::class);
        $recordRepository->method('getEntityManager')->willReturn($entityManager);

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
                $recordRepository,
                true, // enableAccessRecords
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
            ->onlyMethods(['getParameter', 'render'])
            ->getMock();

        $request = new Request();

        $controller->expects($this->any())
            ->method('getParameter')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'kernel.environment' => 'dev',
                    default => null,
                };
            });

        $controller->expects($this->once())
            ->method('render')
            ->with(
                '@NowoPerformanceBundle/Performance/diagnose.html.twig',
                $this->callback(function ($vars) {
                    return isset($vars['diagnostic']['table_status'])
                        && isset($vars['diagnostic']['table_status']['records_table_exists'])
                        && $vars['diagnostic']['table_status']['records_table_exists'] === true;
                })
            )
            ->willReturn(new Response());

        $result = $controller->diagnose($request);

        $this->assertInstanceOf(Response::class, $result);
    }

    public function testIndexWithDependencyChecker(): void
    {
        $dependencyChecker = $this->createMock(\Nowo\PerformanceBundle\Service\DependencyChecker::class);
        $dependencyChecker->method('getMissingDependencies')->willReturn([
            [
                'feature' => 'Messenger',
                'message' => 'Async processing requires Symfony Messenger',
                'install_command' => 'composer require symfony/messenger',
            ],
        ]);

        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null,
                true,
                [],
                'bootstrap',
                null,
                $dependencyChecker,
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
            ->onlyMethods(['getParameter', 'render', 'createForm', 'getAvailableEnvironments'])
            ->getMock();

        $request = new Request();
        $request->query->set('env', 'dev');

        $routeData = new RouteData();
        $routeData->setName('test_route');
        $routeData->setEnv('dev');

        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('getDistinctEnvironments')->willReturn(['dev']);
        $repository->method('findWithFilters')->willReturn([$routeData]);

        $this->metricsService->method('getRoutesByEnvironment')->willReturn([$routeData]);
        $this->metricsService->method('getRepository')->willReturn($repository);

        $form = $this->createMock(\Symfony\Component\Form\FormInterface::class);
        $formView = $this->createMock(\Symfony\Component\Form\FormView::class);
        $form->method('createView')->willReturn($formView);
        $form->method('handleRequest')->willReturnSelf();
        $form->method('getData')->willReturn(['env' => 'dev']);

        $controller->expects($this->any())
            ->method('getParameter')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'kernel.environment' => 'dev',
                    default => null,
                };
            });

        $controller->expects($this->once())
            ->method('getAvailableEnvironments')
            ->willReturn(['dev']);

        $controller->expects($this->once())
            ->method('createForm')
            ->willReturn($form);

        $controller->expects($this->once())
            ->method('render')
            ->with(
                '@NowoPerformanceBundle/Performance/index.html.twig',
                $this->callback(function ($vars) {
                    return isset($vars['missingDependencies'])
                        && count($vars['missingDependencies']) === 1;
                })
            )
            ->willReturn(new Response());

        $result = $controller->index($request);

        $this->assertInstanceOf(Response::class, $result);
    }

    public function testStatisticsWithAnalysisService(): void
    {
        $analysisService = $this->createMock(\Nowo\PerformanceBundle\Service\PerformanceAnalysisService::class);
        $analysisService->method('analyzeCorrelations')->willReturn([
            'request_time_vs_query_time' => ['coefficient' => 0.85, 'interpretation' => 'strong'],
        ]);
        $analysisService->method('analyzeEfficiency')->willReturn([]);
        $analysisService->method('generateRecommendations')->willReturn([]);
        $analysisService->method('analyzeTrafficDistribution')->willReturn([]);

        $routeData = new RouteData();
        $routeData->setName('test_route');
        $routeData->setEnv('dev');
        $routeData->setRequestTime(0.5);
        $routeData->setTotalQueries(10);

        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('findAllForStatistics')->willReturn([$routeData]);

        $this->metricsService->method('getRepository')->willReturn($repository);

        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                $analysisService,
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
            ->onlyMethods(['getParameter', 'render', 'getAvailableEnvironments'])
            ->getMock();

        $request = new Request();
        $request->query->set('env', 'dev');

        $controller->expects($this->any())
            ->method('getParameter')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'kernel.environment' => 'dev',
                    default => null,
                };
            });

        $controller->expects($this->once())
            ->method('getAvailableEnvironments')
            ->willReturn(['dev']);

        $controller->expects($this->once())
            ->method('render')
            ->with(
                '@NowoPerformanceBundle/Performance/statistics.html.twig',
                $this->callback(function ($vars) {
                    return isset($vars['correlations'])
                        && isset($vars['efficiency'])
                        && isset($vars['recommendations'])
                        && isset($vars['traffic_distribution']);
                })
            )
            ->willReturn(new Response());

        $result = $controller->statistics($request);

        $this->assertInstanceOf(Response::class, $result);
    }

    public function testStatisticsHandlesRepositoryException(): void
    {
        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('findAllForStatistics')
            ->willThrowException(new \Exception('Database error'));

        $this->metricsService->method('getRepository')->willReturn($repository);

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
            ->onlyMethods(['getParameter', 'render', 'getAvailableEnvironments'])
            ->getMock();

        $request = new Request();
        $request->query->set('env', 'dev');

        $controller->expects($this->any())
            ->method('getParameter')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'kernel.environment' => 'dev',
                    default => null,
                };
            });

        $controller->expects($this->once())
            ->method('getAvailableEnvironments')
            ->willReturn(['dev']);

        $controller->expects($this->once())
            ->method('render')
            ->with(
                '@NowoPerformanceBundle/Performance/statistics.html.twig',
                $this->callback(function ($vars) {
                    return isset($vars['routes']) && $vars['routes'] === [];
                })
            )
            ->willReturn(new Response());

        $result = $controller->statistics($request);

        $this->assertInstanceOf(Response::class, $result);
    }

    public function testExportCsvWithFilters(): void
    {
        $routeData = new RouteData();
        $routeData->setName('test_route');
        $routeData->setEnv('dev');
        $routeData->setRequestTime(0.5);
        $routeData->setQueryTime(0.2);
        $routeData->setTotalQueries(10);
        $routeData->setAccessCount(5);
        $routeData->setLastAccessedAt(new \DateTimeImmutable('2024-01-01 12:00:00'));
        $routeData->setCreatedAt(new \DateTimeImmutable('2024-01-01 12:00:00'));
        $routeData->setUpdatedAt(new \DateTimeImmutable('2024-01-01 12:00:00'));

        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('findWithFilters')->willReturn([$routeData]);

        $this->metricsService->method('getRepository')->willReturn($repository);

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
        $request->query->set('route', 'test');
        $request->query->set('sort', 'requestTime');
        $request->query->set('order', 'desc');

        $controller->expects($this->any())
            ->method('getParameter')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'kernel.environment' => 'dev',
                    default => null,
                };
            });

        $response = $controller->exportCsv($request);

        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class, $response);
        $this->assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
    }

    public function testExportCsvWithNoData(): void
    {
        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('findWithFilters')->willReturn([]);

        $this->metricsService->method('getRepository')->willReturn($repository);

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

        $controller->expects($this->any())
            ->method('getParameter')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'kernel.environment' => 'dev',
                    default => null,
                };
            });

        $response = $controller->exportCsv($request);

        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class, $response);
    }

    public function testExportJsonWithFilters(): void
    {
        $routeData = new RouteData();
        $routeData->setName('test_route');
        $routeData->setEnv('dev');
        $routeData->setRequestTime(0.5);
        $routeData->setQueryTime(0.2);
        $routeData->setTotalQueries(10);
        $routeData->setParams(['id' => 123]);
        $routeData->setAccessCount(5);
        $routeData->setLastAccessedAt(new \DateTimeImmutable('2024-01-01 12:00:00'));
        $routeData->setCreatedAt(new \DateTimeImmutable('2024-01-01 12:00:00'));
        $routeData->setUpdatedAt(new \DateTimeImmutable('2024-01-01 12:00:00'));

        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('findWithFilters')->willReturn([$routeData]);

        $this->metricsService->method('getRepository')->willReturn($repository);

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
        $request->query->set('route', 'test');
        $request->query->set('sort', 'requestTime');

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
        $this->assertSame('application/json; charset=UTF-8', $response->headers->get('Content-Type'));
        
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertSame('dev', $data['environment']);
        $this->assertSame(1, $data['total_records']);
    }

    public function testClearWithCacheService(): void
    {
        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('deleteAll')->with('dev')->willReturn(3);

        $this->metricsService->method('getRepository')->willReturn($repository);

        $cacheService = $this->createMock(\Nowo\PerformanceBundle\Service\PerformanceCacheService::class);
        $cacheService->expects($this->once())
            ->method('clearStatistics')
            ->with('dev');

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
            ->onlyMethods(['isCsrfTokenValid', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $request = new Request();
        $request->setMethod('POST');
        $request->request->set('_token', 'valid_token');
        $request->request->set('env', 'dev');

        $controller->expects($this->once())
            ->method('isCsrfTokenValid')
            ->willReturn(true);

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('success', $this->stringContains('Successfully deleted 3 performance record(s)'));

        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->willReturn(new RedirectResponse('/performance'));

        $result = $controller->clear($request);

        $this->assertInstanceOf(RedirectResponse::class, $result);
    }

    public function testClearWithEventPreventingClearing(): void
    {
        $repository = $this->createMock(RouteDataRepository::class);
        $this->metricsService->method('getRepository')->willReturn($repository);

        $eventDispatcher = $this->createMock(\Symfony\Contracts\EventDispatcher\EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($event) {
                if ($event instanceof \Nowo\PerformanceBundle\Event\BeforeRecordsClearedEvent) {
                    $event->preventClearing();
                }
                return $event;
            });

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
                $eventDispatcher,
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
            ->onlyMethods(['isCsrfTokenValid', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $request = new Request();
        $request->setMethod('POST');
        $request->request->set('_token', 'valid_token');
        $request->request->set('env', 'dev');

        $controller->expects($this->once())
            ->method('isCsrfTokenValid')
            ->willReturn(true);

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('warning', 'Clearing was prevented by an event listener.');

        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->willReturn(new RedirectResponse('/performance'));

        $result = $controller->clear($request);

        $this->assertInstanceOf(RedirectResponse::class, $result);
    }

    public function testDeleteWithEventPreventingDeletion(): void
    {
        $routeData = new RouteData();
        $routeData->setId(1);
        $routeData->setName('test_route');
        $routeData->setEnv('dev');

        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('find')->with(1)->willReturn($routeData);

        $this->metricsService->method('getRepository')->willReturn($repository);

        $eventDispatcher = $this->createMock(\Symfony\Contracts\EventDispatcher\EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($event) {
                if ($event instanceof \Nowo\PerformanceBundle\Event\BeforeRecordDeletedEvent) {
                    $event->preventDeletion();
                }
                return $event;
            });

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
                true, // enableRecordManagement
                false,
                $eventDispatcher,
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
            ->onlyMethods(['isCsrfTokenValid', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $request = new Request();
        $request->setMethod('POST');
        $request->request->set('_token', 'valid_token');

        $controller->expects($this->once())
            ->method('isCsrfTokenValid')
            ->willReturn(true);

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('warning', 'Deletion was prevented by an event listener.');

        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->willReturn(new RedirectResponse('/performance'));

        $result = $controller->delete(1, $request);

        $this->assertInstanceOf(RedirectResponse::class, $result);
    }

    public function testDeleteWithCacheService(): void
    {
        $routeData = new RouteData();
        $routeData->setId(1);
        $routeData->setName('test_route');
        $routeData->setEnv('dev');

        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('find')->with(1)->willReturn($routeData);
        $repository->method('deleteById')->with(1)->willReturn(true);

        $this->metricsService->method('getRepository')->willReturn($repository);

        $cacheService = $this->createMock(\Nowo\PerformanceBundle\Service\PerformanceCacheService::class);
        $cacheService->expects($this->once())
            ->method('clearStatistics')
            ->with('dev');
        $cacheService->expects($this->once())
            ->method('clearEnvironments');

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
                true, // enableRecordManagement
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
            ->onlyMethods(['isCsrfTokenValid', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $request = new Request();
        $request->setMethod('POST');
        $request->request->set('_token', 'valid_token');

        $controller->expects($this->once())
            ->method('isCsrfTokenValid')
            ->willReturn(true);

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('success', 'Performance record deleted successfully.');

        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->willReturn(new RedirectResponse('/performance'));

        $result = $controller->delete(1, $request);

        $this->assertInstanceOf(RedirectResponse::class, $result);
    }

    public function testReviewWithEventPreventingReview(): void
    {
        $routeData = new RouteData();
        $routeData->setId(1);
        $routeData->setName('test_route');
        $routeData->setEnv('dev');

        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('find')->with(1)->willReturn($routeData);

        $this->metricsService->method('getRepository')->willReturn($repository);

        $form = $this->createMock(\Symfony\Component\Form\FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn([
            'queries_improved' => '1',
            'time_improved' => '0',
        ]);

        $eventDispatcher = $this->createMock(\Symfony\Contracts\EventDispatcher\EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($event) {
                if ($event instanceof \Nowo\PerformanceBundle\Event\BeforeRecordReviewedEvent) {
                    $event->preventReview();
                }
                return $event;
            });

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
                true, // enableReviewSystem
                $eventDispatcher,
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
            ->onlyMethods(['createForm', 'addFlash', 'redirectToRoute', 'getUser'])
            ->getMock();

        $request = new Request();
        $request->setMethod('POST');

        $controller->expects($this->once())
            ->method('createForm')
            ->willReturn($form);

        $controller->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('warning', 'Review was prevented by an event listener.');

        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->willReturn(new RedirectResponse('/performance'));

        $result = $controller->review(1, $request);

        $this->assertInstanceOf(RedirectResponse::class, $result);
    }

    public function testReviewWithDifferentFormValues(): void
    {
        $routeData = new RouteData();
        $routeData->setId(1);
        $routeData->setName('test_route');
        $routeData->setEnv('dev');

        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('find')->with(1)->willReturn($routeData);
        $repository->method('markAsReviewed')->with(1, null, null, null)->willReturn(true);

        $this->metricsService->method('getRepository')->willReturn($repository);

        $form = $this->createMock(\Symfony\Component\Form\FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn([
            'queries_improved' => '',
            'time_improved' => '',
        ]);

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
                true, // enableReviewSystem
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
            ->onlyMethods(['createForm', 'addFlash', 'redirectToRoute', 'getUser'])
            ->getMock();

        $request = new Request();
        $request->setMethod('POST');

        $controller->expects($this->once())
            ->method('createForm')
            ->willReturn($form);

        $controller->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('success', 'Performance record marked as reviewed successfully.');

        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->willReturn(new RedirectResponse('/performance'));

        $result = $controller->review(1, $request);

        $this->assertInstanceOf(RedirectResponse::class, $result);
    }

    public function testReviewUpdatesSaveAccessRecordsWhenEnableAccessRecordsTrue(): void
    {
        $routeData = new RouteData();
        $routeData->setId(1);
        $routeData->setName('test_route');
        $routeData->setEnv('dev');
        $routeData->setSaveAccessRecords(true);

        $entityManager = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $entityManager->expects($this->atLeastOnce())->method('flush');

        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('find')->with(1)->willReturn($routeData);
        $repository->method('markAsReviewed')->with(1, null, null, null)->willReturn(true);
        $repository->method('getEntityManager')->willReturn($entityManager);

        $this->metricsService->method('getRepository')->willReturn($repository);

        $form = $this->createMock(\Symfony\Component\Form\FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn([
            'queries_improved' => '',
            'time_improved' => '',
            'save_access_records' => false,
        ]);
        $form->method('handleRequest')->willReturnSelf();

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
                true, // enableReviewSystem
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
                true,  // enableAccessRecords
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
            ->onlyMethods(['createForm', 'addFlash', 'redirectToRoute', 'getUser'])
            ->getMock();

        $request = new Request();
        $request->setMethod('POST');

        $controller->expects($this->once())
            ->method('createForm')
            ->willReturn($form);

        $controller->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('success', $this->anything());

        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->willReturn(new RedirectResponse('/performance'));

        $controller->review(1, $request);

        $this->assertFalse($routeData->getSaveAccessRecords());
    }

    public function testClearHandlesException(): void
    {
        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('deleteAll')
            ->willThrowException(new \Exception('Database error'));

        $this->metricsService->method('getRepository')->willReturn($repository);

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
            ->onlyMethods(['isCsrfTokenValid', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $request = new Request();
        $request->setMethod('POST');
        $request->request->set('_token', 'valid_token');
        $request->request->set('env', 'dev');

        $controller->expects($this->once())
            ->method('isCsrfTokenValid')
            ->willReturn(true);

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('error', $this->stringContains('An error occurred while clearing performance data'));

        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->willReturn(new RedirectResponse('/performance'));

        $result = $controller->clear($request);

        $this->assertInstanceOf(RedirectResponse::class, $result);
    }

    public function testDeleteHandlesException(): void
    {
        $routeData = new RouteData();
        $routeData->setId(1);
        $routeData->setName('test_route');
        $routeData->setEnv('dev');

        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('find')->with(1)->willReturn($routeData);
        $repository->method('deleteById')
            ->willThrowException(new \Exception('Database error'));

        $this->metricsService->method('getRepository')->willReturn($repository);

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
                true, // enableRecordManagement
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
            ->onlyMethods(['isCsrfTokenValid', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $request = new Request();
        $request->setMethod('POST');
        $request->request->set('_token', 'valid_token');

        $controller->expects($this->once())
            ->method('isCsrfTokenValid')
            ->willReturn(true);

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('error', $this->stringContains('An error occurred while deleting the record'));

        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->willReturn(new RedirectResponse('/performance'));

        $result = $controller->delete(1, $request);

        $this->assertInstanceOf(RedirectResponse::class, $result);
    }

    public function testReviewHandlesException(): void
    {
        $routeData = new RouteData();
        $routeData->setId(1);
        $routeData->setName('test_route');
        $routeData->setEnv('dev');

        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('find')->with(1)->willReturn($routeData);
        $repository->method('markAsReviewed')
            ->willThrowException(new \Exception('Database error'));

        $this->metricsService->method('getRepository')->willReturn($repository);

        $form = $this->createMock(\Symfony\Component\Form\FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn([
            'queries_improved' => '1',
            'time_improved' => '0',
        ]);

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
                true, // enableReviewSystem
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
            ->onlyMethods(['createForm', 'addFlash', 'redirectToRoute', 'getUser'])
            ->getMock();

        $request = new Request();
        $request->setMethod('POST');

        $controller->expects($this->once())
            ->method('createForm')
            ->willReturn($form);

        $controller->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('error', $this->stringContains('An error occurred while reviewing the record'));

        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->willReturn(new RedirectResponse('/performance'));

        $result = $controller->review(1, $request);

        $this->assertInstanceOf(RedirectResponse::class, $result);
    }

    public function testIndexWithTailwindTemplate(): void
    {
        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null,
                true,
                [],
                'tailwind', // template
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
            ->onlyMethods(['getParameter', 'render', 'createForm', 'getAvailableEnvironments'])
            ->getMock();

        $request = new Request();
        $request->query->set('env', 'dev');

        $routeData = new RouteData();
        $routeData->setName('test_route');
        $routeData->setEnv('dev');

        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('getDistinctEnvironments')->willReturn(['dev']);
        $repository->method('findWithFilters')->willReturn([$routeData]);

        $this->metricsService->method('getRoutesByEnvironment')->willReturn([$routeData]);
        $this->metricsService->method('getRepository')->willReturn($repository);

        $form = $this->createMock(\Symfony\Component\Form\FormInterface::class);
        $formView = $this->createMock(\Symfony\Component\Form\FormView::class);
        $form->method('createView')->willReturn($formView);
        $form->method('handleRequest')->willReturnSelf();
        $form->method('getData')->willReturn(['env' => 'dev']);

        $controller->expects($this->any())
            ->method('getParameter')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'kernel.environment' => 'dev',
                    default => null,
                };
            });

        $controller->expects($this->once())
            ->method('getAvailableEnvironments')
            ->willReturn(['dev']);

        $controller->expects($this->once())
            ->method('createForm')
            ->willReturn($form);

        $controller->expects($this->once())
            ->method('render')
            ->with(
                '@NowoPerformanceBundle/Performance/index.html.twig',
                $this->callback(function ($vars) {
                    return isset($vars['template']) && $vars['template'] === 'tailwind';
                })
            )
            ->willReturn(new Response());

        $result = $controller->index($request);

        $this->assertInstanceOf(Response::class, $result);
    }
}
