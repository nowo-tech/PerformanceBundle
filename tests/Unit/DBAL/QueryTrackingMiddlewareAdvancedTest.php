<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\DBAL;

use Nowo\PerformanceBundle\DBAL\QueryTrackingConnection;
use Nowo\PerformanceBundle\DBAL\QueryTrackingMiddleware;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Advanced tests for QueryTrackingMiddleware edge cases.
 */
final class QueryTrackingMiddlewareAdvancedTest extends TestCase
{
    protected function setUp(): void
    {
        QueryTrackingMiddleware::reset();
    }

    public function testStartQueryWithSameIdMultipleTimes(): void
    {
        $queryId = 'same_query_id';
        
        QueryTrackingMiddleware::startQuery($queryId);
        usleep(1000);
        QueryTrackingMiddleware::startQuery($queryId); // Start again with same ID
        
        QueryTrackingMiddleware::stopQuery($queryId);
        
        // Should only count once (second start overwrites first)
        $this->assertSame(1, QueryTrackingMiddleware::getQueryCount());
    }

    public function testStopQueryMultipleTimes(): void
    {
        $queryId = 'query1';
        
        QueryTrackingMiddleware::startQuery($queryId);
        QueryTrackingMiddleware::stopQuery($queryId);
        QueryTrackingMiddleware::stopQuery($queryId); // Stop again
        
        // Should only count once
        $this->assertSame(1, QueryTrackingMiddleware::getQueryCount());
    }

    public function testQueryTimeWithVeryShortQueries(): void
    {
        $queryId = 'fast_query';
        
        QueryTrackingMiddleware::startQuery($queryId);
        QueryTrackingMiddleware::stopQuery($queryId);
        
        // Even very fast queries should have some time (even if very small)
        $this->assertGreaterThanOrEqual(0.0, QueryTrackingMiddleware::getTotalQueryTime());
    }

    public function testMultipleResets(): void
    {
        QueryTrackingMiddleware::startQuery('query1');
        QueryTrackingMiddleware::stopQuery('query1');
        
        QueryTrackingMiddleware::reset();
        $this->assertSame(0, QueryTrackingMiddleware::getQueryCount());
        
        QueryTrackingMiddleware::startQuery('query2');
        QueryTrackingMiddleware::stopQuery('query2');
        
        QueryTrackingMiddleware::reset();
        $this->assertSame(0, QueryTrackingMiddleware::getQueryCount());
    }

    public function testQueryTrackingConnectionWithNullServerVersion(): void
    {
        $connection = $this->createMock(\Doctrine\DBAL\Driver\Connection::class);
        $connection->method('getServerVersion')->willReturn(null);
        
        $trackingConnection = new QueryTrackingConnection($connection);
        
        // Should return empty string when server version is null
        $this->assertSame('', $trackingConnection->getServerVersion());
    }

    public function testQueryTrackingConnectionWithEmptyStringServerVersion(): void
    {
        $connection = $this->createMock(\Doctrine\DBAL\Driver\Connection::class);
        $connection->method('getServerVersion')->willReturn('');
        
        $trackingConnection = new QueryTrackingConnection($connection);
        
        $this->assertSame('', $trackingConnection->getServerVersion());
    }

    public function testQueryTrackingConnectionQuoteWithDifferentTypes(): void
    {
        $connection = $this->createMock(\Doctrine\DBAL\Driver\Connection::class);
        $connection->method('quote')->willReturnCallback(function ($value, $type) {
            return match ($type) {
                \PDO::PARAM_INT => (string) $value,
                \PDO::PARAM_STR => "'{$value}'",
                default => "'{$value}'",
            };
        });
        
        $trackingConnection = new QueryTrackingConnection($connection);
        
        $this->assertSame('123', $trackingConnection->quote('123', \PDO::PARAM_INT));
        $this->assertSame("'test'", $trackingConnection->quote('test', \PDO::PARAM_STR));
    }

    public function testQueryTrackingConnectionLastInsertIdReturnsInt(): void
    {
        $connection = $this->createMock(\Doctrine\DBAL\Driver\Connection::class);
        $connection->method('lastInsertId')->willReturn(123);
        
        $trackingConnection = new QueryTrackingConnection($connection);
        
        $result = $trackingConnection->lastInsertId();
        $this->assertIsInt($result);
        $this->assertSame(123, $result);
    }

    public function testQueryTrackingConnectionLastInsertIdReturnsString(): void
    {
        $connection = $this->createMock(\Doctrine\DBAL\Driver\Connection::class);
        $connection->method('lastInsertId')->willReturn('abc123');
        
        $trackingConnection = new QueryTrackingConnection($connection);
        
        $result = $trackingConnection->lastInsertId();
        $this->assertIsString($result);
        $this->assertSame('abc123', $result);
    }

    public function testQueryTrackingConnectionGetNativeConnection(): void
    {
        $nativeConnection = new \stdClass();
        $connection = $this->createMock(\Doctrine\DBAL\Driver\Connection::class);
        $connection->method('getNativeConnection')->willReturn($nativeConnection);
        
        $trackingConnection = new QueryTrackingConnection($connection);
        
        $result = $trackingConnection->getNativeConnection();
        $this->assertSame($nativeConnection, $result);
    }

    public function testQueryTrackingConnectionPrepareWithDifferentSqlQueries(): void
    {
        QueryTrackingMiddleware::reset();
        
        $connection = $this->createMock(\Doctrine\DBAL\Driver\Connection::class);
        $statement = $this->createMock(\Doctrine\DBAL\Driver\Statement::class);
        $result = $this->createMock(\Doctrine\DBAL\Driver\Result::class);
        
        $connection->method('prepare')->willReturn($statement);
        $statement->method('execute')->willReturn($result);
        
        $trackingConnection = new QueryTrackingConnection($connection);
        
        // Prepare and execute multiple different queries
        $stmt1 = $trackingConnection->prepare('SELECT * FROM users');
        $stmt1->execute();
        
        $stmt2 = $trackingConnection->prepare('SELECT * FROM posts');
        $stmt2->execute();
        
        $this->assertSame(2, QueryTrackingMiddleware::getQueryCount());
    }

    public function testQueryTrackingConnectionPrepareWithSameSqlQuery(): void
    {
        QueryTrackingMiddleware::reset();
        
        $connection = $this->createMock(\Doctrine\DBAL\Driver\Connection::class);
        $statement = $this->createMock(\Doctrine\DBAL\Driver\Statement::class);
        $result = $this->createMock(\Doctrine\DBAL\Driver\Result::class);
        
        $connection->method('prepare')->willReturn($statement);
        $statement->method('execute')->willReturn($result);
        
        $trackingConnection = new QueryTrackingConnection($connection);
        
        // Prepare and execute same query multiple times
        $sql = 'SELECT * FROM users';
        $stmt1 = $trackingConnection->prepare($sql);
        $stmt1->execute();
        
        $stmt2 = $trackingConnection->prepare($sql);
        $stmt2->execute();
        
        // Should track both executions (different query IDs due to object hash)
        $this->assertSame(2, QueryTrackingMiddleware::getQueryCount());
    }

    public function testQueryTrackingConnectionQueryWithEmptySql(): void
    {
        QueryTrackingMiddleware::reset();
        
        $connection = $this->createMock(\Doctrine\DBAL\Driver\Connection::class);
        $result = $this->createMock(\Doctrine\DBAL\Driver\Result::class);
        
        $connection->method('query')->willReturn($result);
        
        $trackingConnection = new QueryTrackingConnection($connection);
        
        $trackingConnection->query('');
        
        $this->assertSame(1, QueryTrackingMiddleware::getQueryCount());
    }

    public function testQueryTrackingConnectionExecWithEmptySql(): void
    {
        QueryTrackingMiddleware::reset();
        
        $connection = $this->createMock(\Doctrine\DBAL\Driver\Connection::class);
        $connection->method('exec')->willReturn(0);
        
        $trackingConnection = new QueryTrackingConnection($connection);
        
        $result = $trackingConnection->exec('');
        
        $this->assertSame(0, $result);
        $this->assertSame(1, QueryTrackingMiddleware::getQueryCount());
    }

    public function testQueryTrackingConnectionExecReturnsZero(): void
    {
        QueryTrackingMiddleware::reset();
        
        $connection = $this->createMock(\Doctrine\DBAL\Driver\Connection::class);
        $connection->method('exec')->willReturn(0);
        
        $trackingConnection = new QueryTrackingConnection($connection);
        
        $result = $trackingConnection->exec('UPDATE users SET active = 0 WHERE 1=0');
        
        $this->assertSame(0, $result);
        $this->assertSame(1, QueryTrackingMiddleware::getQueryCount());
    }

    public function testWrapCreatesNewInstanceEachTime(): void
    {
        $middleware = new QueryTrackingMiddleware();
        $driver = $this->createMock(\Doctrine\DBAL\Driver::class);
        
        $wrapped1 = $middleware->wrap($driver);
        $wrapped2 = $middleware->wrap($driver);
        
        // Each wrap should create a new instance
        $this->assertNotSame($wrapped1, $wrapped2);
    }

    public function testQueryTrackingConnectionTransactionMethodsInSequence(): void
    {
        $connection = $this->createMock(\Doctrine\DBAL\Driver\Connection::class);
        
        $connection->expects($this->exactly(2))->method('beginTransaction');
        $connection->expects($this->exactly(2))->method('commit');
        $connection->expects($this->once())->method('rollBack');
        
        $trackingConnection = new QueryTrackingConnection($connection);
        
        // Multiple transactions
        $trackingConnection->beginTransaction();
        $trackingConnection->commit();
        
        $trackingConnection->beginTransaction();
        $trackingConnection->rollBack();
        
        $trackingConnection->beginTransaction();
        $trackingConnection->commit();
    }

    public function testQueryTrackingConnectionPrepareStatementExecuteWithNullParams(): void
    {
        QueryTrackingMiddleware::reset();
        
        $connection = $this->createMock(\Doctrine\DBAL\Driver\Connection::class);
        $statement = $this->createMock(\Doctrine\DBAL\Driver\Statement::class);
        $result = $this->createMock(\Doctrine\DBAL\Driver\Result::class);
        
        $connection->method('prepare')->willReturn($statement);
        $statement->method('execute')->willReturn($result);
        
        $trackingConnection = new QueryTrackingConnection($connection);
        
        $preparedStatement = $trackingConnection->prepare('SELECT * FROM users');
        $preparedStatement->execute(null);
        
        $this->assertSame(1, QueryTrackingMiddleware::getQueryCount());
    }

    public function testQueryTrackingConnectionPrepareStatementExecuteWithArrayParams(): void
    {
        QueryTrackingMiddleware::reset();
        
        $connection = $this->createMock(\Doctrine\DBAL\Driver\Connection::class);
        $statement = $this->createMock(\Doctrine\DBAL\Driver\Statement::class);
        $result = $this->createMock(\Doctrine\DBAL\Driver\Result::class);
        
        $connection->method('prepare')->willReturn($statement);
        $statement->method('execute')->willReturn($result);
        
        $trackingConnection = new QueryTrackingConnection($connection);
        
        $preparedStatement = $trackingConnection->prepare('SELECT * FROM users WHERE id = ?');
        $preparedStatement->execute([1]);
        
        $this->assertSame(1, QueryTrackingMiddleware::getQueryCount());
    }
}
