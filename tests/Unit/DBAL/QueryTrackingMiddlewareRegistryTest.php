<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\DBAL;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\DBAL\QueryTrackingMiddleware;
use Nowo\PerformanceBundle\DBAL\QueryTrackingMiddlewareRegistry;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

final class QueryTrackingMiddlewareRegistryTest extends TestCase
{
    private ManagerRegistry|MockObject $registry;
    private Connection|MockObject $connection;
    private QueryTrackingMiddleware $middleware;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->connection = $this->createMock(Connection::class);
        $this->middleware = new QueryTrackingMiddleware();
    }

    public function testApplyMiddlewareReturnsFalseWhenConnectionNotFound(): void
    {
        $this->registry->method('getConnection')->willThrowException(new \Exception('Connection not found'));

        $result = QueryTrackingMiddlewareRegistry::applyMiddleware(
            $this->registry,
            'non_existent',
            $this->middleware
        );

        $this->assertFalse($result);
    }

    public function testApplyMiddlewareReturnsFalseWhenConnectionIsNotDoctrineConnection(): void
    {
        $this->registry->method('getConnection')->willReturn(new \stdClass());

        $result = QueryTrackingMiddlewareRegistry::applyMiddleware(
            $this->registry,
            'default',
            $this->middleware
        );

        $this->assertFalse($result);
    }

    public function testApplyMiddlewareHandlesReflectionErrors(): void
    {
        $this->registry->method('getConnection')->willReturn($this->connection);
        $this->connection->method('createSchemaManager')->willThrowException(new \Exception('Schema error'));

        $result = QueryTrackingMiddlewareRegistry::applyMiddleware(
            $this->registry,
            'default',
            $this->middleware
        );

        // Should return false when reflection fails
        $this->assertFalse($result);
    }
}
