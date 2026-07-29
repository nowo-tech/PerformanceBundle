<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\DBAL;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Exception;
use Nowo\PerformanceBundle\DBAL\QueryTrackingConnection;
use Nowo\PerformanceBundle\DBAL\QueryTrackingCounters;
use Nowo\PerformanceBundle\DBAL\QueryTrackingMiddleware;
use PHPUnit\Framework\TestCase;
use stdClass;

final class QueryTrackingMiddlewareTest extends TestCase
{
    private QueryTrackingCounters $counters;

    protected function setUp(): void
    {
        $this->counters = new QueryTrackingCounters();
    }

    public function testReset(): void
    {
        $this->counters->startQuery('query1');
        $this->counters->stopQuery('query1');

        $this->assertSame(1, $this->counters->getQueryCount());
        $this->assertGreaterThan(0, $this->counters->getTotalQueryTime());

        $this->counters->reset();

        $this->assertSame(0, $this->counters->getQueryCount());
        $this->assertSame(0.0, $this->counters->getTotalQueryTime());
    }

    public function testStartAndStopQuery(): void
    {
        $queryId = 'test_query_1';
        $this->counters->startQuery($queryId);
        $this->assertSame(0, $this->counters->getQueryCount());
        $this->counters->stopQuery($queryId);
        $this->assertSame(1, $this->counters->getQueryCount());
        $this->assertGreaterThan(0, $this->counters->getTotalQueryTime());
    }

    public function testMultipleQueries(): void
    {
        for ($i = 1; $i <= 5; ++$i) {
            $queryId = "query_{$i}";
            $this->counters->startQuery($queryId);
            usleep(1000);
            $this->counters->stopQuery($queryId);
        }

        $this->assertSame(5, $this->counters->getQueryCount());
        $this->assertGreaterThan(0, $this->counters->getTotalQueryTime());
    }

    public function testStopQueryWithoutStart(): void
    {
        $this->counters->stopQuery('non_existent_query');
        $this->assertSame(0, $this->counters->getQueryCount());
        $this->assertSame(0.0, $this->counters->getTotalQueryTime());
    }

    public function testWrap(): void
    {
        $middleware    = new QueryTrackingMiddleware($this->counters);
        $driver        = $this->createMock(Driver::class);
        $wrappedDriver = $middleware->wrap($driver);
        $this->assertInstanceOf(Driver::class, $wrappedDriver);
    }

    public function testWrapConnectReturnsQueryTrackingConnection(): void
    {
        $innerConnection = $this->createMock(Connection::class);
        $driver          = $this->createMock(Driver::class);
        $driver->method('connect')->willReturn($innerConnection);

        $middleware    = new QueryTrackingMiddleware($this->counters);
        $wrappedDriver = $middleware->wrap($driver);
        $connection    = $wrappedDriver->connect([]);

        $this->assertInstanceOf(QueryTrackingConnection::class, $connection);
    }

    public function testQueryTrackingConnectionPrepare(): void
    {
        $connection = $this->createMock(Connection::class);
        $statement  = $this->createMock(Statement::class);
        $result     = $this->createMock(Result::class);
        $connection->method('prepare')->willReturn($statement);
        $statement->method('execute')->willReturn($result);

        $trackingConnection = new QueryTrackingConnection($connection, $this->counters);
        $preparedStatement  = $trackingConnection->prepare('SELECT * FROM users');
        $preparedStatement->execute();

        $this->assertSame(1, $this->counters->getQueryCount());
        $this->assertGreaterThan(0, $this->counters->getTotalQueryTime());
    }

    public function testQueryTrackingConnectionQuery(): void
    {
        $connection = $this->createMock(Connection::class);
        $result     = $this->createMock(Result::class);
        $connection->method('query')->willReturn($result);

        $trackingConnection = new QueryTrackingConnection($connection, $this->counters);
        $this->assertInstanceOf(Result::class, $trackingConnection->query('SELECT * FROM users'));
        $this->assertSame(1, $this->counters->getQueryCount());
    }

    public function testQueryTrackingConnectionExec(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('exec')->willReturn(5);

        $trackingConnection = new QueryTrackingConnection($connection, $this->counters);
        $this->assertSame(5, $trackingConnection->exec('UPDATE users SET active = 1'));
        $this->assertSame(1, $this->counters->getQueryCount());
    }

    public function testQueryTrackingConnectionTransactionMethods(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('beginTransaction');
        $connection->expects($this->once())->method('commit');
        $connection->expects($this->once())->method('rollBack');

        $trackingConnection = new QueryTrackingConnection($connection, $this->counters);
        $trackingConnection->beginTransaction();
        $trackingConnection->commit();
        $trackingConnection->rollBack();
    }

    public function testQueryTrackingConnectionOtherMethods(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getServerVersion')->willReturn('8.0.0');
        $connection->method('quote')->willReturn("'test'");
        $connection->method('lastInsertId')->willReturn('123');
        $connection->method('getNativeConnection')->willReturn(new stdClass());

        $trackingConnection = new QueryTrackingConnection($connection, $this->counters);
        $this->assertSame('8.0.0', $trackingConnection->getServerVersion());
        $this->assertSame("'test'", $trackingConnection->quote('test'));
        $this->assertSame('123', $trackingConnection->lastInsertId());
        $this->assertInstanceOf(stdClass::class, $trackingConnection->getNativeConnection());
    }

    public function testQueryTrackingConnectionQueryWithException(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('query')->willThrowException(new Exception('Database error'));
        $trackingConnection = new QueryTrackingConnection($connection, $this->counters);

        try {
            $trackingConnection->query('SELECT * FROM users');
            $this->fail('Expected exception was not thrown');
        } catch (Exception $e) {
            $this->assertSame('Database error', $e->getMessage());
            $this->assertSame(1, $this->counters->getQueryCount());
        }
    }

    public function testQueryTrackingConnectionExecWithException(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('exec')->willThrowException(new Exception('Database error'));
        $trackingConnection = new QueryTrackingConnection($connection, $this->counters);

        try {
            $trackingConnection->exec('UPDATE users SET active = 1');
            $this->fail('Expected exception was not thrown');
        } catch (Exception $e) {
            $this->assertSame('Database error', $e->getMessage());
            $this->assertSame(1, $this->counters->getQueryCount());
        }
    }

    public function testQueryTrackingConnectionPrepareWithException(): void
    {
        $connection = $this->createMock(Connection::class);
        $statement  = $this->createMock(Statement::class);
        $connection->method('prepare')->willReturn($statement);
        $statement->method('execute')->willThrowException(new Exception('Database error'));
        $trackingConnection = new QueryTrackingConnection($connection, $this->counters);
        $preparedStatement  = $trackingConnection->prepare('SELECT * FROM users');

        try {
            $preparedStatement->execute();
            $this->fail('Expected exception was not thrown');
        } catch (Exception $e) {
            $this->assertSame('Database error', $e->getMessage());
            $this->assertSame(1, $this->counters->getQueryCount());
        }
    }
}
