<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\Column;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\Service\PerformanceCacheService;
use Nowo\PerformanceBundle\Service\TableStatusChecker;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Advanced tests for TableStatusChecker.
 */
final class TableStatusCheckerAdvancedTest extends TestCase
{
    private ManagerRegistry|MockObject $registry;
    private Connection|MockObject $connection;
    private EntityManagerInterface|MockObject $entityManager;
    private AbstractSchemaManager|MockObject $schemaManager;
    private ClassMetadataFactory|MockObject $metadataFactory;
    private ClassMetadata|MockObject $metadata;
    private TableStatusChecker $checker;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->connection = $this->createMock(Connection::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->schemaManager = $this->createMock(AbstractSchemaManager::class);
        $this->metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $this->metadata = $this->createMock(ClassMetadata::class);

        $this->registry->method('getConnection')->willReturn($this->connection);
        $this->registry->method('getManager')->willReturn($this->entityManager);
        $this->entityManager->method('getMetadataFactory')->willReturn($this->metadataFactory);
        $this->metadataFactory->method('getMetadataFor')->willReturn($this->metadata);

        $this->checker = new TableStatusChecker($this->registry, 'default', 'routes_data');
    }

    public function testTableExistsWithDBAL3(): void
    {
        $this->connection->method('createSchemaManager')->willReturn($this->schemaManager);
        $this->metadata->method('getTableName')->willReturn('routes_data');
        $this->schemaManager->method('tablesExist')->willReturn(true);

        $result = $this->checker->tableExists();

        $this->assertTrue($result);
    }

    public function testTableExistsWithDBAL2(): void
    {
        $this->connection->method('createSchemaManager')->willReturn(null);
        $this->connection->method('getSchemaManager')->willReturn($this->schemaManager);
        $this->metadata->method('getTableName')->willReturn('routes_data');
        $this->schemaManager->method('tablesExist')->willReturn(true);

        $result = $this->checker->tableExists();

        $this->assertTrue($result);
    }

    public function testTableExistsWithMetadataWithoutGetTableName(): void
    {
        $this->connection->method('createSchemaManager')->willReturn($this->schemaManager);
        $this->metadata->method('method_exists')->willReturn(false);
        $this->metadata->table = ['name' => 'routes_data'];
        $this->schemaManager->method('tablesExist')->willReturn(true);

        $result = $this->checker->tableExists();

        $this->assertTrue($result);
    }

    public function testTableExistsWithException(): void
    {
        $this->connection->method('createSchemaManager')->willThrowException(new \Exception('Connection error'));

        $result = $this->checker->tableExists();

        $this->assertFalse($result);
    }

    public function testTableExistsWithCache(): void
    {
        $cacheService = $this->createMock(PerformanceCacheService::class);
        $cacheService->method('getCachedValue')->willReturn(true);
        $this->checker->setCacheService($cacheService);

        $result = $this->checker->tableExists();

        $this->assertTrue($result);
    }

    public function testTableExistsCachesResult(): void
    {
        $cacheService = $this->createMock(PerformanceCacheService::class);
        $cacheService->method('getCachedValue')->willReturn(null);
        $cacheService->expects($this->once())
            ->method('cacheValue')
            ->with($this->stringContains('table_exists'), true, 300);
        $this->checker->setCacheService($cacheService);

        $this->connection->method('createSchemaManager')->willReturn($this->schemaManager);
        $this->metadata->method('getTableName')->willReturn('routes_data');
        $this->schemaManager->method('tablesExist')->willReturn(true);

        $this->checker->tableExists();
    }

    public function testTableIsCompleteWhenTableDoesNotExist(): void
    {
        $this->connection->method('createSchemaManager')->willReturn($this->schemaManager);
        $this->metadata->method('getTableName')->willReturn('routes_data');
        $this->schemaManager->method('tablesExist')->willReturn(false);

        $result = $this->checker->tableIsComplete();

        $this->assertFalse($result);
    }

    public function testTableIsCompleteWithCache(): void
    {
        $cacheService = $this->createMock(PerformanceCacheService::class);
        $cacheService->method('getCachedValue')->willReturn(true);
        $this->checker->setCacheService($cacheService);

        $this->connection->method('createSchemaManager')->willReturn($this->schemaManager);
        $this->metadata->method('getTableName')->willReturn('routes_data');
        $this->schemaManager->method('tablesExist')->willReturn(true);

        $result = $this->checker->tableIsComplete();

        $this->assertTrue($result);
    }

    public function testGetMissingColumnsWhenTableDoesNotExist(): void
    {
        $this->connection->method('createSchemaManager')->willReturn($this->schemaManager);
        $this->metadata->method('getTableName')->willReturn('routes_data');
        $this->metadata->method('getFieldNames')->willReturn(['id', 'name', 'requestTime']);
        $this->metadata->method('getColumnName')->willReturnCallback(function ($field) {
            return match ($field) {
                'id' => 'id',
                'name' => 'name',
                'requestTime' => 'request_time',
                default => $field,
            };
        });
        $this->schemaManager->method('tablesExist')->willReturn(false);

        $result = $this->checker->getMissingColumns();

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    public function testGetMissingColumnsWithException(): void
    {
        $this->connection->method('createSchemaManager')->willThrowException(new \Exception('Connection error'));

        $result = $this->checker->getMissingColumns();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetTableName(): void
    {
        $result = $this->checker->getTableName();

        $this->assertEquals('routes_data', $result);
    }

    public function testSetCacheService(): void
    {
        $cacheService = $this->createMock(PerformanceCacheService::class);
        $this->checker->setCacheService($cacheService);

        // Verify cache service is set by checking it's used
        $cacheService->method('getCachedValue')->willReturn(null);
        $this->connection->method('createSchemaManager')->willReturn($this->schemaManager);
        $this->metadata->method('getTableName')->willReturn('routes_data');
        $this->schemaManager->method('tablesExist')->willReturn(true);

        $this->checker->tableExists();
    }
}
