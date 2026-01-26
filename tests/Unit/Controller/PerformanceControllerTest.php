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

    public function testIndexThrowsExceptionWhenDisabled(): void
    {
        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                false, // disabled
                [],
            ])
            ->onlyMethods([])
            ->getMock();

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
                true, // enabled
                [], // no roles required
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
                true, // enabled
                ['ROLE_ADMIN'], // required role
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
                true, // enabled
                ['ROLE_ADMIN', 'ROLE_PERFORMANCE_VIEWER'], // multiple roles
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
                true, // enabled
                ['ROLE_ADMIN'], // required role
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
                true, // enabled
                ['ROLE_ADMIN', 'ROLE_PERFORMANCE_VIEWER'], // multiple roles
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
                true, // enabled
                [], // no roles required
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
                false, // disabled
                [],
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
                true, // enabled
                ['ROLE_ADMIN'], // required role
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
                true, // enabled
                [], // no roles required
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
                false, // disabled
                [],
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
                true, // enabled
                ['ROLE_ADMIN'], // required role
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
                true, // enabled
                [], // no roles required
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
}
