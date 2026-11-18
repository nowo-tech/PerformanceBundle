<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Concurrency;

use Doctrine\DBAL\Driver\AbstractException;
use Doctrine\DBAL\Exception\ConnectionLost;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Nowo\PerformanceBundle\Concurrency\WriteConflictException;
use Nowo\PerformanceBundle\Concurrency\WriteConflictMapper;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\TransactionDoctrine\Symfony\Conflict\ConflictResponsePolicy;
use Nowo\TransactionDoctrine\TransientFailureKind;
use Nowo\TransactionDoctrine\WriteUnit\ConcurrencyConflict;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/**
 * Lock conflicts of dashboard writes answer 409 + Retry-After + CONFLICT_001, or CONFLICT_002 without Retry-After when
 * part of the work was committed; other database errors are not mapped (REQ-RUNTIME-003 rule 6).
 */
final class WriteConflictMapperTest extends TestCase
{
    /**
     * @return iterable<string, array{Throwable}>
     */
    public static function conflicts(): iterable
    {
        yield 'MySQL deadlock 1213' => [new DeadlockException(self::driverException(1213, '40001'), null)];
        yield 'MySQL lock wait timeout 1205' => [new LockWaitTimeoutException(self::driverException(1205, 'HY000'), null)];
        yield 'MariaDB 1020' => [new DriverException(self::driverException(1020, 'HY000'), null)];
        yield 'PostgreSQL serialization failure 40001' => [new DriverException(self::driverException(7, '40001'), null)];
        yield 'PostgreSQL deadlock 40P01' => [new DriverException(self::driverException(7, '40P01'), null)];
        yield 'PostgreSQL lock not available 55P03' => [new RuntimeException('wrapped', 0, new DriverException(self::driverException(7, '55P03'), null))];
        yield 'runner transient failure' => [TransientStorageFailure::fromPrevious(new RuntimeException('storage'))];
    }

    #[DataProvider('conflicts')]
    public function testLockConflictsAnswer409WithRetryAfterAndConflict001(Throwable $failure): void
    {
        $mapper = new WriteConflictMapper();
        self::assertTrue($mapper->isConflict($failure));

        $answer = $mapper->toHttpException($failure);

        self::assertSame(409, $answer->getStatusCode());
        self::assertSame(WriteConflictException::CODE_RETRY_SAFE, $answer->errorCode);
        self::assertFalse($answer->isPartiallySaved());
        self::assertSame(WriteConflictException::MESSAGE_RETRY_SAFE, $answer->getMessage());
        self::assertSame(['Retry-After' => '1', 'Cache-Control' => 'no-store, private'], $answer->getHeaders());
        self::assertSame($failure, $answer->getPrevious());
    }

    /**
     * @return iterable<string, array{Throwable}>
     */
    public static function notConflicts(): iterable
    {
        yield 'unique violation' => [new UniqueConstraintViolationException(self::driverException(1062, '23000'), null)];
        yield 'connection lost' => [new ConnectionLost(self::driverException(2006, 'HY000'), null)];
        yield 'other database error' => [new DriverException(self::driverException(1146, '42S02'), null)];
        yield 'domain error' => [new RuntimeException('Record not found', 1213)];
    }

    #[DataProvider('notConflicts')]
    public function testOtherFailuresAreNotConflicts(Throwable $failure): void
    {
        self::assertFalse((new WriteConflictMapper())->isConflict($failure));
    }

    public function testCommittedConflictAnswersConflict002WithoutRetryAfter(): void
    {
        $failure = new RuntimeException('outer', 0, new ConcurrencyConflict(1, TransientFailureKind::Deadlock, null, new RuntimeException('storage'), true));

        $answer = (new WriteConflictMapper())->toHttpException($failure);

        self::assertSame(409, $answer->getStatusCode());
        self::assertSame(WriteConflictException::CODE_PARTIALLY_SAVED, $answer->errorCode);
        self::assertTrue($answer->isPartiallySaved());
        self::assertSame(WriteConflictException::MESSAGE_PARTIALLY_SAVED, $answer->getMessage());
        self::assertSame(['Cache-Control' => 'no-store, private'], $answer->getHeaders());
    }

    public function testUncommittedConcurrencyConflictIsASafeRetry(): void
    {
        $failure = new ConcurrencyConflict(3, TransientFailureKind::LockWaitTimeout, 4, new RuntimeException('storage'));

        $answer = (new WriteConflictMapper())->toHttpException($failure);

        self::assertSame(WriteConflictException::CODE_RETRY_SAFE, $answer->errorCode);
        self::assertSame('4', $answer->getHeaders()['Retry-After'], "The failure's own hint wins.");
    }

    public function testTheHostPolicyGivesTheStatusAndRetryAfter(): void
    {
        $mapper = new WriteConflictMapper(new ConflictResponsePolicy(503, 7));

        $retry = $mapper->toHttpException(new DeadlockException(self::driverException(1213, '40001'), null));
        self::assertSame(503, $retry->getStatusCode());
        self::assertSame('7', $retry->getHeaders()['Retry-After']);

        $partial = $mapper->toHttpException(new ConcurrencyConflict(1, TransientFailureKind::Deadlock, null, new RuntimeException('storage'), true));
        self::assertSame(503, $partial->getStatusCode());
        self::assertArrayNotHasKey('Retry-After', $partial->getHeaders());
    }

    private static function driverException(int $code, string $sqlState): AbstractException
    {
        return new class('Simulated driver failure', $sqlState, $code) extends AbstractException {
        };
    }
}
