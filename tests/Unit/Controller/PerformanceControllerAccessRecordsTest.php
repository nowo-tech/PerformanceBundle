<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Controller;

use Nowo\PerformanceBundle\Controller\PerformanceController;
use Nowo\PerformanceBundle\Model\RecordFilters;
use Nowo\PerformanceBundle\Repository\RouteDataRecordRepository;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Tests for PerformanceController::accessRecords() and ::deleteRecordsByFilter().
 */
final class PerformanceControllerAccessRecordsTest extends TestCase
{
    private PerformanceMetricsService|MockObject $metricsService;
    private RouteDataRecordRepository|MockObject $recordRepository;

    protected function setUp(): void
    {
        $this->metricsService = $this->createMock(PerformanceMetricsService::class);
        $this->recordRepository = $this->createMock(RouteDataRecordRepository::class);
    }

    private function createController(
        bool $enabled = true,
        bool $enableAccessRecords = true,
        bool $enableRecordManagement = false,
        ?RouteDataRecordRepository $recordRepository = null,
        array $requiredRoles = [],
    ): PerformanceController {
        $repo = $recordRepository ?? $this->recordRepository;

        return new PerformanceController(
            $this->metricsService,
            null,
            $enabled,
            $requiredRoles,
            'bootstrap',
            null,
            null,
            null,
            $enableRecordManagement,
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
            $repo,
            $enableAccessRecords,
            true,
            ['dev', 'test', 'prod'],
            'default',
            true,
            true,
            false,
            [],
            false,
            1.0,
            true,
        );
    }

    public function testAccessRecordsThrowsWhenDisabled(): void
    {
        $controller = $this->createController(enabled: false);
        $request = new Request();

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Temporal access records are disabled.');

        $controller->accessRecords($request);
    }

    public function testAccessRecordsThrowsWhenAccessRecordsDisabled(): void
    {
        $controller = $this->createController(enableAccessRecords: false);
        $request = new Request();

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Temporal access records are disabled.');

        $controller->accessRecords($request);
    }

    public function testAccessRecordsThrowsAccessDeniedWhenUserLacksRoles(): void
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
                $this->recordRepository,
                true,
                true,
                ['dev', 'test', 'prod'],
                'default',
                true,
                true,
                false,
                [],
                false,
                1.0,
                true,
            ])
            ->onlyMethods(['isGranted'])
            ->getMock();

        $controller->method('isGranted')->with('ROLE_ADMIN')->willReturn(false);

        $request = new Request();

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('You do not have permission to access access records.');

        $controller->accessRecords($request);
    }

    public function testAccessRecordsReturnsResponse(): void
    {
        $filterData = new RecordFilters(null, null, 'dev', null, null);
        $filterForm = $this->createMock(FormInterface::class);
        $filterForm->method('handleRequest')->with($this->anything())->willReturnSelf();
        $filterForm->method('getData')->willReturn($filterData);
        $filterForm->method('createView')->willReturn(new \Symfony\Component\Form\FormView());

        $deleteForm = $this->createMock(FormInterface::class);
        $deleteForm->method('createView')->willReturn(new \Symfony\Component\Form\FormView());

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
                $this->recordRepository,
                true,
                true,
                ['dev', 'test', 'prod'],
                'default',
                true,
                true,
                false,
                [],
                false,
                1.0,
                true,
            ])
            ->onlyMethods(['getParameter', 'createForm', 'getAvailableEnvironments', 'addFlash', 'render'])
            ->getMock();

        $controller->method('getParameter')->willReturnCallback(
            static fn (string $k) => $k === 'kernel.environment' ? 'dev' : null
        );
        $controller->method('getAvailableEnvironments')->willReturn(['dev', 'test', 'prod']);
        $controller->method('addFlash')->with($this->anything(), $this->anything());

        $createFormCalls = 0;
        $controller->method('createForm')->willReturnCallback(function (...$args) use (&$createFormCalls, $filterForm, $deleteForm) {
            ++$createFormCalls;
            return 1 === $createFormCalls ? $filterForm : $deleteForm;
        });

        $routeRepo = $this->createMock(\Doctrine\Persistence\ObjectRepository::class);
        $routeRepo->method('findBy')->willReturn([]);
        $this->metricsService->method('getRepository')->willReturn($routeRepo);
        $this->recordRepository->method('getPaginatedRecords')->willReturn([
            'records' => [],
            'total' => 0,
            'page' => 1,
            'per_page' => 50,
            'total_pages' => 0,
        ]);

        $controller->expects($this->once())
            ->method('render')
            ->with(
                '@NowoPerformanceBundle/Performance/access_records.html.twig',
                $this->callback(function (array $vars): bool {
                    return isset($vars['paginated_data'])
                        && isset($vars['filterForm'])
                        && isset($vars['deleteByFilterForm'])
                        && isset($vars['environments'])
                        && isset($vars['environment']);
                })
            )
            ->willReturn(new Response());

        $request = new Request();
        $request->query->set('env', 'dev');

        $result = $controller->accessRecords($request);

        $this->assertInstanceOf(Response::class, $result);
    }

    public function testDeleteRecordsByFilterThrowsWhenDisabled(): void
    {
        $controller = $this->createController(enabled: false, enableRecordManagement: true);
        $request = new Request();
        $request->setMethod('POST');
        $request->request->set('_token', 't');
        $request->request->set('env', 'dev');

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Temporal access records are disabled.');

        $controller->deleteRecordsByFilter($request);
    }

    public function testDeleteRecordsByFilterThrowsWhenAccessRecordsDisabled(): void
    {
        $controller = $this->createController(enableAccessRecords: false, enableRecordManagement: true);
        $request = new Request();
        $request->setMethod('POST');
        $request->request->set('_token', 't');
        $request->request->set('env', 'dev');

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Temporal access records are disabled.');

        $controller->deleteRecordsByFilter($request);
    }

    public function testDeleteRecordsByFilterThrowsWhenRecordManagementDisabled(): void
    {
        $controller = $this->createController(enableRecordManagement: false);
        $request = new Request();
        $request->setMethod('POST');
        $request->request->set('_token', 't');
        $request->request->set('env', 'dev');

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Record management is disabled.');

        $controller->deleteRecordsByFilter($request);
    }

    public function testDeleteRecordsByFilterThrowsAccessDeniedWhenUserLacksRoles(): void
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
                true,
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
                $this->recordRepository,
                true,
                true,
                ['dev', 'test', 'prod'],
                'default',
                true,
                true,
                false,
                [],
                false,
                1.0,
                true,
            ])
            ->onlyMethods(['isGranted'])
            ->getMock();

        $controller->method('isGranted')->with('ROLE_ADMIN')->willReturn(false);

        $request = new Request();
        $request->setMethod('POST');
        $request->request->set('_token', 'valid');
        $request->request->set('env', 'dev');

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('You do not have permission to delete access records.');

        $controller->deleteRecordsByFilter($request);
    }

    public function testDeleteRecordsByFilterRedirectsWhenCsrfInvalid(): void
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
                true,
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
                $this->recordRepository,
                true,
                true,
                ['dev', 'test', 'prod'],
                'default',
                true,
                true,
                false,
                [],
                false,
                1.0,
                true,
            ])
            ->onlyMethods(['isCsrfTokenValid', 'getParameter', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $controller->method('isCsrfTokenValid')->with('delete_records_by_filter', 'bad')->willReturn(false);
        $controller->method('getParameter')->willReturnCallback(
            static fn (string $k) => $k === 'kernel.environment' ? 'dev' : null
        );
        $controller->expects($this->once())->method('addFlash')->with('error', 'Invalid security token. Please try again.');
        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->with('nowo_performance.access_records', $this->callback(fn (array $p) => isset($p['env'])))
            ->willReturn(new RedirectResponse('/performance/access-records'));

        $request = new Request();
        $request->setMethod('POST');
        $request->request->set('_token', 'bad');
        $request->request->set('env', 'dev');

        $result = $controller->deleteRecordsByFilter($request);

        $this->assertInstanceOf(RedirectResponse::class, $result);
    }

    public function testDeleteRecordsByFilterRedirectsWhenEnvMissing(): void
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
                true,
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
                $this->recordRepository,
                true,
                true,
                ['dev', 'test', 'prod'],
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

        $controller->method('isCsrfTokenValid')->willReturn(true);
        $controller->expects($this->once())->method('addFlash')->with('error', 'Environment is required.');
        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->with('nowo_performance.access_records')
            ->willReturn(new RedirectResponse('/performance/access-records'));

        $request = new Request();
        $request->setMethod('POST');
        $request->request->set('_token', 'valid');
        $request->request->set('env', '');

        $result = $controller->deleteRecordsByFilter($request);

        $this->assertInstanceOf(RedirectResponse::class, $result);
    }

    public function testDeleteRecordsByFilterRedirectsWhenRepositoryNull(): void
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
                true,
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
                true,
                true,
                ['dev', 'test', 'prod'],
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

        $controller->method('isCsrfTokenValid')->willReturn(true);
        $controller->expects($this->once())
            ->method('addFlash')
            ->with('error', 'Access records repository is not available.');
        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->with('nowo_performance.access_records', ['env' => 'dev'])
            ->willReturn(new RedirectResponse('/performance/access-records'));

        $request = new Request();
        $request->setMethod('POST');
        $request->request->set('_token', 'valid');
        $request->request->set('env', 'dev');

        $result = $controller->deleteRecordsByFilter($request);

        $this->assertInstanceOf(RedirectResponse::class, $result);
    }

    public function testDeleteRecordsByFilterSuccessRedirectsWithFlash(): void
    {
        $this->recordRepository->method('deleteByFilter')
            ->with('dev', null, null, null, null, null, null, null, null)
            ->willReturn(7);

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
                true,
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
                $this->recordRepository,
                true,
                true,
                ['dev', 'test', 'prod'],
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

        $controller->method('isCsrfTokenValid')->willReturn(true);
        $controller->expects($this->once())
            ->method('addFlash')
            ->with('success', 'Deleted 7 access record(s) matching the filter.');
        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->with('nowo_performance.access_records', ['env' => 'dev'])
            ->willReturn(new RedirectResponse('/performance/access-records'));

        $request = new Request();
        $request->setMethod('POST');
        $request->request->set('_token', 'valid');
        $request->request->set('env', 'dev');

        $result = $controller->deleteRecordsByFilter($request);

        $this->assertInstanceOf(RedirectResponse::class, $result);
    }
}
