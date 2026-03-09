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
use RuntimeException;

final class TableStatusCheckerTest extends TestCase
{
    public function testGetTableName(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $checker  = new TableStatusChecker($registry, 'default', 'routes_data', false);

        $this->assertSame('routes_data', $checker->getTableName());
    }

    public function testIsAccessRecordsEnabledWhenTrue(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $checker  = new TableStatusChecker($registry, 'default', 'routes_data', true);

        $this->assertTrue($checker->isAccessRecordsEnabled());
    }

    public function testIsAccessRecordsEnabledWhenFalse(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $checker  = new TableStatusChecker($registry, 'default', 'routes_data', false);

        $this->assertFalse($checker->isAccessRecordsEnabled());
    }

    public function testSetCacheService(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $checker  = new TableStatusChecker($registry, 'default', 'routes_data', false);
        $cache    = $this->createMock(PerformanceCacheService::class);

        $checker->setCacheService($cache);
        $this->addToAssertionCount(1);
    }

    public function testSetCacheServiceNull(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $checker  = new TableStatusChecker($registry, 'default', 'routes_data', false);

        $checker->setCacheService(null);
        $this->addToAssertionCount(1);
    }

    public function testRecordsTableExistsWhenAccessRecordsDisabled(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $checker  = new TableStatusChecker($registry, 'default', 'routes_data', false);

        $this->assertTrue($checker->recordsTableExists());
    }

    public function testRecordsTableIsCompleteWhenAccessRecordsDisabled(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $checker  = new TableStatusChecker($registry, 'default', 'routes_data', false);

        $this->assertTrue($checker->recordsTableIsComplete());
    }

    public function testGetRecordsMissingColumnsWhenAccessRecordsDisabled(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $checker  = new TableStatusChecker($registry, 'default', 'routes_data', false);

        $this->assertSame([], $checker->getRecordsMissingColumns());
    }

    public function testGetRecordsTableNameWhenExceptionReturnsSuffix(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManager')->willThrowException(new RuntimeException('no manager'));

        $checker = new TableStatusChecker($registry, 'default', 'my_table', true);

        $this->assertSame('my_table_records', $checker->getRecordsTableName());
    }

    public function testGetRecordsTableNameReturnsCachedValueWhenCacheHit(): void
    {
        $cache = $this->createMock(PerformanceCacheService::class);
        $cache->method('getCachedValue')
            ->with($this->stringContains('records_table_name'))
            ->willReturn('cached_routes_data_records');

        $registry = $this->createMock(ManagerRegistry::class);
        $checker  = new TableStatusChecker($registry, 'default', 'routes_data', true);
        $checker->setCacheService($cache);

        $registry->expects($this->never())->method('getManager');

        $this->assertSame('cached_routes_data_records', $checker->getRecordsTableName());
    }

    public function testGetTableNameWithCustomTableName(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $checker  = new TableStatusChecker($registry, 'custom_conn', 'custom_performance_table', false);

        $this->assertSame('custom_performance_table', $checker->getTableName());
    }

    public function testGetRecordsTableNameWithCustomBaseTableReturnsSuffixWhenException(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManager')->willThrowException(new RuntimeException('no manager'));

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
        $checker  = new TableStatusChecker($registry, 'default', 'routes_data', false);
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
        $checker  = new TableStatusChecker($registry, 'default', 'routes_data', false);
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
            300,
        );

        $connection      = $this->createMock(Connection::class);
        $schemaManager   = $this->createMock(AbstractSchemaManager::class);
        $entityManager   = $this->createMock(EntityManagerInterface::class);
        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadata        = $this->createMock(\Doctrine\ORM\Mapping\ClassMetadata::class);

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
        $registry->method('getConnection')->willThrowException(new RuntimeException('Connection failed'));

        $checker = new TableStatusChecker($registry, 'default', 'routes_data', false);

        $this->assertFalse($checker->tableExists());
    }

    public function testTableIsCompleteReturnsCachedTrueWhenCacheHit(): void
    {
        $cache = $this->createMock(PerformanceCacheService::class);
        $cache->method('getCachedValue')
            ->willReturnCallback(static fn (string $key): bool => true);

        $registry = $this->createMock(ManagerRegistry::class);
        $checker  = new TableStatusChecker($registry, 'default', 'routes_data', false);
        $checker->setCacheService($cache);

        $registry->expects($this->never())->method('getConnection');

        $this->assertTrue($checker->tableIsComplete());
    }

    public function testTableIsCompleteReturnsFalseWhenTableExistsButHasMissingColumns(): void
    {
        $cache = $this->createMock(PerformanceCacheService::class);
        $cache->method('getCachedValue')->willReturnCallback(static function (string $key): mixed {
            if (str_contains($key, 'table_exists')) {
                return true;
            }
            if (str_contains($key, 'table_complete')) {
                return null;
            }
            if (str_contains($key, 'missing_columns')) {
                return ['missing_col'];
            }

            return null;
        });
        $cache->expects($this->once())->method('cacheValue')->with(
            $this->stringContains('table_complete'),
            false,
            300,
        );

        $registry = $this->createMock(ManagerRegistry::class);
        $checker  = new TableStatusChecker($registry, 'default', 'routes_data', false);
        $checker->setCacheService($cache);

        $this->assertFalse($checker->tableIsComplete());
    }

    public function testTableIsCompleteReturnsFalseWhenTableDoesNotExist(): void
    {
        $cache = $this->createMock(PerformanceCacheService::class);
        $cache->method('getCachedValue')->willReturn(null);
        $cache->expects($this->atLeastOnce())->method('cacheValue');

        $connection      = $this->createMock(Connection::class);
        $schemaManager   = $this->createMock(AbstractSchemaManager::class);
        $entityManager   = $this->createMock(EntityManagerInterface::class);
        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadata        = $this->createMock(\Doctrine\ORM\Mapping\ClassMetadata::class);

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
        $registry->method('getConnection')->willThrowException(new RuntimeException('Connection failed'));
        $registry->method('getManager')->willThrowException(new RuntimeException('No manager'));

        $checker = new TableStatusChecker($registry, 'default', 'routes_data', true);

        $this->assertFalse($checker->recordsTableExists());
    }

    public function testRecordsTableIsCompleteReturnsTrueWhenAccessRecordsEnabledAndTableComplete(): void
    {
        $cache = $this->createMock(PerformanceCacheService::class);
        $cache->method('getCachedValue')->willReturnCallback(static function (string $key): mixed {
            if (str_contains($key, 'records_table_exists')) {
                return true;
            }
            if (str_contains($key, 'records_missing_columns')) {
                return [];
            }

            return null;
        });

        $registry = $this->createMock(ManagerRegistry::class);
        $checker  = new TableStatusChecker($registry, 'default', 'routes_data', true);
        $checker->setCacheService($cache);

        $this->assertTrue($checker->recordsTableIsComplete());
    }

    public function testRecordsTableIsCompleteReturnsFalseWhenTableExistsButHasMissingColumns(): void
    {
        $cache = $this->createMock(PerformanceCacheService::class);
        $cache->method('getCachedValue')->willReturnCallback(static function (string $key): mixed {
            if (str_contains($key, 'records_table_exists')) {
                return true;
            }
            if (str_contains($key, 'records_missing_columns')) {
                return ['missing_col'];
            }

            return null;
        });

        $registry = $this->createMock(ManagerRegistry::class);
        $checker  = new TableStatusChecker($registry, 'default', 'routes_data', true);
        $checker->setCacheService($cache);

        $this->assertFalse($checker->recordsTableIsComplete());
    }

    public function testGetMissingColumnsReturnsEmptyArrayWhenException(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getConnection')->willThrowException(new RuntimeException('Connection failed'));

        $checker = new TableStatusChecker($registry, 'default', 'routes_data', false);

        $this->assertSame([], $checker->getMissingColumns());
    }

    public function testGetMissingColumnsReturnsCachedValueWhenCacheHit(): void
    {
        $cachedMissing = ['missing_col_a', 'missing_col_b'];
        $cache         = $this->createMock(PerformanceCacheService::class);
        $cache->method('getCachedValue')
            ->with($this->stringContains('missing_columns'))
            ->willReturn($cachedMissing);

        $registry = $this->createMock(ManagerRegistry::class);
        $checker  = new TableStatusChecker($registry, 'default', 'routes_data', false);
        $checker->setCacheService($cache);

        $registry->expects($this->never())->method('getConnection');

        $this->assertSame($cachedMissing, $checker->getMissingColumns());
    }

    public function testGetMainTableStatusWhenTableExistsAndComplete(): void
    {
        $cache = $this->createMock(PerformanceCacheService::class);
        $cache->method('getCachedValue')->willReturn(null);
        $cache->expects($this->atLeastOnce())->method('cacheValue');

        $connection      = $this->createMock(Connection::class);
        $schemaManager   = $this->createMock(AbstractSchemaManager::class);
        $entityManager   = $this->createMock(EntityManagerInterface::class);
        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadata        = $this->createMock(\Doctrine\ORM\Mapping\ClassMetadata::class);

        $metadata->method('getTableName')->willReturn('routes_data');
        $metadata->method('getFieldNames')->willReturn(['id', 'name', 'env']);
        $metadata->method('getColumnName')->willReturnCallback(static fn (string $f): string => $f);
        $metadataFactory->method('getMetadataFor')->willReturn($metadata);
        $entityManager->method('getMetadataFactory')->willReturn($metadataFactory);
        if (method_exists($connection, 'createSchemaManager')) {
            $connection->method('createSchemaManager')->willReturn($schemaManager);
        }
        if (method_exists($connection, 'getSchemaManager')) {
            $connection->method('getSchemaManager')->willReturn($schemaManager);
        }
        $schemaManager->method('tablesExist')->willReturn(true);
        $table = $this->createMock(\Doctrine\DBAL\Schema\Table::class);
        $col   = $this->createMock(\Doctrine\DBAL\Schema\Column::class);
        $col->method('getName')->willReturn('id');
        $table->method('getColumns')->willReturn([$col]);
        $schemaManager->method('introspectTable')->willReturn($table);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getConnection')->willReturn($connection);
        $registry->method('getManager')->willReturn($entityManager);

        $checker = new TableStatusChecker($registry, 'default', 'routes_data', false);
        $checker->setCacheService($cache);

        $status = $checker->getMainTableStatus();
        $this->assertIsArray($status);
        $this->assertArrayHasKey('exists', $status);
        $this->assertArrayHasKey('complete', $status);
        $this->assertArrayHasKey('table_name', $status);
        $this->assertArrayHasKey('missing_columns', $status);
        $this->assertSame('routes_data', $status['table_name']);
        $this->assertTrue($status['exists']);
    }

    public function testGetMainTableStatusWhenTableDoesNotExist(): void
    {
        $cache = $this->createMock(PerformanceCacheService::class);
        $cache->method('getCachedValue')->willReturn(null);
        $cache->expects($this->atLeastOnce())->method('cacheValue');

        $connection      = $this->createMock(Connection::class);
        $schemaManager   = $this->createMock(AbstractSchemaManager::class);
        $entityManager   = $this->createMock(EntityManagerInterface::class);
        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadata        = $this->createMock(\Doctrine\ORM\Mapping\ClassMetadata::class);

        $metadata->method('getTableName')->willReturn('routes_data');
        $metadata->method('getFieldNames')->willReturn(['id']);
        $metadata->method('getColumnName')->willReturn('id');
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

        $status = $checker->getMainTableStatus();
        $this->assertFalse($status['exists']);
        $this->assertFalse($status['complete']);
        $this->assertSame([], $status['missing_columns']);
    }

    public function testGetRecordsTableStatusReturnsNullWhenAccessRecordsDisabled(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $checker  = new TableStatusChecker($registry, 'default', 'routes_data', false);

        $this->assertNull($checker->getRecordsTableStatus());
    }

    public function testGetRecordsTableStatusWhenAccessRecordsEnabled(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getConnection')->willThrowException(new RuntimeException('no manager'));
        $registry->method('getManager')->willThrowException(new RuntimeException('no manager'));

        $checker = new TableStatusChecker($registry, 'default', 'routes_data', true);

        $status = $checker->getRecordsTableStatus();
        $this->assertIsArray($status);
        $this->assertArrayHasKey('exists', $status);
        $this->assertArrayHasKey('complete', $status);
        $this->assertArrayHasKey('table_name', $status);
        $this->assertSame('routes_data_records', $status['table_name']);
        $this->assertFalse($status['exists']);
    }

    public function testTableExistsReturnsFalseWhenRegistryReturnsNonConnection(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getConnection')->with('default')->willReturn(new \stdClass());

        $checker = new TableStatusChecker($registry, 'default', 'routes_data', false);

        $this->assertFalse($checker->tableExists());
    }

    public function testGetMissingColumnsReturnsEmptyWhenRegistryReturnsNonConnection(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getConnection')->with('default')->willReturn(new \stdClass());

        $checker = new TableStatusChecker($registry, 'default', 'routes_data', false);

        $this->assertSame([], $checker->getMissingColumns());
    }

    public function testGetMainTableStatusWhenTableExistsWithMissingColumns(): void
    {
        $cache = $this->createMock(PerformanceCacheService::class);
        $cache->method('getCachedValue')->willReturn(null);
        $cache->expects($this->atLeastOnce())->method('cacheValue');

        $connection      = $this->createMock(Connection::class);
        $schemaManager   = $this->createMock(AbstractSchemaManager::class);
        $entityManager   = $this->createMock(EntityManagerInterface::class);
        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadata        = $this->createMock(\Doctrine\ORM\Mapping\ClassMetadata::class);

        $metadata->method('getTableName')->willReturn('routes_data');
        $metadata->method('getFieldNames')->willReturn(['id', 'name', 'env', 'extra']);
        $metadata->method('getColumnName')->willReturnCallback(static fn (string $f): string => $f);
        $metadataFactory->method('getMetadataFor')->willReturn($metadata);
        $entityManager->method('getMetadataFactory')->willReturn($metadataFactory);
        if (method_exists($connection, 'createSchemaManager')) {
            $connection->method('createSchemaManager')->willReturn($schemaManager);
        }
        if (method_exists($connection, 'getSchemaManager')) {
            $connection->method('getSchemaManager')->willReturn($schemaManager);
        }
        $schemaManager->method('tablesExist')->willReturn(true);
        $table = $this->createMock(\Doctrine\DBAL\Schema\Table::class);
        $col   = $this->createMock(\Doctrine\DBAL\Schema\Column::class);
        $col->method('getName')->willReturn('id');
        $table->method('getColumns')->willReturn([$col]);
        $schemaManager->method('introspectTable')->willReturn($table);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getConnection')->willReturn($connection);
        $registry->method('getManager')->willReturn($entityManager);

        $checker = new TableStatusChecker($registry, 'default', 'routes_data', false);
        $checker->setCacheService($cache);

        $status = $checker->getMainTableStatus();
        $this->assertTrue($status['exists']);
        $this->assertFalse($status['complete']);
        $this->assertNotEmpty($status['missing_columns']);
    }
}
