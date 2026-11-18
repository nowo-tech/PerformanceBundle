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
use Nowo\PerformanceBundle\Service\RouteDataDeduplicator;
use Nowo\PerformanceBundle\Tests\Support\HookedConnection;
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
    public function testARealLockWaitIsReplayedByTheAsyncPathOnlyThenReportedAndTheConnectionStaysUsable(string $driver): void
    {
        $em      = $this->connectBoth($driver);
        $service = $this->service($em);
        $service->recordMetrics('app_locked', 'prod', 0.1, 1, null, null, null, 'GET', 200, [], 'seed');
        $this->shortLockWait($this->worker());

        $other = $this->other();
        $other->beginTransaction();
        $other->fetchOne("SELECT id FROM routes_data WHERE name = 'app_locked' FOR UPDATE");

        try {
            // kernel.terminate path: a lock wait timeout is not replayed (the worker already waited once).
            try {
                $service->recordMetrics('app_locked', 'prod', 0.1, 1, null, null, null, 'GET', 200, [], 'blocked');
                self::fail('The lock conflict was expected.');
            } catch (TransientStorageFailure) {
                self::assertSame([], $service->pauses);
            }

            // Messenger handler path: replayed, then rethrown for the transport's retry.
            $service->recordMetricsSync('app_locked', 'prod', 0.1, 1, null, null, null, 'GET', 200, 'blocked');
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
        $this->dropUniqueIndex($driver);

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

    #[DataProvider('databases')]
    public function testAMergeUnderALiveWriteLosesNoAccessRecord(string $driver): void
    {
        $em     = $this->connectBoth($driver);
        $worker = $this->worker();
        $this->dropUniqueIndex($driver);
        $keep = $this->insertRoute($worker, 'home', 'prod', '2026-01-01 00:00:00');
        $dup  = $this->insertRoute($worker, 'home', 'prod', '2026-02-01 00:00:00');
        $this->insertRecord($worker, $dup, 'old-1');

        $other   = $this->other();
        $outcome = 'not run';
        $this->shortLockWait($other);
        // A live worker records a request for the duplicate right before the merge deletes it.
        HookedConnection::$hooks['DELETE FROM routes_data '] = function () use ($other, $dup, &$outcome): bool {
            try {
                $other->beginTransaction();
                $this->insertRecord($other, $dup, 'live-1', '2026-12-01 00:00:00');
                $other->commit();
                $outcome = 'committed';
            } catch (Throwable) {
                if ($other->isTransactionActive()) {
                    $other->rollBack();
                }
                $outcome = 'failed';
            }

            return false;
        };

        self::assertSame(1, (new RouteDataDeduplicator())->merge($em));

        // The group is locked: the live write waits and fails loudly (its unit replays on the kept row); it is never
        // committed and then cascade-deleted with the duplicate.
        self::assertSame('failed', $outcome);
        self::assertSame(1, $this->rows('SELECT COUNT(*) FROM routes_data_records'));
        self::assertSame(1, $this->rows('SELECT COUNT(*) FROM routes_data_records WHERE route_data_id = ?', [$keep]));
        self::assertSame(0, $worker->getTransactionNestingLevel());
    }

    #[DataProvider('databases')]
    public function testAMergeKeepsTheReviewAndTheDoNotSaveChoiceOfTheMergedRows(string $driver): void
    {
        $em     = $this->connectBoth($driver);
        $worker = $this->worker();
        $this->dropUniqueIndex($driver);
        $keep = $this->insertRoute($worker, 'home', 'prod', '2026-01-01 00:00:00');
        $old  = $this->insertRoute($worker, 'home', 'prod', '2026-03-01 00:00:00');
        $new  = $this->insertRoute($worker, 'home', 'prod', '2026-02-01 00:00:00');
        $this->review($worker, $old, '2026-02-01 00:00:00', 'alice', true, false, false);
        $this->review($worker, $new, '2026-04-01 00:00:00', 'bob', false, true, true);

        self::assertSame(2, (new RouteDataDeduplicator())->merge($em));

        $row = $this->other()->fetchAssociative('SELECT * FROM routes_data WHERE id = ?', [$keep]);
        self::assertIsArray($row);
        $bool = static fn (mixed $value): ?bool => $value === null ? null : (bool) $value;
        self::assertTrue($bool($row['reviewed']), 'reviewed is OR-ed over the group.');
        self::assertStringStartsWith('2026-04-01 00:00:00', (string) $row['reviewed_at'], 'The most recent review wins.');
        self::assertSame('bob', $row['reviewed_by']);
        self::assertFalse($bool($row['queries_improved']));
        self::assertTrue($bool($row['time_improved']));
        self::assertFalse($bool($row['save_access_records']), 'One "do not save access records" wins.');
        self::assertStringStartsWith('2026-03-01 00:00:00', (string) $row['last_accessed_at']);
    }

    #[DataProvider('databases')]
    public function testAForeignKeyViolationIsReplayedOnTheCurrentRouteRow(string $driver): void
    {
        $em    = $this->connectBoth($driver);
        $other = $this->other();
        $this->insertRoute($this->worker(), 'app_fk');
        $factory = new ScriptedMetricsEntityManagerFactory([
            // The route row read by this worker is merged away (deleted) before its access record is inserted.
            ['onFlush' => static fn (): int|string => $other->executeStatement("DELETE FROM routes_data WHERE name = 'app_fk'")],
        ]);
        $service = $this->service($em, $factory);

        $service->recordMetrics('app_fk', 'prod', 0.2, 1, null, null, null, 'GET', 200, [], 'req-fk');

        self::assertSame(2, $factory->created);
        self::assertSame(1, $this->rows("SELECT COUNT(*) FROM routes_data WHERE name = 'app_fk'"));
        self::assertSame(1, $this->rows("SELECT COUNT(*) FROM routes_data_records rec INNER JOIN routes_data r ON r.id = rec.route_data_id WHERE r.name = 'app_fk' AND rec.request_id = 'req-fk'"));
        $this->assertWorkerUsable($service);
    }

    #[DataProvider('databases')]
    public function testCreateTableMergesAgainWhenLiveTrafficAddsADuplicateBeforeTheUniqueIndex(string $driver): void
    {
        $em = $this->connectBoth($driver);
        $this->dropUniqueIndex($driver);
        $this->insertRoute($this->worker(), 'home');
        $this->insertRoute($this->worker(), 'home');
        $other                                          = $this->other();
        HookedConnection::$hooks['CREATE UNIQUE INDEX'] = function () use ($other): bool {
            $this->insertRoute($other, 'home');

            return false;
        };

        $tester = $this->createTable($em);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('attempt 1 of 2', preg_replace('/\s+/', ' ', $tester->getDisplay()) ?? '');
        self::assertSame(1, $this->rows("SELECT COUNT(*) FROM routes_data WHERE name = 'home'"));
        self::assertTrue($this->worker()->createSchemaManager()->introspectTable('routes_data')->getIndex('uniq_route_env_name')->isUnique());
    }

    #[DataProvider('databases')]
    public function testCreateTableFailsWhenTheUniqueIndexCannotBeCreated(string $driver): void
    {
        $em = $this->connectBoth($driver);
        $this->dropUniqueIndex($driver);
        $other                                          = $this->other();
        HookedConnection::$hooks['CREATE UNIQUE INDEX'] = function () use ($other): bool {
            // Two live inserts of the same new route, every time: the unique index can never be created.
            $this->insertRoute($other, 'busy');
            $this->insertRoute($other, 'busy');

            return true;
        };

        $tester = $this->createTable($em);

        self::assertSame(Command::FAILURE, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('could not be created', preg_replace('/\s+/', ' ', $tester->getDisplay()) ?? '');
        self::assertFalse($this->worker()->createSchemaManager()->introspectTable('routes_data')->hasIndex('uniq_route_env_name'));
    }

    private function createTable(EntityManager $em): CommandTester
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($em);
        $registry->method('getConnection')->willReturn($this->worker());
        $tester = new CommandTester(new CreateTableCommand($registry, 'default', 'routes_data', true));
        $tester->execute(['--update' => true]);

        return $tester;
    }

    private function dropUniqueIndex(string $driver): void
    {
        $this->worker()->executeStatement($driver === 'postgresql' ? 'DROP INDEX uniq_route_env_name' : 'DROP INDEX uniq_route_env_name ON routes_data');
    }

    private function review(Connection $connection, int $id, string $at, string $by, bool $queries, bool $time, bool $save): void
    {
        $connection->update('routes_data', [
            'reviewed'            => true,
            'reviewed_at'         => $at,
            'reviewed_by'         => $by,
            'queries_improved'    => $queries,
            'time_improved'       => $time,
            'save_access_records' => $save,
        ], ['id' => $id], ['reviewed' => 'boolean', 'queries_improved' => 'boolean', 'time_improved' => 'boolean', 'save_access_records' => 'boolean']);
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
