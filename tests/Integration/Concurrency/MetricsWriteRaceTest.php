<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Integration\Concurrency;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManager;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Nowo\PerformanceBundle\Command\CreateTableCommand;
use Nowo\PerformanceBundle\Concurrency\WriteConflictException;
use Nowo\PerformanceBundle\Concurrency\WriteConflictMapper;
use Nowo\PerformanceBundle\Entity\RouteDataRecord;
use Nowo\PerformanceBundle\Repository\RouteDataRecordRepository;
use Nowo\PerformanceBundle\Tests\Support\PausingMetricsService;
use Nowo\PerformanceBundle\Tests\Support\ScriptedMetricsEntityManagerFactory;
use Nowo\TransactionContract\TransientStorageFailure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Throwable;

use function array_column;
use function str_contains;

/**
 * Two-connection race tests of the metrics write path on MySQL 8.4, MariaDB 11 and PostgreSQL 16 (REQ-RUNTIME-003):
 * the second connection commits the competing row at the worst moment (between this worker's read and its insert),
 * a real row lock makes the worker's write wait, and the unique constraint of 3.18.0 is added over duplicate rows.
 * Each test also checks that the worker's connection is usable afterwards (no transaction left open).
 */
final class MetricsWriteRaceTest extends TestCase
{
    use TwoConnectionsTrait;

    /** @var list<array{string, string}> */
    private array $logs = [];

    protected function tearDown(): void
    {
        $this->dropBoth();
    }

    #[DataProvider('databases')]
    public function testTwoFirstRequestsOnTheSameNewRouteGiveExactlyOneRouteData(string $driver): void
    {
        $em      = $this->connectBoth($driver);
        $other   = $this->other();
        $factory = new ScriptedMetricsEntityManagerFactory([
            // The other worker inserts and commits the same new route after this worker looked it up.
            ['onFlush' => fn (): int => $this->insertRoute($other, 'app_new')],
        ]);
        $service = $this->service($em, $factory);

        $result = $service->recordMetrics('app_new', 'prod', 0.2, 3, 0.01, null, 2048, 'GET', 200, [], 'req-1');

        self::assertFalse($result['is_new'], 'The row committed by the other worker is re-read.');
        self::assertSame(2, $factory->created);
        self::assertSame(1, $this->rows("SELECT COUNT(*) FROM routes_data WHERE env = 'prod' AND name = 'app_new'"));
        self::assertSame(1, $this->rows("SELECT COUNT(*) FROM routes_data_records rec INNER JOIN routes_data r ON r.id = rec.route_data_id WHERE r.name = 'app_new' AND rec.request_id = 'req-1'"));
        $this->assertWorkerUsable($service);
    }

    #[DataProvider('databases')]
    public function testTheUniqueConstraintRejectsASecondRowForTheSameRoute(string $driver): void
    {
        $this->connectBoth($driver);
        $this->insertRoute($this->other(), 'app_unique');

        $this->expectException(UniqueConstraintViolationException::class);
        $this->insertRoute($this->worker(), 'app_unique');
    }

    #[DataProvider('databases')]
    public function testDuplicateRequestIdStillSavesTheOtherMetrics(string $driver): void
    {
        $em      = $this->connectBoth($driver);
        $other   = $this->other();
        $factory = new ScriptedMetricsEntityManagerFactory([
            ['onFlush' => fn () => $this->insertRecord($other, $this->insertRoute($other, 'other_route'), 'req-dup')],
        ]);
        $service = $this->service($em, $factory);

        $result = $service->recordMetrics('app_dup', 'prod', 0.2, 3, 0.01, null, 2048, 'GET', 200, [], 'req-dup');

        self::assertTrue($result['is_new']);
        self::assertSame(1, $this->rows("SELECT COUNT(*) FROM routes_data WHERE name = 'app_dup'"), 'The route and its metrics are saved.');
        self::assertSame(1, $this->rows("SELECT COUNT(*) FROM routes_data_records WHERE request_id = 'req-dup'"));
        self::assertContains('warning', array_column($this->logs, 0));
        $this->assertWorkerUsable($service);
    }

    #[DataProvider('databases')]
    public function testARealLockWaitIsReplayedThenReportedAndTheConnectionStaysUsable(string $driver): void
    {
        $em      = $this->connectBoth($driver);
        $service = $this->service($em);
        $service->recordMetrics('app_locked', 'prod', 0.1, 1, null, null, null, 'GET', 200, [], 'seed');
        $this->shortLockWait($this->worker());

        $other = $this->other();
        $other->beginTransaction();
        $other->fetchOne("SELECT id FROM routes_data WHERE name = 'app_locked' FOR UPDATE");

        try {
            $service->recordMetrics('app_locked', 'prod', 0.1, 1, null, null, null, 'GET', 200, [], 'blocked');
            self::fail('The lock conflict was expected.');
        } catch (TransientStorageFailure $e) {
            $answer = (new WriteConflictMapper())->toHttpException($e);
            self::assertSame(409, $answer->getStatusCode());
            self::assertSame(WriteConflictException::CODE_RETRY_SAFE, $answer->errorCode);
            self::assertSame('1', $answer->getHeaders()['Retry-After']);
        } finally {
            $other->rollBack();
        }

        self::assertSame([1, 2], $service->pauses);
        self::assertSame(0, $this->rows("SELECT COUNT(*) FROM routes_data_records WHERE request_id = 'blocked'"), 'Nothing half-saved.');
        $this->assertWorkerUsable($service);
    }

    #[DataProvider('databases')]
    public function testChunkedPurgeThatConflictsAfterACommittedBatchAnswersConflict002(string $driver): void
    {
        $em    = $this->connectBoth($driver);
        $route = $this->insertRoute($this->worker(), 'app_purge');
        foreach (['2026-01-01 00:00:01', '2026-01-01 00:00:02', '2026-01-01 00:00:03'] as $accessedAt) {
            $this->insertRecord($this->worker(), $route, null, $accessedAt);
        }
        $lastId = (int) $this->other()->fetchOne('SELECT MAX(id) FROM routes_data_records');
        $this->shortLockWait($this->worker());

        $other = $this->other();
        $other->beginTransaction();
        $other->fetchOne('SELECT id FROM routes_data_records WHERE id = ? FOR UPDATE', [$lastId]);

        $repository = $em->getRepository(RouteDataRecord::class);
        self::assertInstanceOf(RouteDataRecordRepository::class, $repository);

        try {
            $repository->deleteOlderThan(new DateTimeImmutable('2026-06-01'), null, 2);
            self::fail('The lock conflict was expected.');
        } catch (Throwable $e) {
            $answer = (new WriteConflictMapper())->toHttpException($e);
            self::assertSame(WriteConflictException::CODE_PARTIALLY_SAVED, $answer->errorCode);
            self::assertSame(409, $answer->getStatusCode());
            self::assertArrayNotHasKey('Retry-After', $answer->getHeaders());
        } finally {
            $other->rollBack();
        }

        self::assertSame(1, $this->rows('SELECT COUNT(*) FROM routes_data_records'), 'The first batch was committed.');
        self::assertSame(1, $repository->deleteOlderThan(new DateTimeImmutable('2026-06-01'), null, 2), 'The connection is still usable.');
    }

    #[DataProvider('databases')]
    public function testSyncSchemaMergesDuplicateRoutesBeforeAddingTheUniqueConstraint(string $driver): void
    {
        $em     = $this->connectBoth($driver);
        $worker = $this->worker();
        $worker->executeStatement($driver === 'postgresql' ? 'DROP INDEX uniq_route_env_name' : 'DROP INDEX uniq_route_env_name ON routes_data');

        $keep = $this->insertRoute($worker, 'home', 'prod', '2026-01-01 00:00:00');
        $dup1 = $this->insertRoute($worker, 'home', 'prod', '2026-03-01 00:00:00');
        $dup2 = $this->insertRoute($worker, 'home', 'prod', '2026-02-01 00:00:00');
        $dev  = $this->insertRoute($worker, 'home', 'dev', '2026-01-01 00:00:00');
        foreach ([$keep, $dup1, $dup1, $dup2, $dev] as $i => $routeId) {
            $this->insertRecord($worker, $routeId, 'req-' . $i);
        }

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($em);
        $registry->method('getConnection')->willReturn($worker);
        $tester = new CommandTester(new CreateTableCommand($registry, 'default', 'routes_data', true));
        $tester->execute(['--update' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Merged 2 duplicate route row(s)', $tester->getDisplay());
        self::assertStringContainsString('Created unique index uniq_route_env_name', $tester->getDisplay());
        self::assertSame([$keep], array_map(intval(...), $this->other()->fetchFirstColumn("SELECT id FROM routes_data WHERE env = 'prod' AND name = 'home'")));
        self::assertSame(4, $this->rows('SELECT COUNT(*) FROM routes_data_records WHERE route_data_id = ?', [$keep]), 'The records of the duplicates were moved.');
        self::assertSame(1, $this->rows('SELECT COUNT(*) FROM routes_data_records WHERE route_data_id = ?', [$dev]));
        self::assertStringStartsWith('2026-03-01 00:00:00', (string) $this->other()->fetchOne('SELECT last_accessed_at FROM routes_data WHERE id = ?', [$keep]));

        $index = $worker->createSchemaManager()->introspectTable('routes_data')->getIndex('uniq_route_env_name');
        self::assertTrue($index->isUnique());

        $this->expectException(UniqueConstraintViolationException::class);
        $this->insertRoute($worker, 'home', 'prod');
    }

    private function service(EntityManager $em, ?ScriptedMetricsEntityManagerFactory $factory = null): PausingMetricsService
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($em);
        $logger = new class($this->logs) extends AbstractLogger {
            /** @param list<array{string, string}> $logs */
            public function __construct(private array &$logs)
            {
            }

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->logs[] = [(string) $level, (string) $message];
            }
        };

        return new PausingMetricsService($registry, 'default', false, true, false, $logger, new MockClock('2026-11-18 10:00:00'), $factory ?? new ScriptedMetricsEntityManagerFactory([]));
    }

    private function assertWorkerUsable(PausingMetricsService $service): void
    {
        self::assertSame(0, $this->worker()->getTransactionNestingLevel());
        $service->recordMetrics('after_race', 'prod', 0.1, 1, null, null, null, 'GET', 200, [], 'after-' . substr(md5($this->name()), 0, 16));
        self::assertSame(1, $this->rows("SELECT COUNT(*) FROM routes_data WHERE name = 'after_race'"));
        foreach ($this->logs as [, $message]) {
            self::assertFalse(str_contains($message, 'SELECT'), 'No SQL in the logs.');
        }
    }

    private function worker(): Connection
    {
        return $this->worker ?? throw new LogicException('connectBoth() first.');
    }

    private function other(): Connection
    {
        return $this->other ?? throw new LogicException('connectBoth() first.');
    }
}
