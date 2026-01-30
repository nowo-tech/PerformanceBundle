<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\DBAL;

use Nowo\PerformanceBundle\DBAL\QueryTrackingConnection;
use Nowo\PerformanceBundle\DBAL\QueryTrackingMiddleware;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

final class QueryTrackingMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset middleware state before each test
        QueryTrackingMiddleware::reset();
    }

    public function testReset(): void
    {
        // Simulate some queries
        QueryTrackingMiddleware::startQuery('query1');
        QueryTrackingMiddleware::stopQuery('query1');
        
        $this->assertSame(1, QueryTrackingMiddleware::getQueryCount());
        $this->assertGreaterThan(0, QueryTrackingMiddleware::getTotalQueryTime());
        
        // Reset
        QueryTrackingMiddleware::reset();
        
        $this->assertSame(0, QueryTrackingMiddleware::getQueryCount());
        $this->assertSame(0.0, QueryTrackingMiddleware::getTotalQueryTime());
    }

    public function testStartAndStopQuery(): void
    {
        $queryId = 'test_query_1';
        
        // Start query
        QueryTrackingMiddleware::startQuery($queryId);
        
        // Query count should still be 0 until stopped
        $this->assertSame(0, QueryTrackingMiddleware::getQueryCount());
        
        // Stop query
        QueryTrackingMiddleware::stopQuery($queryId);
        
        // Now count should be 1
        $this->assertSame(1, QueryTrackingMiddleware::getQueryCount());
        $this->assertGreaterThan(0, QueryTrackingMiddleware::getTotalQueryTime());
    }

    public function testMultipleQueries(): void
    {
        // Track multiple queries
        for ($i = 1; $i <= 5; $i++) {
            $queryId = "query_{$i}";
            QueryTrackingMiddleware::startQuery($queryId);
            usleep(1000); // Small delay to ensure different timings
            QueryTrackingMiddleware::stopQuery($queryId);
        }
        
        $this->assertSame(5, QueryTrackingMiddleware::getQueryCount());
        $this->assertGreaterThan(0, QueryTrackingMiddleware::getTotalQueryTime());
    }

    public function testStopQueryWithoutStart(): void
    {
        // Stopping a query that was never started should not cause errors
        QueryTrackingMiddleware::stopQuery('non_existent_query');
        
        $this->assertSame(0, QueryTrackingMiddleware::getQueryCount());
        $this->assertSame(0.0, QueryTrackingMiddleware::getTotalQueryTime());
    }

    public function testGetQueryCountInitiallyZero(): void
    {
        $this->assertSame(0, QueryTrackingMiddleware::getQueryCount());
    }

    public function testGetTotalQueryTimeInitiallyZero(): void
    {
        $this->assertSame(0.0, QueryTrackingMiddleware::getTotalQueryTime());
    }

    public function testQueryTimeAccumulation(): void
    {
        $queryId1 = 'query1';
        $queryId2 = 'query2';
        
        // Track first query
        QueryTrackingMiddleware::startQuery($queryId1);
        usleep(1000);
        QueryTrackingMiddleware::stopQuery($queryId1);
        
        $time1 = QueryTrackingMiddleware::getTotalQueryTime();
        
        // Track second query
        QueryTrackingMiddleware::startQuery($queryId2);
        usleep(1000);
        QueryTrackingMiddleware::stopQuery($queryId2);
        
        $time2 = QueryTrackingMiddleware::getTotalQueryTime();
        
        // Total time should be greater than first query time
        $this->assertGreaterThan($time1, $time2);
    }

    public function testWrap(): void
    {
        $middleware = new QueryTrackingMiddleware();
        $driver = $this->createMock(\Doctrine\DBAL\Driver::class);
        
        $wrappedDriver = $middleware->wrap($driver);
        
        // The wrapped driver extends AbstractDriverMiddleware, which implements Driver
        $this->assertInstanceOf(\Doctrine\DBAL\Driver::class, $wrappedDriver);
    }

    public function testQueryTrackingConnectionPrepare(): void
    {
        QueryTrackingMiddleware::reset();
        
        $connection = $this->createMock(\Doctrine\DBAL\Driver\Connection::class);
        $statement = $this->createMock(\Doctrine\DBAL\Driver\Statement::class);
        $result = $this->createMock(\Doctrine\DBAL\Driver\Result::class);
        
        $connection->method('prepare')->willReturn($statement);
        $statement->method('execute')->willReturn($result);
        
        $trackingConnection = new QueryTrackingConnection($connection);
        
        $preparedStatement = $trackingConnection->prepare('SELECT * FROM users');
        
        $this->assertInstanceOf(\Doctrine\DBAL\Driver\Statement::class, $preparedStatement);
        
        // Execute the statement to trigger tracking
        $preparedStatement->execute();
        
        $this->assertSame(1, QueryTrackingMiddleware::getQueryCount());
        $this->assertGreaterThan(0, QueryTrackingMiddleware::getTotalQueryTime());
    }

    public function testQueryTrackingConnectionQuery(): void
    {
        QueryTrackingMiddleware::reset();
        
        $connection = $this->createMock(\Doctrine\DBAL\Driver\Connection::class);
        $result = $this->createMock(\Doctrine\DBAL\Driver\Result::class);
        
        $connection->method('query')->willReturn($result);
        
        $trackingConnection = new QueryTrackingConnection($connection);
        
        $result = $trackingConnection->query('SELECT * FROM users');
        
        $this->assertInstanceOf(\Doctrine\DBAL\Driver\Result::class, $result);
        $this->assertSame(1, QueryTrackingMiddleware::getQueryCount());
        $this->assertGreaterThan(0, QueryTrackingMiddleware::getTotalQueryTime());
    }

    public function testQueryTrackingConnectionExec(): void
    {
        QueryTrackingMiddleware::reset();
        
        $connection = $this->createMock(\Doctrine\DBAL\Driver\Connection::class);
        $connection->method('exec')->willReturn(5);
        
        $trackingConnection = new QueryTrackingConnection($connection);
        
        $result = $trackingConnection->exec('UPDATE users SET active = 1');
        
        $this->assertSame(5, $result);
        $this->assertSame(1, QueryTrackingMiddleware::getQueryCount());
        $this->assertGreaterThan(0, QueryTrackingMiddleware::getTotalQueryTime());
    }

    public function testQueryTrackingConnectionTransactionMethods(): void
    {
        $connection = $this->createMock(\Doctrine\DBAL\Driver\Connection::class);
        
        $connection->expects($this->once())->method('beginTransaction');
        $connection->expects($this->once())->method('commit');
        $connection->expects($this->once())->method('rollBack');
        
        $trackingConnection = new QueryTrackingConnection($connection);
        
        $trackingConnection->beginTransaction();
        $trackingConnection->commit();
        $trackingConnection->rollBack();
    }

    public function testQueryTrackingConnectionOtherMethods(): void
    {
        $connection = $this->createMock(\Doctrine\DBAL\Driver\Connection::class);
        
        $connection->method('getServerVersion')->willReturn('8.0.0');
        $connection->method('quote')->willReturn("'test'");
        $connection->method('lastInsertId')->willReturn('123');
        $connection->method('getNativeConnection')->willReturn(new \stdClass());
        
        $trackingConnection = new QueryTrackingConnection($connection);
        
        $this->assertSame('8.0.0', $trackingConnection->getServerVersion());
        $this->assertSame("'test'", $trackingConnection->quote('test'));
        $this->assertSame('123', $trackingConnection->lastInsertId());
        $this->assertInstanceOf(\stdClass::class, $trackingConnection->getNativeConnection());
    }

    public function testQueryTrackingConnectionQueryWithException(): void
    {
        QueryTrackingMiddleware::reset();
        
        $connection = $this->createMock(\Doctrine\DBAL\Driver\Connection::class);
        $connection->method('query')->willThrowException(new \Exception('Database error'));
        
        $trackingConnection = new QueryTrackingConnection($connection);
        
        try {
            $trackingConnection->query('SELECT * FROM users');
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $this->assertSame('Database error', $e->getMessage());
            // Query should still be tracked even if it fails
            $this->assertSame(1, QueryTrackingMiddleware::getQueryCount());
        }
    }

    public function testQueryTrackingConnectionExecWithException(): void
    {
        QueryTrackingMiddleware::reset();
        
        $connection = $this->createMock(\Doctrine\DBAL\Driver\Connection::class);
        $connection->method('exec')->willThrowException(new \Exception('Database error'));
        
        $trackingConnection = new QueryTrackingConnection($connection);
        
        try {
            $trackingConnection->exec('UPDATE users SET active = 1');
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $this->assertSame('Database error', $e->getMessage());
            // Query should still be tracked even if it fails
            $this->assertSame(1, QueryTrackingMiddleware::getQueryCount());
        }
    }

    public function testQueryTrackingConnectionPrepareWithException(): void
    {
        QueryTrackingMiddleware::reset();

        $connection = $this->createMock(\Doctrine\DBAL\Driver\Connection::class);
        $statement = $this->createMock(\Doctrine\DBAL\Driver\Statement::class);

        $connection->method('prepare')->willReturn($statement);
        $statement->method('execute')->willThrowException(new \Exception('Database error'));

        $trackingConnection = new QueryTrackingConnection($connection);

        $preparedStatement = $trackingConnection->prepare('SELECT * FROM users');

        try {
            $preparedStatement->execute();
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $this->assertSame('Database error', $e->getMessage());
            // Query should still be tracked even if it fails
            $this->assertSame(1, QueryTrackingMiddleware::getQueryCount());
        }
    }

    public function testStartQuerySameIdTwiceOverwritesStartTime(): void
    {
        QueryTrackingMiddleware::reset();

        QueryTrackingMiddleware::startQuery('q1');
        usleep(10000);
        QueryTrackingMiddleware::startQuery('q1');
        usleep(2000);
        QueryTrackingMiddleware::stopQuery('q1');

        $this->assertSame(1, QueryTrackingMiddleware::getQueryCount());
        $this->assertGreaterThan(0.0, QueryTrackingMiddleware::getTotalQueryTime());
    }
}
