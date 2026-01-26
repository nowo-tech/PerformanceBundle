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
use Symfony\Component\HttpKernel\Exception\AccessDeniedException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Environment;

final class PerformanceControllerTest extends TestCase
{
    private PerformanceMetricsService|MockObject $metricsService;
    private PerformanceController $controller;
    private AuthorizationCheckerInterface|MockObject $authorizationChecker;
    private ParameterBagInterface|MockObject $parameterBag;
    private Environment|MockObject $twig;

    protected function setUp(): void
    {
        $this->metricsService = $this->createMock(PerformanceMetricsService::class);
        $this->authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $this->parameterBag = $this->createMock(ParameterBagInterface::class);
        $this->twig = $this->createMock(Environment::class);

        // Create a partial mock of AbstractController to test the controller
        $this->controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                true, // enabled
                [], // requiredRoles (empty by default)
            ])
            ->onlyMethods(['isGranted', 'getParameter', 'render', 'createNotFoundException', 'createAccessDeniedException'])
            ->getMock();

        // Set protected properties using reflection
        $reflection = new \ReflectionClass($this->controller);
        $authorizationProperty = $reflection->getParentClass()->getProperty('authorizationChecker');
        $authorizationProperty->setAccessible(true);
        $authorizationProperty->setValue($this->controller, $this->authorizationChecker);

        $parameterBagProperty = $reflection->getParentClass()->getProperty('parameterBag');
        $parameterBagProperty->setAccessible(true);
        $parameterBagProperty->setValue($this->controller, $this->parameterBag);
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
            ->onlyMethods(['getParameter', 'render'])
            ->getMock();

        $request = new Request();
        $request->query->set('env', 'dev');

        $routeData = new RouteData();
        $routeData->setName('test_route');
        $routeData->setEnv('dev');

        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('getDistinctEnvironments')->willReturn(['dev']);

        $this->metricsService->method('getRoutesByEnvironment')->willReturn([$routeData]);
        $this->metricsService->method('getRepository')->willReturn($repository);

        $controller->expects($this->once())
            ->method('getParameter')
            ->with('kernel.environment')
            ->willReturn('dev');

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
            ->onlyMethods(['isGranted', 'getParameter', 'render'])
            ->getMock();

        $request = new Request();
        $request->query->set('env', 'dev');

        $routeData = new RouteData();
        $routeData->setName('test_route');
        $routeData->setEnv('dev');

        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('getDistinctEnvironments')->willReturn(['dev']);

        $this->metricsService->method('getRoutesByEnvironment')->willReturn([$routeData]);
        $this->metricsService->method('getRepository')->willReturn($repository);

        $controller->expects($this->once())
            ->method('isGranted')
            ->with('ROLE_ADMIN')
            ->willReturn(true);

        $controller->expects($this->once())
            ->method('getParameter')
            ->with('kernel.environment')
            ->willReturn('dev');

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
            ->onlyMethods(['isGranted', 'getParameter', 'render'])
            ->getMock();

        $request = new Request();
        $request->query->set('env', 'dev');

        $routeData = new RouteData();
        $routeData->setName('test_route');
        $routeData->setEnv('dev');

        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('getDistinctEnvironments')->willReturn(['dev']);

        $this->metricsService->method('getRoutesByEnvironment')->willReturn([$routeData]);
        $this->metricsService->method('getRepository')->willReturn($repository);

        // User has ROLE_PERFORMANCE_VIEWER but not ROLE_ADMIN
        $controller->expects($this->exactly(2))
            ->method('isGranted')
            ->willReturnCallback(function ($role) {
                return $role === 'ROLE_PERFORMANCE_VIEWER';
            });

        $controller->expects($this->once())
            ->method('getParameter')
            ->with('kernel.environment')
            ->willReturn('dev');

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
            ->onlyMethods(['getParameter', 'render'])
            ->getMock();

        $request = new Request();
        $request->query->set('env', 'dev');
        $request->query->set('route', 'test');

        $route1 = new RouteData();
        $route1->setName('test_route');
        $route1->setEnv('dev');

        $route2 = new RouteData();
        $route2->setName('other_route');
        $route2->setEnv('dev');

        $repository = $this->createMock(RouteDataRepository::class);
        $repository->method('getDistinctEnvironments')->willReturn(['dev']);

        $this->metricsService->method('getRoutesByEnvironment')->willReturn([$route1, $route2]);
        $this->metricsService->method('getRepository')->willReturn($repository);

        $controller->expects($this->once())
            ->method('getParameter')
            ->with('kernel.environment')
            ->willReturn('dev');

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
}
