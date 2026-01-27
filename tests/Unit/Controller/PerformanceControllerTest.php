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
}
