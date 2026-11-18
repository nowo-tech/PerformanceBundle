<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\AbstractException;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManager;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\Event\AfterMetricsRecordedEvent;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use Nowo\PerformanceBundle\Tests\Support\OrmTestSupport;
use Nowo\PerformanceBundle\Tests\Support\PausingMetricsService;
use Nowo\PerformanceBundle\Tests\Support\ScriptedMetricsEntityManagerFactory;
use Nowo\TransactionContract\TransientStorageFailure;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use ReflectionProperty;
use RuntimeException;
use Stringable;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;

use function sprintf;

/**
 * The metrics write unit under contention (REQ-RUNTIME-003), on SQLite: a unique violation of uniq_route_env_name or
 * uniq_record_request_id makes the unit re-read what the other worker committed, a lock conflict is replayed, and
 * nothing is lost silently. The two-connection versions of these races run on MySQL, MariaDB and PostgreSQL in
 * tests/Integration/Concurrency.
 */
final class PerformanceMetricsServiceConcurrencyTest extends TestCase
{
    private EntityManager $hostEm;
    private Connection $connection;

    /** @var list<array{string, string, array<string, mixed>}> */
    private array $logs = [];

    protected function setUp(): void
    {
        $this->hostEm     = OrmTestSupport::entityManager($this);
        $this->connection = $this->hostEm->getConnection();
    }

    public function testConcurrentFirstRequestOfARouteReReadsTheCommittedRow(): void
    {
        $factory = new ScriptedMetricsEntityManagerFactory([
            // Attempt 1: the other worker's row is there when this one inserts (a real unique violation).
            ['onFlush' => fn (Connection $c): int => $this->insertRoute($c, 'app_home')],
            // Attempt 2: the other worker committed it.
            ['meanwhile' => fn (Connection $c): int => $this->insertRoute($c, 'app_home')],
        ]);
        $service = $this->service($factory);

        $result = $service->recordMetrics('app_home', 'prod', 0.2, 3, 0.01, null, 2048, 'GET', 200, [], 'req-a');

        self::assertSame(['is_new' => false, 'was_updated' => true], $result);
        self::assertSame(2, $factory->created);
        self::assertSame(1, $this->rows('SELECT COUNT(*) FROM routes_data WHERE name = ?', ['app_home']));
        self::assertSame(1, $this->rows('SELECT COUNT(*) FROM routes_data_records rec INNER JOIN routes_data r ON r.id = rec.route_data_id WHERE r.name = ? AND rec.request_id = ?', ['app_home', 'req-a']));
        self::assertSame(0, $this->connection->getTransactionNestingLevel());
        self::assertLogged('notice', 'running the write unit again', UniqueConstraintViolationException::class);
        self::assertNotLogged('warning');
    }

    public function testDuplicateRequestIdStillSavesTheRouteMetricsAndWarns(): void
    {
        $other   = fn (Connection $c) => $this->insertRecord($c, $this->insertRoute($c, 'other_route'), 'req-dup');
        $factory = new ScriptedMetricsEntityManagerFactory([
            ['onFlush' => $other],
            ['meanwhile' => $other],
        ]);
        $service = $this->service($factory);

        $result = $service->recordMetrics('app_dup', 'prod', 0.2, 3, 0.01, null, 2048, 'GET', 200, [], 'req-dup');

        self::assertTrue($result['is_new']);
        self::assertSame(1, $this->rows('SELECT COUNT(*) FROM routes_data WHERE name = ?', ['app_dup']), 'The route metrics are saved.');
        self::assertSame(1, $this->rows('SELECT COUNT(*) FROM routes_data_records WHERE request_id = ?', ['req-dup']));
        self::assertLogged('warning', 'same request id');
    }

    public function testUniqueConflictsOnEveryEarlierAttemptSaveTheRouteWithoutTheAccessRecord(): void
    {
        $factory = new ScriptedMetricsEntityManagerFactory([
            ['throw' => $this->uniqueViolation()],
            ['throw' => $this->uniqueViolation()],
        ]);
        $service = $this->service($factory);

        $result = $service->recordMetrics('app_stubborn', 'prod', 0.2, 3, 0.01, null, 2048, 'GET', 200, [], 'req-s');

        self::assertTrue($result['is_new']);
        self::assertSame(3, $factory->created);
        self::assertSame(1, $this->rows('SELECT COUNT(*) FROM routes_data WHERE name = ?', ['app_stubborn']));
        self::assertSame(0, $this->rows('SELECT COUNT(*) FROM routes_data_records'));
        self::assertLogged('warning', 'same request id');
    }

    public function testUniqueConflictOnTheLastAttemptIsRethrown(): void
    {
        $factory = new ScriptedMetricsEntityManagerFactory([
            ['throw' => $this->uniqueViolation()],
            ['throw' => $this->uniqueViolation()],
            ['throw' => $this->uniqueViolation()],
        ]);
        $service = $this->service($factory);

        try {
            $service->recordMetrics('app_lost', 'prod', 0.2);
            self::fail('The unique violation was expected.');
        } catch (UniqueConstraintViolationException) {
        }

        self::assertSame(3, $factory->created);
        self::assertSame(0, $this->rows('SELECT COUNT(*) FROM routes_data'));
        self::assertSame(0, $this->connection->getTransactionNestingLevel());
    }

    public function testDeadlockIsReplayedAfterAPause(): void
    {
        $factory = new ScriptedMetricsEntityManagerFactory([
            ['throw' => new DeadlockException($this->driverException(1213, '40001'), null)],
        ]);
        $service = $this->service($factory);

        $result = $service->recordMetrics('app_deadlock', 'prod', 0.2, 1, null, null, null, 'GET', 200, [], 'req-d');

        self::assertTrue($result['is_new']);
        self::assertSame([1], $service->pauses);
        self::assertSame(1, $this->rows('SELECT COUNT(*) FROM routes_data_records WHERE request_id = ?', ['req-d']));
        self::assertLogged('notice', 'running the write unit again', TransientStorageFailure::class);
    }

    public function testAsyncPathReplaysALockWaitTimeoutThenThrowsTheTransientFailure(): void
    {
        $timeout = fn (): LockWaitTimeoutException => new LockWaitTimeoutException($this->driverException(1205, 'HY000'), null);
        $factory = new ScriptedMetricsEntityManagerFactory([
            ['throw' => $timeout()],
            ['throw' => $timeout()],
            ['throw' => $timeout()],
        ]);
        $service = $this->service($factory);

        try {
            $service->recordMetricsSync('app_locked', 'prod', 0.2);
            self::fail('The transient failure was expected.');
        } catch (TransientStorageFailure $e) {
            self::assertInstanceOf(LockWaitTimeoutException::class, $e->getPrevious());
        }

        self::assertSame([1, 2], $service->pauses);
        self::assertSame(0, $this->rows('SELECT COUNT(*) FROM routes_data'));
        self::assertSame(0, $this->connection->getTransactionNestingLevel());
    }

    public function testTerminatePathDoesNotReplayALockWaitTimeout(): void
    {
        $factory = new ScriptedMetricsEntityManagerFactory([
            ['throw' => new LockWaitTimeoutException($this->driverException(1205, 'HY000'), null)],
        ]);
        $service = $this->service($factory);

        try {
            $service->recordMetrics('app_locked', 'prod', 0.2);
            self::fail('The transient failure was expected.');
        } catch (TransientStorageFailure) {
        }

        self::assertSame(1, $factory->created);
        self::assertSame([], $service->pauses);
    }

    public function testForeignKeyViolationIsReplayedLikeAUniqueOne(): void
    {
        $factory = new ScriptedMetricsEntityManagerFactory([
            ['throw' => new ForeignKeyConstraintViolationException($this->driverException(1452, '23000'), null)],
        ]);
        $service = $this->service($factory);

        $service->recordMetrics('app_fk', 'prod', 0.2, 1, null, null, null, 'GET', 200, [], 'req-fk');

        self::assertSame(2, $factory->created);
        self::assertSame([], $service->pauses);
        self::assertSame(1, $this->rows('SELECT COUNT(*) FROM routes_data_records WHERE request_id = ?', ['req-fk']));
        self::assertLogged('notice', 'running the write unit again', ForeignKeyConstraintViolationException::class);
    }

    public function testThePrivateManagerIsClearedEvenWhenAnAfterCommitListenerThrows(): void
    {
        $factory    = new ScriptedMetricsEntityManagerFactory([]);
        $service    = $this->service($factory);
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(AfterMetricsRecordedEvent::class, static function (): never {
            throw new RuntimeException('listener failure');
        });
        $service->setEventDispatcher($dispatcher);

        try {
            $service->recordMetrics('app_listener', 'prod', 0.2);
            self::fail('The listener failure was expected.');
        } catch (RuntimeException $e) {
            self::assertSame('listener failure', $e->getMessage());
        }

        $writeEm = (new ReflectionProperty(PerformanceMetricsService::class, 'writeEntityManager'))->getValue($service);
        self::assertInstanceOf(EntityManager::class, $writeEm);
        self::assertSame([], $writeEm->getUnitOfWork()->getIdentityMap(), 'No stale RouteData kept for the next request.');
        self::assertSame(1, $this->rows('SELECT COUNT(*) FROM routes_data WHERE name = ?', ['app_listener']), 'Committed before the listener ran.');
    }

    public function testInsideAHostTransactionAConflictIsNotReplayed(): void
    {
        $factory = new ScriptedMetricsEntityManagerFactory([
            ['throw' => new DeadlockException($this->driverException(1213, '40001'), null)],
        ]);
        $service = $this->service($factory);
        $this->connection->beginTransaction();

        try {
            $service->recordMetrics('app_nested', 'prod', 0.2);
            self::fail('The transient failure was expected.');
        } catch (TransientStorageFailure) {
        } finally {
            self::assertSame(1, $this->connection->getTransactionNestingLevel(), 'The host transaction is left to the host.');
            $this->connection->rollBack();
        }

        self::assertSame(1, $factory->created);
        self::assertSame([], $service->pauses);
    }

    private function service(ScriptedMetricsEntityManagerFactory $factory): PausingMetricsService
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($this->hostEm);
        $logger = new class($this->logs) extends AbstractLogger {
            /** @param list<array{string, string, array<string, mixed>}> $logs */
            public function __construct(private array &$logs)
            {
            }

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->logs[] = [(string) $level, (string) $message, $context];
            }
        };

        return new PausingMetricsService($registry, 'default', false, true, true, $logger, new MockClock('2026-11-18 10:00:00'), $factory);
    }

    private function insertRoute(Connection $connection, string $name): int
    {
        $connection->insert('routes_data', ['name' => $name, 'env' => 'prod', 'created_at' => '2026-11-18 09:00:00', 'reviewed' => 0, 'save_access_records' => 1]);

        return (int) $connection->lastInsertId();
    }

    private function insertRecord(Connection $connection, int $routeId, string $requestId): void
    {
        $connection->insert('routes_data_records', ['route_data_id' => $routeId, 'accessed_at' => '2026-11-18 09:00:00', 'request_id' => $requestId]);
    }

    /**
     * @param list<mixed> $params
     */
    private function rows(string $sql, array $params = []): int
    {
        return (int) $this->connection->fetchOne($sql, $params);
    }

    private function uniqueViolation(): UniqueConstraintViolationException
    {
        return new UniqueConstraintViolationException($this->driverException(1062, '23000'), null);
    }

    private function driverException(int $code, string $sqlState): AbstractException
    {
        return new class('Simulated driver failure', $sqlState, $code) extends AbstractException {
        };
    }

    private function assertLogged(string $level, string $text, ?string $exceptionClass = null): void
    {
        foreach ($this->logs as [$logLevel, $message, $context]) {
            if ($logLevel === $level && str_contains($message, $text) && ($exceptionClass === null || ($context['exception'] ?? null) === $exceptionClass)) {
                $this->addToAssertionCount(1);

                return;
            }
        }

        self::fail(sprintf('No %s log containing "%s" (%s).', $level, $text, json_encode($this->logs)));
    }

    private function assertNotLogged(string $level): void
    {
        foreach ($this->logs as [$logLevel, $message]) {
            self::assertNotSame($level, $logLevel, $message);
        }
    }
}
