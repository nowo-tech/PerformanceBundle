<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Controller;

use Exception;
use Nowo\PerformanceBundle\Controller\PerformanceController;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Form\StatisticsEnvFilterType;
use Nowo\PerformanceBundle\Model\RouteDataWithAggregates;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use Nowo\PerformanceBundle\Service\PerformanceAnalysisService;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Unit tests for PerformanceController::statistics() with analysisService (correlations, efficiency, recommendations).
 */
final class PerformanceControllerStatisticsTest extends TestCase
{
    private MockObject&PerformanceMetricsService $metricsService;
    private MockObject&PerformanceAnalysisService $analysisService;
    private MockObject&RouteDataRepository $repository;

    protected function setUp(): void
    {
        $this->repository     = $this->createMock(RouteDataRepository::class);
        $this->metricsService = $this->createMock(PerformanceMetricsService::class);
        $this->metricsService->method('getRepository')->willReturn($this->repository);
        $this->analysisService = $this->createMock(PerformanceAnalysisService::class);
    }

    private function createRouteWithAggregates(): RouteDataWithAggregates
    {
        $routeData = new RouteData();
        $ref       = new ReflectionClass($routeData);
        $id        = $ref->getProperty('id');
        $id->setValue($routeData, 1);
        $env = $ref->getProperty('env');
        $env->setValue($routeData, 'test');
        $name = $ref->getProperty('name');
        $name->setValue($routeData, 'app_home');

        return new RouteDataWithAggregates($routeData, [
            'request_time'  => 0.5,
            'total_queries' => 10,
            'query_time'    => 0.02,
            'memory_usage'  => 1024 * 1024,
            'access_count'  => 5,
            'status_codes'  => [200 => 10],
        ]);
    }

    public function testStatisticsWithAnalysisServiceRendersWithCorrelationsAndRecommendations(): void
    {
        $this->repository->method('getDistinctEnvironments')->willReturn(['test']);
        $routeWithAggregates = $this->createRouteWithAggregates();

        $this->metricsService->method('getRoutesWithAggregates')->with('test')->willReturn([$routeWithAggregates]);

        $this->analysisService->method('analyzeCorrelations')->with(self::anything())->willReturn(['request_time_vs_query_time' => 0.8]);
        $this->analysisService->method('analyzeEfficiency')->with(self::anything())->willReturn([]);
        $this->analysisService->method('generateRecommendations')->with(self::anything(), self::anything())->willReturn([]);
        $this->analysisService->method('analyzeTrafficDistribution')->with(self::anything())->willReturn([]);

        $envForm = $this->createMock(FormInterface::class);
        $envForm->method('handleRequest');
        $data      = new stdClass();
        $data->env = 'test';
        $envForm->method('getData')->willReturn($data);
        $envForm->method('createView')->willReturn(new FormView());

        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                $this->analysisService,
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
                true,
                null,
            ])
            ->onlyMethods(['createForm', 'getParameter', 'render'])
            ->getMock();

        $controller->method('getParameter')->with('kernel.environment')->willReturn('test');
        $controller->method('createForm')->with(
            StatisticsEnvFilterType::class,
            self::anything(),
            self::anything(),
        )->willReturn($envForm);
        $controller->method('render')->with(
            '@NowoPerformanceBundle/Performance/statistics.html.twig',
            self::callback(static fn (array $vars): bool => isset($vars['correlations'], $vars['efficiency'], $vars['recommendations'], $vars['traffic_distribution'])),
        )->willReturn(new Response('', 200));

        $request  = Request::create('/performance/statistics', 'GET', ['env' => 'test']);
        $response = $controller->statistics($request);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(200, $response->getStatusCode());
    }

    /** When getRoutesWithAggregates throws, statistics uses empty routes and calculateAdvancedStats([]). */
    public function testStatisticsWhenGetRoutesWithAggregatesThrowsUsesEmptyRoutes(): void
    {
        $this->repository->method('getDistinctEnvironments')->willReturn(['test']);
        $this->metricsService->method('getRoutesWithAggregates')->with('test')->willThrowException(new Exception('DB error'));

        $envForm = $this->createMock(FormInterface::class);
        $envForm->method('handleRequest');
        $data      = new stdClass();
        $data->env = 'test';
        $envForm->method('getData')->willReturn($data);
        $envForm->method('createView')->willReturn(new FormView());

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
                true,
                null,
            ])
            ->onlyMethods(['createForm', 'getParameter', 'render'])
            ->getMock();

        $controller->method('getParameter')->with('kernel.environment')->willReturn('test');
        $controller->method('createForm')->with(
            StatisticsEnvFilterType::class,
            self::anything(),
            self::anything(),
        )->willReturn($envForm);
        $controller->method('render')->willReturn(new Response('', 200));

        $request  = Request::create('/performance/statistics', 'GET', ['env' => 'test']);
        $response = $controller->statistics($request);

        self::assertSame(200, $response->getStatusCode());
    }

    /** Statistics with requiredRoles and isGranted true passes the role check. */
    public function testStatisticsWithRequiredRolesAndIsGrantedRenders(): void
    {
        $this->repository->method('getDistinctEnvironments')->willReturn(['test']);
        $this->metricsService->method('getRoutesWithAggregates')->with('test')->willReturn([]);

        $envForm = $this->createMock(FormInterface::class);
        $envForm->method('handleRequest');
        $data      = new stdClass();
        $data->env = 'test';
        $envForm->method('getData')->willReturn($data);
        $envForm->method('createView')->willReturn(new FormView());

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
                true,
                null,
            ])
            ->onlyMethods(['createForm', 'getParameter', 'render', 'isGranted'])
            ->getMock();

        $controller->method('getParameter')->with('kernel.environment')->willReturn('test');
        $controller->method('isGranted')->with('ROLE_ADMIN')->willReturn(true);
        $controller->method('createForm')->with(
            StatisticsEnvFilterType::class,
            self::anything(),
            self::anything(),
        )->willReturn($envForm);
        $controller->method('render')->willReturn(new Response('', 200));

        $request  = Request::create('/performance/statistics', 'GET', ['env' => 'test']);
        $response = $controller->statistics($request);

        self::assertSame(200, $response->getStatusCode());
    }
}
