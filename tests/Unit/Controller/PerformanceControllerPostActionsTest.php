<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Controller;

use DateTimeImmutable;
use Exception;
use Nowo\PerformanceBundle\Controller\PerformanceController;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Event\BeforeRecordDeletedEvent;
use Nowo\PerformanceBundle\Event\BeforeRecordsClearedEvent;
use Nowo\PerformanceBundle\Form\ClearPerformanceDataType;
use Nowo\PerformanceBundle\Form\DeleteRecordsByFilterType;
use Nowo\PerformanceBundle\Form\DeleteRecordType;
use Nowo\PerformanceBundle\Form\PurgeAccessRecordsType;
use Nowo\PerformanceBundle\Form\ReviewRouteDataType;
use Nowo\PerformanceBundle\Model\ClearPerformanceDataRequest;
use Nowo\PerformanceBundle\Model\DeleteRecordsByFilterRequest;
use Nowo\PerformanceBundle\Model\PurgeAccessRecordsRequest;
use Nowo\PerformanceBundle\Repository\RouteDataRecordRepository;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use Nowo\PerformanceBundle\Service\PerformanceCacheService;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Unit tests for PerformanceController POST actions (clear, delete, review) using mocked form and services.
 */
final class PerformanceControllerPostActionsTest extends TestCase
{
    private MockObject&PerformanceMetricsService $metricsService;
    private MockObject&RouteDataRepository $repository;
    private MockObject&RouteDataRecordRepository $recordRepository;
    private MockObject&EventDispatcherInterface $eventDispatcher;
    private MockObject&PerformanceCacheService $cacheService;

    protected function setUp(): void
    {
        $this->repository       = $this->createMock(RouteDataRepository::class);
        $this->recordRepository = $this->createMock(RouteDataRecordRepository::class);
        $this->metricsService   = $this->createMock(PerformanceMetricsService::class);
        $this->metricsService->method('getRepository')->willReturn($this->repository);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->cacheService    = $this->createMock(PerformanceCacheService::class);
    }

    private function createController(
        bool $enabled = true,
        bool $enableRecordManagement = true,
        bool $enableReviewSystem = true,
    ): PerformanceController {
        return new PerformanceController(
            $this->metricsService,
            null,
            $enabled,
            [],
            'bootstrap',
            $this->cacheService,
            null,
            null,
            $enableRecordManagement,
            $enableReviewSystem,
            $this->eventDispatcher,
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
        );
    }

    public function testClearWithValidFormDeletesAndRedirects(): void
    {
        $this->repository->method('deleteAll')->with('test')->willReturn(5);
        $this->eventDispatcher->expects(self::exactly(2))->method('dispatch');
        $this->cacheService->expects(self::once())->method('clearStatistics')->with('test');

        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn(new ClearPerformanceDataRequest('test'));

        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null,
                true,
                [],
                'bootstrap',
                $this->cacheService,
                null,
                null,
                false,
                false,
                $this->eventDispatcher,
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
            ->onlyMethods(['createForm', 'getParameter', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $controller->method('createForm')
            ->with(self::identicalTo(ClearPerformanceDataType::class), self::isInstanceOf(ClearPerformanceDataRequest::class))
            ->willReturn($form);
        $controller->method('getParameter')->with('kernel.environment')->willReturn('test');
        $controller->method('addFlash');
        $controller->method('redirectToRoute')->with('nowo_performance.index')->willReturn(new RedirectResponse('/performance'));

        $request  = Request::create('/performance/clear', 'POST');
        $response = $controller->clear($request);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/performance', $response->getTargetUrl());
    }

    public function testClearWhenEventPreventsClearingRedirectsWithoutDeleting(): void
    {
        $this->repository->expects(self::never())->method('deleteAll');
        $this->eventDispatcher->method('dispatch')->willReturnCallback(static function ($event) {
            if ($event instanceof BeforeRecordsClearedEvent) {
                $event->preventClearing();
            }

            return $event;
        });

        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn(new ClearPerformanceDataRequest('test'));

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
                $this->eventDispatcher,
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
            ->onlyMethods(['createForm', 'getParameter', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $controller->method('createForm')->willReturn($form);
        $controller->method('getParameter')->with('kernel.environment')->willReturn('test');
        $controller->method('addFlash')->with('warning', self::stringContains('prevented'));
        $controller->method('redirectToRoute')->with('nowo_performance.index')->willReturn(new RedirectResponse('/performance'));

        $request  = Request::create('/performance/clear', 'POST');
        $response = $controller->clear($request);

        self::assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testClearWithInvalidFormRedirectsWithoutDeleting(): void
    {
        $this->repository->expects(self::never())->method('deleteAll');

        $form = $this->createMock(FormInterface::class);
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
            ->onlyMethods(['createForm', 'getParameter', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $controller->method('createForm')->willReturn($form);
        $controller->method('getParameter')->with('kernel.environment')->willReturn('test');
        $controller->method('addFlash');
        $controller->method('redirectToRoute')->with('nowo_performance.index')->willReturn(new RedirectResponse('/performance'));

        $request  = Request::create('/performance/clear', 'POST');
        $response = $controller->clear($request);

        self::assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testClearWhenRepositoryThrowsAddsFlashAndRedirects(): void
    {
        $this->repository->method('deleteAll')->willThrowException(new Exception('DB error'));
        $this->eventDispatcher->expects(self::once())->method('dispatch');

        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn(new ClearPerformanceDataRequest('test'));

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
                $this->eventDispatcher,
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
            ->onlyMethods(['createForm', 'getParameter', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $controller->method('createForm')->willReturn($form);
        $controller->method('getParameter')->with('kernel.environment')->willReturn('test');
        $controller->method('addFlash')->with('error', self::stringContains('DB error'));
        $controller->method('redirectToRoute')->with('nowo_performance.index')->willReturn(new RedirectResponse('/performance'));

        $request  = Request::create('/performance/clear', 'POST');
        $response = $controller->clear($request);

        self::assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testDeleteWhenDisabledThrowsNotFound(): void
    {
        $controller = $this->createController(enabled: false);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $controller->delete(1, Request::create('/performance/1/delete', 'POST'));
    }

    public function testDeleteWhenRecordManagementDisabledThrowsAccessDenied(): void
    {
        $controller = $this->createController(enableRecordManagement: false);

        $this->expectException(\Symfony\Component\Security\Core\Exception\AccessDeniedException::class);
        $controller->delete(1, Request::create('/performance/1/delete', 'POST'));
    }

    public function testReviewWhenDisabledThrowsNotFound(): void
    {
        $controller = $this->createController(enabled: false);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $controller->review(1, Request::create('/performance/1/review', 'POST'));
    }

    public function testReviewWhenReviewSystemDisabledThrowsAccessDenied(): void
    {
        $controller = $this->createController(enableReviewSystem: false);

        $this->expectException(\Symfony\Component\Security\Core\Exception\AccessDeniedException::class);
        $controller->review(1, Request::create('/performance/1/review', 'POST'));
    }

    public function testDeleteWithValidFormAndExistingRecordDeletesAndRedirects(): void
    {
        $routeData = $this->createMock(RouteData::class);
        $routeData->method('getEnv')->willReturn('test');
        $routeData->method('getName')->willReturn('app_home');
        $this->repository->method('find')->with(1)->willReturn($routeData);
        $this->repository->method('deleteById')->with(1)->willReturn(true);
        $this->eventDispatcher->expects(self::exactly(2))->method('dispatch');
        $this->cacheService->expects(self::once())->method('clearStatistics')->with('test');
        $this->cacheService->expects(self::once())->method('clearEnvironments');

        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);

        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null,
                true,
                [],
                'bootstrap',
                $this->cacheService,
                null,
                null,
                true,
                false,
                $this->eventDispatcher,
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
            ->onlyMethods(['createForm', 'getParameter', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $controller->method('createForm')
            ->with(self::identicalTo(DeleteRecordType::class), null, self::anything())
            ->willReturn($form);
        $controller->method('addFlash');
        $controller->method('redirectToRoute')->with('nowo_performance.index')->willReturn(new RedirectResponse('/performance'));

        $request  = Request::create('/performance/1/delete', 'POST');
        $response = $controller->delete(1, $request);

        self::assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testDeleteWhenEventPreventsDeletionRedirectsWithoutDeleting(): void
    {
        $routeData = $this->createMock(RouteData::class);
        $routeData->method('getEnv')->willReturn('test');
        $routeData->method('getName')->willReturn('app_home');
        $this->repository->method('find')->with(1)->willReturn($routeData);
        $this->repository->expects(self::never())->method('deleteById');
        $this->eventDispatcher->method('dispatch')->willReturnCallback(static function ($event) {
            if ($event instanceof BeforeRecordDeletedEvent) {
                $event->preventDeletion();
            }

            return $event;
        });

        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);

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
                $this->eventDispatcher,
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
            ->onlyMethods(['createForm', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $controller->method('createForm')->willReturn($form);
        $controller->method('addFlash')->with('warning', self::stringContains('prevented'));
        $controller->method('redirectToRoute')->with('nowo_performance.index')->willReturn(new RedirectResponse('/performance'));

        $request  = Request::create('/performance/1/delete', 'POST');
        $response = $controller->delete(1, $request);

        self::assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testDeleteWhenDeleteByIdReturnsFalseAddsFlashError(): void
    {
        $routeData = $this->createMock(RouteData::class);
        $routeData->method('getEnv')->willReturn('test');
        $routeData->method('getName')->willReturn('app_home');
        $this->repository->method('find')->with(1)->willReturn($routeData);
        $this->repository->method('deleteById')->with(1)->willReturn(false);

        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);

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
            ->onlyMethods(['createForm', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $controller->method('createForm')->willReturn($form);
        $controller->method('addFlash')->with('error', 'Record not found.');
        $controller->method('redirectToRoute')->willReturn(new RedirectResponse('/performance'));

        $request  = Request::create('/performance/1/delete', 'POST');
        $response = $controller->delete(1, $request);

        self::assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testDeleteWhenRecordNotFoundAddsFlashAndRedirects(): void
    {
        $this->repository->method('find')->with(999)->willReturn(null);
        $this->repository->expects(self::never())->method('deleteById');

        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);

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
            ->onlyMethods(['createForm', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $controller->method('createForm')->willReturn($form);
        $controller->method('addFlash')->with('error', self::stringContains('999'));
        $controller->method('redirectToRoute')->with('nowo_performance.index')->willReturn(new RedirectResponse('/performance'));

        $request  = Request::create('/performance/999/delete', 'POST');
        $response = $controller->delete(999, $request);

        self::assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testReviewWithValidFormAndExistingRecordUpdatesAndRedirects(): void
    {
        $routeData = $this->createMock(RouteData::class);
        $routeData->method('getEnv')->willReturn('test');
        $routeData->method('getName')->willReturn('app_home');
        $this->repository->method('find')->with(1)->willReturn($routeData);
        $this->repository->expects(self::once())->method('markAsReviewed')->with(
            1,
            true,
            true,
            null,
        )->willReturn(true);
        $this->eventDispatcher->expects(self::exactly(2))->method('dispatch');
        $this->cacheService->expects(self::once())->method('clearStatistics')->with('test');

        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn([
            'queries_improved' => '1',
            'time_improved'    => '1',
            'notes'            => null,
        ]);

        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null,
                true,
                [],
                'bootstrap',
                $this->cacheService,
                null,
                null,
                false,
                true,
                $this->eventDispatcher,
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
            ->onlyMethods(['createForm', 'getUser', 'addFlash', 'redirectToRoute'])
            ->addMethods(['trans'])
            ->getMock();

        $controller->method('trans')->willReturnArgument(0);
        $controller->method('createForm')
            ->with(self::identicalTo(ReviewRouteDataType::class), null, self::anything())
            ->willReturn($form);
        $controller->method('getUser')->willReturn(null);
        $controller->method('addFlash');
        $controller->method('redirectToRoute')->with('nowo_performance.index')->willReturn(new RedirectResponse('/performance'));

        $request  = Request::create('/performance/1/review', 'POST');
        $response = $controller->review(1, $request);

        self::assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testPurgeRecordsWithValidFormPurgeAllDeletesAndRedirects(): void
    {
        $this->recordRepository->method('deleteAllRecords')->with('test')->willReturn(10);
        $this->cacheService->expects(self::once())->method('clearStatistics')->with('test');

        $data = new PurgeAccessRecordsRequest('test', PurgeAccessRecordsRequest::PURGE_ALL, 30);
        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn($data);

        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null,
                true,
                [],
                'bootstrap',
                $this->cacheService,
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
            ->onlyMethods(['createForm', 'getParameter', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $controller->method('createForm')
            ->with(self::identicalTo(PurgeAccessRecordsType::class), self::isInstanceOf(PurgeAccessRecordsRequest::class))
            ->willReturn($form);
        $controller->method('getParameter')->with('kernel.environment')->willReturn('test');
        $controller->method('addFlash')->with('success', self::stringContains('10'));
        $controller->method('redirectToRoute')->with('nowo_performance.access_records', ['env' => 'test'])->willReturn(new RedirectResponse('/performance/access-records?env=test'));

        $request  = Request::create('/performance/purge-records', 'POST');
        $response = $controller->purgeRecords($request);

        self::assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testPurgeRecordsWithOlderThanCallsDeleteOlderThan(): void
    {
        $this->recordRepository->expects(self::once())->method('deleteOlderThan')->with(
            self::isInstanceOf(DateTimeImmutable::class),
            'test',
        )->willReturn(5);
        $this->cacheService->expects(self::once())->method('clearStatistics')->with('test');

        $data = new PurgeAccessRecordsRequest('test', PurgeAccessRecordsRequest::PURGE_OLDER_THAN, 7);
        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn($data);

        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null,
                true,
                [],
                'bootstrap',
                $this->cacheService,
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
            ->onlyMethods(['createForm', 'getParameter', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $controller->method('createForm')->willReturn($form);
        $controller->method('getParameter')->with('kernel.environment')->willReturn('test');
        $controller->method('addFlash')->with('success', self::stringContains('5'));
        $controller->method('redirectToRoute')->willReturn(new RedirectResponse('/performance/access-records'));

        $request  = Request::create('/performance/purge-records', 'POST');
        $response = $controller->purgeRecords($request);

        self::assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testPurgeRecordsWithInvalidFormRedirectsToAccessRecords(): void
    {
        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(false);
        $this->recordRepository->expects(self::never())->method('deleteAllRecords');
        $this->recordRepository->expects(self::never())->method('deleteOlderThan');

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
            ->onlyMethods(['createForm', 'getParameter', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $controller->method('createForm')->willReturn($form);
        $controller->method('getParameter')->with('kernel.environment')->willReturn('test');
        $controller->method('addFlash');
        $controller->method('redirectToRoute')->with('nowo_performance.access_records')->willReturn(new RedirectResponse('/performance/access-records'));

        $request  = Request::create('/performance/purge-records', 'POST');
        $response = $controller->purgeRecords($request);

        self::assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testPurgeRecordsWhenRecordRepositoryNullAddsFlashAndRedirects(): void
    {
        $data = new PurgeAccessRecordsRequest('test', PurgeAccessRecordsRequest::PURGE_ALL, 30);
        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn($data);

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
            ->onlyMethods(['createForm', 'getParameter', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $controller->method('createForm')->willReturn($form);
        $controller->method('getParameter')->with('kernel.environment')->willReturn('test');
        $controller->method('addFlash')->with('error', self::stringContains('not available'));
        $controller->method('redirectToRoute')->with('nowo_performance.access_records', self::anything())->willReturn(new RedirectResponse('/performance/access-records'));

        $request  = Request::create('/performance/purge-records', 'POST');
        $response = $controller->purgeRecords($request);

        self::assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testDeleteRecordsByFilterWithValidFormDeletesAndRedirects(): void
    {
        $this->recordRepository->expects(self::once())->method('deleteByFilter')->with(
            'test',
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
        )->willReturn(3);
        $this->cacheService->expects(self::once())->method('clearStatistics')->with('test');

        $data = new DeleteRecordsByFilterRequest('test', 'access_records');
        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn($data);

        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null,
                true,
                [],
                'bootstrap',
                $this->cacheService,
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
            ->onlyMethods(['createForm', 'getParameter', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $controller->method('createForm')
            ->with(self::identicalTo(DeleteRecordsByFilterType::class), self::isInstanceOf(DeleteRecordsByFilterRequest::class))
            ->willReturn($form);
        $controller->method('getParameter')->with('kernel.environment')->willReturn('test');
        $controller->method('addFlash')->with('success', self::stringContains('3'));
        $controller->method('redirectToRoute')->with('nowo_performance.access_records', ['env' => 'test'])->willReturn(new RedirectResponse('/performance/access-records?env=test'));

        $request  = Request::create('/performance/delete-records-by-filter', 'POST');
        $response = $controller->deleteRecordsByFilter($request);

        self::assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testDeleteRecordsByFilterWhenDisabledThrowsNotFound(): void
    {
        $controller = new PerformanceController(
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
        );

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $controller->deleteRecordsByFilter(Request::create('/performance/delete-records-by-filter', 'POST'));
    }

    public function testDeleteRecordsByFilterWhenRecordManagementDisabledThrowsAccessDenied(): void
    {
        $controller = new PerformanceController(
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
        );

        $this->expectException(\Symfony\Component\Security\Core\Exception\AccessDeniedException::class);
        $controller->deleteRecordsByFilter(Request::create('/performance/delete-records-by-filter', 'POST'));
    }

    public function testChartDataWhenServiceThrowsReturnsEmptyData(): void
    {
        $this->metricsService->method('getRoutesWithAggregatesFiltered')
            ->willThrowException(new Exception('Service unavailable'));

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
            ->onlyMethods(['getParameter'])
            ->getMock();

        $controller->method('getParameter')->with('kernel.environment')->willReturn('test');

        $request  = Request::create('/performance/api/chart-data', 'GET', ['env' => 'test']);
        $response = $controller->chartData($request);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = $response->getContent();
        self::assertNotFalse($content);
        $data = json_decode($content, true);
        self::assertIsArray($data);
        self::assertArrayHasKey('labels', $data);
        self::assertArrayHasKey('datasets', $data);
        self::assertSame([], $data['labels']);
        self::assertSame([], $data['datasets']);
    }
}
