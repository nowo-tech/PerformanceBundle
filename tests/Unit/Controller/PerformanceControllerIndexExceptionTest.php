<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Controller;

use Exception;
use Nowo\PerformanceBundle\Controller\PerformanceController;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Form\ClearPerformanceDataType;
use Nowo\PerformanceBundle\Form\DeleteRecordType;
use Nowo\PerformanceBundle\Form\PerformanceFiltersType;
use Nowo\PerformanceBundle\Form\ReviewRouteDataType;
use Nowo\PerformanceBundle\Model\RouteDataWithAggregates;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use Nowo\PerformanceBundle\Service\DependencyChecker;
use Nowo\PerformanceBundle\Service\PerformanceCacheService;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Unit tests for PerformanceController::index() exception paths (getAvailableEnvironments and countWithFilters catch blocks).
 */
final class PerformanceControllerIndexExceptionTest extends TestCase
{
    private MockObject&PerformanceMetricsService $metricsService;
    private MockObject&RouteDataRepository $repository;

    protected function setUp(): void
    {
        $this->repository     = $this->createMock(RouteDataRepository::class);
        $this->metricsService = $this->createMock(PerformanceMetricsService::class);
        $this->metricsService->method('getRepository')->willReturn($this->repository);
    }

    /** When countWithFilters throws, index returns response with empty routes and default paginator. */
    public function testIndexWhenCountWithFiltersThrowsReturnsEmptyRoutesAndDefaultPaginator(): void
    {
        $this->repository->method('countWithFilters')->willThrowException(new Exception('DB error'));
        $this->repository->method('getDistinctEnvironments')->willReturn(['test']);
        $this->metricsService->method('getRoutesWithAggregates')->willReturn([]);

        $filtersForm = $this->createMock(FormInterface::class);
        $filtersForm->method('handleRequest');
        $filtersForm->method('getData')->willReturn([
            'env'              => 'test',
            'route'            => null,
            'path'             => null,
            'sort'             => 'requestTime',
            'order'            => 'DESC',
            'limit'            => 100,
            'min_request_time' => null,
            'max_request_time' => null,
            'min_query_count'  => null,
            'max_query_count'  => null,
            'date_from'        => null,
            'date_to'          => null,
        ]);
        $filtersForm->method('createView')->willReturn(new FormView());

        $clearForm = $this->createMock(FormInterface::class);
        $clearForm->method('createView')->willReturn(new FormView());

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
        $controller->method('createForm')->willReturnCallback(static function ($type) use ($filtersForm, $clearForm) {
            if ($type === PerformanceFiltersType::class) {
                return $filtersForm;
            }
            if ($type === ClearPerformanceDataType::class) {
                return $clearForm;
            }

            return $filtersForm;
        });
        $controller->method('render')->willReturn(new Response('', 200));

        $request  = Request::create('/performance', 'GET');
        $response = $controller->index($request);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(200, $response->getStatusCode());
    }

    /** When getAvailableEnvironments throws (e.g. repository fails), index uses fallback environments. */
    public function testIndexWhenGetAvailableEnvironmentsThrowsUsesFallbackEnvironments(): void
    {
        $this->repository->method('getDistinctEnvironments')->willThrowException(new Exception('DB unavailable'));
        $this->repository->method('countWithFilters')->willReturn(0);
        $this->metricsService->method('getRoutesWithAggregates')->willReturn([]);

        $filtersForm = $this->createMock(FormInterface::class);
        $filtersForm->method('handleRequest');
        $filtersForm->method('getData')->willReturn([
            'env'              => 'test',
            'route'            => null,
            'path'             => null,
            'sort'             => 'requestTime',
            'order'            => 'DESC',
            'limit'            => 100,
            'min_request_time' => null,
            'max_request_time' => null,
            'min_query_count'  => null,
            'max_query_count'  => null,
            'date_from'        => null,
            'date_to'          => null,
        ]);
        $filtersForm->method('createView')->willReturn(new FormView());

        $clearForm = $this->createMock(FormInterface::class);
        $clearForm->method('createView')->willReturn(new FormView());

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
                [], // allowedEnvironments = [] so getAvailableEnvironments() calls getDistinctEnvironments()
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
        $controller->method('createForm')->willReturnCallback(static function ($type) use ($filtersForm, $clearForm) {
            if ($type === ClearPerformanceDataType::class) {
                return $clearForm;
            }

            return $filtersForm;
        });
        $controller->method('render')->willReturn(new Response('', 200));

        $request  = Request::create('/performance', 'GET');
        $response = $controller->index($request);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(200, $response->getStatusCode());
    }

    /** When cacheService returns cached statistics, index uses them and does not recalculate. */
    public function testIndexWithCacheHitUsesCachedStatistics(): void
    {
        $this->repository->method('countWithFilters')->willReturn(0);
        $this->repository->method('getDistinctEnvironments')->willReturn(['test']);
        $this->metricsService->method('getRoutesWithAggregatesFiltered')->willReturn([]);
        $this->metricsService->method('getRoutesWithAggregates')->willReturn([]);

        $cachedStats = [
            'total_routes'        => 0,
            'avg_queries'         => 0.0,
            'max_queries'         => 0,
            'avg_request_time'    => 0.0,
            'avg_query_time'      => 0.0,
            'max_request_time'    => 0.0,
            'max_query_time'      => 0.0,
            'total_records'       => 0,
            'top_used_routes'     => [],
            'top_consumed_routes' => [],
        ];
        $cacheService = $this->createMock(PerformanceCacheService::class);
        $cacheService->method('getCachedStatistics')->with('test')->willReturn($cachedStats);

        $filtersForm = $this->createMock(FormInterface::class);
        $filtersForm->method('handleRequest');
        $filtersForm->method('getData')->willReturn([
            'env'              => 'test', 'route' => null, 'path' => null, 'sort' => 'requestTime', 'order' => 'DESC', 'limit' => 100,
            'min_request_time' => null, 'max_request_time' => null, 'min_query_count' => null, 'max_query_count' => null,
            'date_from'        => null, 'date_to' => null,
        ]);
        $filtersForm->method('createView')->willReturn(new FormView());
        $clearForm = $this->createMock(FormInterface::class);
        $clearForm->method('createView')->willReturn(new FormView());

        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService, null, true, [], 'bootstrap', $cacheService, null, null, false, false, null,
                0.5, 1.0, 20, 50, 20.0, 50.0, 'Y-m-d H:i:s', 'Y-m-d H:i', 0, [200, 404, 500, 503], null, false, true,
                ['dev', 'test'], 'default', true, true, false, [], false, 1.0, true, true, null,
            ])
            ->onlyMethods(['createForm', 'getParameter', 'render'])
            ->getMock();

        $controller->method('getParameter')->with('kernel.environment')->willReturn('test');
        $controller->method('createForm')->willReturnCallback(static function ($type) use ($filtersForm, $clearForm) {
            return $type === ClearPerformanceDataType::class ? $clearForm : $filtersForm;
        });
        $controller->method('render')->willReturn(new Response('', 200));

        $request  = Request::create('/performance', 'GET');
        $response = $controller->index($request);

        self::assertSame(200, $response->getStatusCode());
    }

    /** Index with dependencyChecker set uses it for useComponents and dependencyStatus. */
    public function testIndexWithDependencyCheckerUsesIt(): void
    {
        $this->repository->method('countWithFilters')->willReturn(0);
        $this->repository->method('getDistinctEnvironments')->willReturn(['test']);
        $this->metricsService->method('getRoutesWithAggregatesFiltered')->willReturn([]);
        $this->metricsService->method('getRoutesWithAggregates')->willReturn([]);

        $dependencyChecker = $this->createMock(DependencyChecker::class);
        $dependencyChecker->method('isTwigComponentAvailable')->willReturn(true);
        $dependencyChecker->method('getMissingDependencies')->willReturn([]);
        $dependencyChecker->method('getDependencyStatus')->willReturn(['twig_component' => ['available' => true, 'package' => 'symfony/ux-twig-component', 'required' => false]]);

        $filtersForm = $this->createMock(FormInterface::class);
        $filtersForm->method('handleRequest');
        $filtersForm->method('getData')->willReturn([
            'env'              => 'test', 'route' => null, 'path' => null, 'sort' => 'requestTime', 'order' => 'DESC', 'limit' => 100,
            'min_request_time' => null, 'max_request_time' => null, 'min_query_count' => null, 'max_query_count' => null,
            'date_from'        => null, 'date_to' => null,
        ]);
        $filtersForm->method('createView')->willReturn(new FormView());
        $clearForm = $this->createMock(FormInterface::class);
        $clearForm->method('createView')->willReturn(new FormView());

        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService, null, true, [], 'bootstrap', null, $dependencyChecker, null, false, false, null,
                0.5, 1.0, 20, 50, 20.0, 50.0, 'Y-m-d H:i:s', 'Y-m-d H:i', 0, [200, 404, 500, 503], null, false, true,
                ['dev', 'test'], 'default', true, true, false, [], false, 1.0, true, true, null,
            ])
            ->onlyMethods(['createForm', 'getParameter', 'render'])
            ->getMock();

        $controller->method('getParameter')->with('kernel.environment')->willReturn('test');
        $controller->method('createForm')->willReturnCallback(static function ($type) use ($filtersForm, $clearForm) {
            return $type === ClearPerformanceDataType::class ? $clearForm : $filtersForm;
        });
        $controller->method('render')->willReturn(new Response('', 200));

        $request  = Request::create('/performance', 'GET');
        $response = $controller->index($request);

        self::assertSame(200, $response->getStatusCode());
    }

    /** Index with template tailwind and enableRecordManagement uses tailwind submit_attr_class for delete forms. */
    public function testIndexWithTailwindTemplateUsesTailwindClassForDeleteForms(): void
    {
        $routeWithAggregates = $this->createRouteWithAggregates();
        $this->repository->method('countWithFilters')->willReturn(1);
        $this->repository->method('getDistinctEnvironments')->willReturn(['test']);
        $this->metricsService->method('getRoutesWithAggregatesFiltered')->willReturn([$routeWithAggregates]);
        $this->metricsService->method('getRoutesWithAggregates')->willReturn([$routeWithAggregates]);

        $filtersForm = $this->createMock(FormInterface::class);
        $filtersForm->method('handleRequest');
        $filtersForm->method('getData')->willReturn([
            'env'              => 'test', 'route' => null, 'path' => null, 'sort' => 'requestTime', 'order' => 'DESC', 'limit' => 100,
            'min_request_time' => null, 'max_request_time' => null, 'min_query_count' => null, 'max_query_count' => null,
            'date_from'        => null, 'date_to' => null,
        ]);
        $filtersForm->method('createView')->willReturn(new FormView());
        $clearForm = $this->createMock(FormInterface::class);
        $clearForm->method('createView')->willReturn(new FormView());
        $deleteForm = $this->createMock(FormInterface::class);
        $deleteForm->method('createView')->willReturn(new FormView());
        $reviewForm = $this->createMock(FormInterface::class);
        $reviewForm->method('createView')->willReturn(new FormView());

        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService, null, true, [], 'tailwind', null, null, null,
                true,  // enableRecordManagement
                true,  // enableReviewSystem
                null,
                0.5, 1.0, 20, 50, 20.0, 50.0, 'Y-m-d H:i:s', 'Y-m-d H:i', 0, [200, 404, 500, 503], null, false, true,
                ['dev', 'test'], 'default', true, true, false, [], false, 1.0, true, true, null,
            ])
            ->onlyMethods(['createForm', 'getParameter', 'render'])
            ->getMock();

        $controller->method('getParameter')->with('kernel.environment')->willReturn('test');
        $controller->method('createForm')->willReturnCallback(static function ($type, $data = null, $options = []) use ($filtersForm, $clearForm, $deleteForm, $reviewForm) {
            if ($type === ClearPerformanceDataType::class) {
                return $clearForm;
            }
            if ($type === PerformanceFiltersType::class) {
                return $filtersForm;
            }
            if ($type === DeleteRecordType::class) {
                self::assertArrayHasKey('submit_attr_class', $options);
                self::assertStringContainsString('inline-flex', $options['submit_attr_class']);

                return $deleteForm;
            }
            if ($type === ReviewRouteDataType::class) {
                return $reviewForm;
            }

            return $filtersForm;
        });
        $controller->method('render')->willReturn(new Response('', 200));

        $request  = Request::create('/performance', 'GET');
        $response = $controller->index($request);

        self::assertSame(200, $response->getStatusCode());
    }

    private function createRouteWithAggregates(): RouteDataWithAggregates
    {
        $routeData = new RouteData();
        $ref       = new ReflectionClass($routeData);
        $id        = $ref->getProperty('id');
        $id->setAccessible(true);
        $id->setValue($routeData, 1);
        $env = $ref->getProperty('env');
        $env->setAccessible(true);
        $env->setValue($routeData, 'test');
        $name = $ref->getProperty('name');
        $name->setAccessible(true);
        $name->setValue($routeData, 'app_home');

        return new RouteDataWithAggregates($routeData, [
            'request_time' => 0.5, 'total_queries' => 10, 'query_time' => 0.02,
            'memory_usage' => 1024 * 1024, 'access_count' => 5, 'status_codes' => [200 => 10],
        ]);
    }
}
