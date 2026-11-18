<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\AbstractException;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\PerformanceBundle\Concurrency\WriteConflictException;
use Nowo\PerformanceBundle\Concurrency\WriteConflictMapper;
use Nowo\PerformanceBundle\Controller\PerformanceController;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Model\ClearPerformanceDataRequest;
use Nowo\PerformanceBundle\Model\DeleteRecordsByFilterRequest;
use Nowo\PerformanceBundle\Model\PurgeAccessRecordsRequest;
use Nowo\PerformanceBundle\Repository\RouteDataRecordRepository;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use Nowo\PerformanceBundle\Security\AllowAllPerformanceAccessChecker;
use Nowo\PerformanceBundle\Service\PerformanceCacheService;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use Nowo\TransactionDoctrine\Symfony\Conflict\ConflictResponsePolicy;
use Nowo\TransactionDoctrine\TransientFailureKind;
use Nowo\TransactionDoctrine\WriteUnit\ConcurrencyConflict;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * A database lock conflict on a dashboard write answers 409 + Retry-After + CONFLICT_001 (nothing saved) or 409 +
 * CONFLICT_002 without Retry-After (a chunked delete already committed part of its work), never an error flash or a
 * 500 (REQ-RUNTIME-003 rule 6).
 */
final class PerformanceControllerConflictTest extends TestCase
{
    private MockObject&PerformanceMetricsService $metricsService;
    private MockObject&RouteDataRepository $repository;
    private MockObject&RouteDataRecordRepository $recordRepository;
    private MockObject&PerformanceCacheService $cacheService;

    protected function setUp(): void
    {
        $this->repository       = $this->createMock(RouteDataRepository::class);
        $this->recordRepository = $this->createMock(RouteDataRecordRepository::class);
        $this->metricsService   = $this->createMock(PerformanceMetricsService::class);
        $this->metricsService->method('getRepository')->willReturn($this->repository);
        $this->cacheService = $this->createMock(PerformanceCacheService::class);
    }

    public function testClearDeadlockAnswersConflict001(): void
    {
        $this->repository->method('deleteAll')->willThrowException($this->deadlock());
        $controller = $this->controller($this->form(new ClearPerformanceDataRequest('test')));

        $conflict = $this->catchConflict(static fn (): RedirectResponse => $controller->clear(Request::create('/performance/clear', 'POST')));

        self::assertSame(409, $conflict->getStatusCode());
        self::assertSame(WriteConflictException::CODE_RETRY_SAFE, $conflict->errorCode);
        self::assertSame('1', $conflict->getHeaders()['Retry-After']);
    }

    public function testDeleteLockWaitTimeoutAnswersConflict001(): void
    {
        $routeData = (new RouteData())->setName('app_home')->setEnv('test');
        $this->repository->method('find')->willReturn($routeData);
        $this->repository->method('deleteById')->willThrowException(new LockWaitTimeoutException($this->driverException(1205, 'HY000'), null));
        $controller = $this->controller($this->form(null));

        $conflict = $this->catchConflict(static fn (): RedirectResponse => $controller->delete(1, Request::create('/performance/1/delete', 'POST')));

        self::assertSame(WriteConflictException::CODE_RETRY_SAFE, $conflict->errorCode);
    }

    public function testReviewRunsInOneTransactionAndAConflictAnswersTheHostPolicy(): void
    {
        $routeData = (new RouteData())->setName('app_home')->setEnv('test');
        $this->repository->method('find')->willReturn($routeData);
        $this->repository->method('markAsReviewed')->willThrowException($this->deadlock());
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::never())->method('commit');
        $this->metricsService->method('getConnection')->willReturn($connection);
        $controller = $this->controller(
            $this->form(['queries_improved' => '1', 'time_improved' => '0', 'save_access_records' => true]),
            new WriteConflictMapper(new ConflictResponsePolicy(503, 5)),
        );

        $conflict = $this->catchConflict(static fn (): RedirectResponse => $controller->review(1, Request::create('/performance/1/review', 'POST')));

        self::assertSame(503, $conflict->getStatusCode());
        self::assertSame(WriteConflictException::CODE_RETRY_SAFE, $conflict->errorCode);
        self::assertSame('5', $conflict->getHeaders()['Retry-After'], 'The host policy gives Retry-After.');
    }

    public function testReviewSavesTheAccessRecordsFlagInTheSameTransaction(): void
    {
        $routeData = (new RouteData())->setName('app_home')->setEnv('test');
        $this->repository->method('find')->willReturn($routeData);
        $this->repository->method('markAsReviewed')->willReturn(true);
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('commit');
        $this->metricsService->method('getConnection')->willReturn($connection);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');
        $this->metricsService->method('getEntityManager')->willReturn($entityManager);
        $controller = $this->controller($this->form(['queries_improved' => '', 'time_improved' => '', 'save_access_records' => false]));

        $controller->review(1, Request::create('/performance/1/review', 'POST'));

        self::assertFalse($routeData->getSaveAccessRecords());
    }

    public function testPurgeThatAlreadyDeletedABatchAnswersConflict002AndClearsTheCache(): void
    {
        $this->recordRepository->method('deleteAllRecords')->willThrowException($this->committedConflict());
        $this->cacheService->expects(self::once())->method('clearStatistics')->with('');
        $this->cacheService->expects(self::once())->method('clearEnvironments');
        $controller = $this->controller($this->form(new PurgeAccessRecordsRequest('', PurgeAccessRecordsRequest::PURGE_ALL, 30)));

        $conflict = $this->catchConflict(static fn (): RedirectResponse => $controller->purgeRecords(Request::create('/performance/purge-records', 'POST')));

        self::assertSame(409, $conflict->getStatusCode());
        self::assertSame(WriteConflictException::CODE_PARTIALLY_SAVED, $conflict->errorCode);
        self::assertArrayNotHasKey('Retry-After', $conflict->getHeaders());
    }

    public function testDeleteByFilterThatAlreadyDeletedABatchAnswersConflict002AndClearsTheCache(): void
    {
        $this->recordRepository->method('deleteByFilter')->willThrowException($this->committedConflict());
        $this->cacheService->expects(self::once())->method('clearStatistics')->with('test');
        $controller = $this->controller($this->form(new DeleteRecordsByFilterRequest('test', 'access_records')));

        $conflict = $this->catchConflict(static fn (): RedirectResponse => $controller->deleteRecordsByFilter(Request::create('/performance/delete-records-by-filter', 'POST')));

        self::assertSame(WriteConflictException::CODE_PARTIALLY_SAVED, $conflict->errorCode);
    }

    public function testPurgeConflictBeforeAnyCommitAnswersConflict001WithoutClearingTheCache(): void
    {
        $this->recordRepository->method('deleteOlderThan')->willThrowException($this->deadlock());
        $this->cacheService->expects(self::never())->method('clearStatistics');
        $controller = $this->controller($this->form(new PurgeAccessRecordsRequest('test', PurgeAccessRecordsRequest::PURGE_OLDER_THAN, 7)));

        $conflict = $this->catchConflict(static fn (): RedirectResponse => $controller->purgeRecords(Request::create('/performance/purge-records', 'POST')));

        self::assertSame(WriteConflictException::CODE_RETRY_SAFE, $conflict->errorCode);
    }

    /**
     * @param callable(): mixed $action
     */
    private function catchConflict(callable $action): WriteConflictException
    {
        try {
            $action();
        } catch (WriteConflictException $conflict) {
            self::assertSame('no-store, private', $conflict->getHeaders()['Cache-Control']);

            return $conflict;
        }

        self::fail('A WriteConflictException was expected.');
    }

    private function form(mixed $data): FormInterface
    {
        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn($data);

        return $form;
    }

    private function controller(FormInterface $form, ?WriteConflictMapper $mapper = null): PerformanceController
    {
        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                'metricsService'         => $this->metricsService,
                'analysisService'        => null,
                'enabled'                => true,
                'cacheService'           => $this->cacheService,
                'enableRecordManagement' => true,
                'enableReviewSystem'     => true,
                'recordRepository'       => $this->recordRepository,
                'enableAccessRecords'    => true,
                'accessChecker'          => new AllowAllPerformanceAccessChecker(),
                'conflictMapper'         => $mapper,
            ])
            ->onlyMethods(['createForm', 'getParameter', 'addFlash', 'redirectToRoute', 'getUser'])
            ->getMock();
        $controller->method('createForm')->willReturn($form);
        $controller->method('getParameter')->willReturn('test');
        $controller->method('getUser')->willReturn(null);
        $controller->method('addFlash')->willReturnCallback(static function (string $type, mixed $message): void {
            if ($type === 'error') {
                throw new RuntimeException((string) $message);
            }
        });
        $controller->method('redirectToRoute')->willReturn(new RedirectResponse('/performance'));

        return $controller;
    }

    private function deadlock(): DeadlockException
    {
        return new DeadlockException($this->driverException(1213, '40001'), null);
    }

    private function committedConflict(): ConcurrencyConflict
    {
        return new ConcurrencyConflict(1, TransientFailureKind::Deadlock, null, new RuntimeException('storage'), true);
    }

    private function driverException(int $code, string $sqlState): AbstractException
    {
        return new class('Simulated driver failure', $sqlState, $code) extends AbstractException {
        };
    }
}
