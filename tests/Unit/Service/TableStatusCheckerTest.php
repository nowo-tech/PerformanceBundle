<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\Service\PerformanceCacheService;
use Nowo\PerformanceBundle\Service\TableStatusChecker;
use PHPUnit\Framework\TestCase;

final class TableStatusCheckerTest extends TestCase
{
    public function testGetTableName(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $checker = new TableStatusChecker($registry, 'default', 'routes_data', false);

        $this->assertSame('routes_data', $checker->getTableName());
    }

    public function testIsAccessRecordsEnabledWhenTrue(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $checker = new TableStatusChecker($registry, 'default', 'routes_data', true);

        $this->assertTrue($checker->isAccessRecordsEnabled());
    }

    public function testIsAccessRecordsEnabledWhenFalse(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $checker = new TableStatusChecker($registry, 'default', 'routes_data', false);

        $this->assertFalse($checker->isAccessRecordsEnabled());
    }

    public function testSetCacheService(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $checker = new TableStatusChecker($registry, 'default', 'routes_data', false);
        $cache = $this->createMock(PerformanceCacheService::class);

        $checker->setCacheService($cache);
        $this->addToAssertionCount(1);
    }

    public function testSetCacheServiceNull(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $checker = new TableStatusChecker($registry, 'default', 'routes_data', false);

        $checker->setCacheService(null);
        $this->addToAssertionCount(1);
    }

    public function testRecordsTableExistsWhenAccessRecordsDisabled(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $checker = new TableStatusChecker($registry, 'default', 'routes_data', false);

        $this->assertTrue($checker->recordsTableExists());
    }

    public function testRecordsTableIsCompleteWhenAccessRecordsDisabled(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $checker = new TableStatusChecker($registry, 'default', 'routes_data', false);

        $this->assertTrue($checker->recordsTableIsComplete());
    }

    public function testGetRecordsMissingColumnsWhenAccessRecordsDisabled(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $checker = new TableStatusChecker($registry, 'default', 'routes_data', false);

        $this->assertSame([], $checker->getRecordsMissingColumns());
    }

    public function testGetRecordsTableNameWhenExceptionReturnsSuffix(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManager')->willThrowException(new \RuntimeException('no manager'));

        $checker = new TableStatusChecker($registry, 'default', 'my_table', true);

        $this->assertSame('my_table_records', $checker->getRecordsTableName());
    }

    public function testGetTableNameWithCustomTableName(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $checker = new TableStatusChecker($registry, 'custom_conn', 'custom_performance_table', false);

        $this->assertSame('custom_performance_table', $checker->getTableName());
    }

    public function testGetRecordsTableNameWithCustomBaseTableReturnsSuffixWhenException(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManager')->willThrowException(new \RuntimeException('no manager'));

        $checker = new TableStatusChecker($registry, 'default', 'perf_metrics', true);

        $this->assertSame('perf_metrics_records', $checker->getRecordsTableName());
    }

    public function testTableExistsReturnsCachedTrueWhenCacheHit(): void
    {
        $cache = $this->createMock(PerformanceCacheService::class);
        $cache->method('getCachedValue')
            ->with($this->stringContains('table_exists'))
            ->willReturn(true);

        $registry = $this->createMock(ManagerRegistry::class);
        $checker = new TableStatusChecker($registry, 'default', 'routes_data', false);
        $checker->setCacheService($cache);

        $registry->expects($this->never())->method('getConnection');

        $this->assertTrue($checker->tableExists());
    }

    public function testTableExistsReturnsCachedFalseWhenCacheHit(): void
    {
        $cache = $this->createMock(PerformanceCacheService::class);
        $cache->method('getCachedValue')
            ->with($this->stringContains('table_exists'))
            ->willReturn(false);

        $registry = $this->createMock(ManagerRegistry::class);
        $checker = new TableStatusChecker($registry, 'default', 'routes_data', false);
        $checker->setCacheService($cache);

        $registry->expects($this->never())->method('getConnection');

        $this->assertFalse($checker->tableExists());
    }

    public function testTableExistsWhenNoCacheAndDatabaseReturnsTrue(): void
    {
        $cache = $this->createMock(PerformanceCacheService::class);
        $cache->method('getCachedValue')->willReturn(null);
        $cache->expects($this->once())->method('cacheValue')->with(
            $this->stringContains('table_exists'),
            true,
            300
        );

        $connection = $this->createMock(Connection::class);
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadata = $this->createMock(\Doctrine\ORM\Mapping\ClassMetadata::class);

        $metadata->method('getTableName')->willReturn('routes_data');
        $metadataFactory->method('getMetadataFor')->willReturn($metadata);
        $entityManager->method('getMetadataFactory')->willReturn($metadataFactory);
        if (method_exists($connection, 'createSchemaManager')) {
            $connection->method('createSchemaManager')->willReturn($schemaManager);
        }
        if (method_exists($connection, 'getSchemaManager')) {
            $connection->method('getSchemaManager')->willReturn($schemaManager);
        }
        $schemaManager->method('tablesExist')->willReturn(true);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getConnection')->willReturn($connection);
        $registry->method('getManager')->willReturn($entityManager);

        $checker = new TableStatusChecker($registry, 'default', 'routes_data', false);
        $checker->setCacheService($cache);

        $this->assertTrue($checker->tableExists());
    }

    public function testTableExistsReturnsFalseWhenConnectionThrowsException(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getConnection')->willThrowException(new \RuntimeException('Connection failed'));

        $checker = new TableStatusChecker($registry, 'default', 'routes_data', false);

        $this->assertFalse($checker->tableExists());
    }

    public function testTableIsCompleteReturnsCachedTrueWhenCacheHit(): void
    {
        $cache = $this->createMock(PerformanceCacheService::class);
        $cache->method('getCachedValue')
            ->willReturnCallback(function (string $key): bool {
                return true;
            });

        $registry = $this->createMock(ManagerRegistry::class);
        $checker = new TableStatusChecker($registry, 'default', 'routes_data', false);
        $checker->setCacheService($cache);

        $registry->expects($this->never())->method('getConnection');

        $this->assertTrue($checker->tableIsComplete());
    }

    public function testTableIsCompleteReturnsFalseWhenTableDoesNotExist(): void
    {
        $cache = $this->createMock(PerformanceCacheService::class);
        $cache->method('getCachedValue')->willReturn(null);
        $cache->expects($this->atLeastOnce())->method('cacheValue');

        $connection = $this->createMock(Connection::class);
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadata = $this->createMock(\Doctrine\ORM\Mapping\ClassMetadata::class);

        $metadata->method('getTableName')->willReturn('routes_data');
        $metadataFactory->method('getMetadataFor')->willReturn($metadata);
        $entityManager->method('getMetadataFactory')->willReturn($metadataFactory);
        if (method_exists($connection, 'createSchemaManager')) {
            $connection->method('createSchemaManager')->willReturn($schemaManager);
        }
        if (method_exists($connection, 'getSchemaManager')) {
            $connection->method('getSchemaManager')->willReturn($schemaManager);
        }
        $schemaManager->method('tablesExist')->willReturn(false);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getConnection')->willReturn($connection);
        $registry->method('getManager')->willReturn($entityManager);

        $checker = new TableStatusChecker($registry, 'default', 'routes_data', false);
        $checker->setCacheService($cache);

        $this->assertFalse($checker->tableIsComplete());
    }

    public function testRecordsTableExistsReturnsFalseWhenConnectionFails(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getConnection')->willThrowException(new \RuntimeException('Connection failed'));
        $registry->method('getManager')->willThrowException(new \RuntimeException('No manager'));

        $checker = new TableStatusChecker($registry, 'default', 'routes_data', true);

        $this->assertFalse($checker->recordsTableExists());
    }

    public function testGetMissingColumnsReturnsEmptyArrayWhenException(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getConnection')->willThrowException(new \RuntimeException('Connection failed'));

        $checker = new TableStatusChecker($registry, 'default', 'routes_data', false);

        $this->assertSame([], $checker->getMissingColumns());
    }
}
