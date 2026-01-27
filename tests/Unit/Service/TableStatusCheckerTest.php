<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\Service\PerformanceCacheService;
use Nowo\PerformanceBundle\Service\TableStatusChecker;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

final class TableStatusCheckerTest extends TestCase
{
    private ManagerRegistry|MockObject $registry;
    private Connection|MockObject $connection;
    private AbstractSchemaManager|MockObject $schemaManager;
    private EntityManagerInterface|MockObject $entityManager;
    private ClassMetadataFactory|MockObject $metadataFactory;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->connection = $this->createMock(Connection::class);
        $this->schemaManager = $this->createMock(AbstractSchemaManager::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->metadataFactory = $this->createMock(ClassMetadataFactory::class);
    }

    public function testGetTableNameReturnsConfiguredName(): void
    {
        $checker = new TableStatusChecker(
            $this->registry,
            'default',
            'routes_data'
        );
        
        $this->assertSame('routes_data', $checker->getTableName());
    }

    public function testTableExistsReturnsTrueWhenTableExists(): void
    {
        $metadata = $this->createMock(\Doctrine\ORM\Mapping\ClassMetadata::class);
        $metadata->table = ['name' => 'routes_data'];
        
        $this->connection->method('createSchemaManager')->willReturn($this->schemaManager);
        $this->schemaManager->method('tablesExist')->with(['routes_data'])->willReturn(true);
        $this->registry->method('getConnection')->with('default')->willReturn($this->connection);
        $this->registry->method('getManager')->with('default')->willReturn($this->entityManager);
        $this->entityManager->method('getMetadataFactory')->willReturn($this->metadataFactory);
        $this->metadataFactory->method('getMetadataFor')
            ->with('Nowo\PerformanceBundle\Entity\RouteData')
            ->willReturn($metadata);
        
        $checker = new TableStatusChecker(
            $this->registry,
            'default',
            'routes_data'
        );
        
        $this->assertTrue($checker->tableExists());
    }

    public function testTableExistsReturnsFalseWhenTableDoesNotExist(): void
    {
        $metadata = $this->createMock(\Doctrine\ORM\Mapping\ClassMetadata::class);
        $metadata->table = ['name' => 'routes_data'];
        
        $this->connection->method('createSchemaManager')->willReturn($this->schemaManager);
        $this->schemaManager->method('tablesExist')->with(['routes_data'])->willReturn(false);
        $this->registry->method('getConnection')->with('default')->willReturn($this->connection);
        $this->registry->method('getManager')->with('default')->willReturn($this->entityManager);
        $this->entityManager->method('getMetadataFactory')->willReturn($this->metadataFactory);
        $this->metadataFactory->method('getMetadataFor')
            ->with('Nowo\PerformanceBundle\Entity\RouteData')
            ->willReturn($metadata);
        
        $checker = new TableStatusChecker(
            $this->registry,
            'default',
            'routes_data'
        );
        
        $this->assertFalse($checker->tableExists());
    }

    public function testTableExistsReturnsFalseOnException(): void
    {
        $this->registry->method('getConnection')->willThrowException(new \Exception('Connection error'));
        
        $checker = new TableStatusChecker(
            $this->registry,
            'default',
            'routes_data'
        );
        
        $this->assertFalse($checker->tableExists());
    }

    public function testTableExistsUsesGetTableNameMethodWhenAvailable(): void
    {
        $metadata = $this->createMock(\Doctrine\ORM\Mapping\ClassMetadata::class);
        $metadata->method('getTableName')->willReturn('routes_data');
        
        $this->connection->method('createSchemaManager')->willReturn($this->schemaManager);
        $this->schemaManager->method('tablesExist')->with(['routes_data'])->willReturn(true);
        $this->registry->method('getConnection')->with('default')->willReturn($this->connection);
        $this->registry->method('getManager')->with('default')->willReturn($this->entityManager);
        $this->entityManager->method('getMetadataFactory')->willReturn($this->metadataFactory);
        $this->metadataFactory->method('getMetadataFor')
            ->with('Nowo\PerformanceBundle\Entity\RouteData')
            ->willReturn($metadata);
        
        $checker = new TableStatusChecker(
            $this->registry,
            'default',
            'routes_data'
        );
        
        $this->assertTrue($checker->tableExists());
    }

    public function testTableExistsFallsBackToConfiguredNameWhenMetadataFails(): void
    {
        $metadata = $this->createMock(\Doctrine\ORM\Mapping\ClassMetadata::class);
        $metadata->table = [];
        
        $this->connection->method('createSchemaManager')->willReturn($this->schemaManager);
        $this->schemaManager->method('tablesExist')->with(['routes_data'])->willReturn(true);
        $this->registry->method('getConnection')->with('default')->willReturn($this->connection);
        $this->registry->method('getManager')->with('default')->willReturn($this->entityManager);
        $this->entityManager->method('getMetadataFactory')->willReturn($this->metadataFactory);
        $this->metadataFactory->method('getMetadataFor')
            ->with('Nowo\PerformanceBundle\Entity\RouteData')
            ->willReturn($metadata);
        
        $checker = new TableStatusChecker(
            $this->registry,
            'default',
            'routes_data'
        );
        
        $this->assertTrue($checker->tableExists());
    }

    public function testGetMissingColumnsUsesGetColumnNameForDBAL3x(): void
    {
        $metadata = $this->createMock(\Doctrine\ORM\Mapping\ClassMetadata::class);
        $metadata->table = ['name' => 'routes_data'];
        
        $column = $this->createMock(Column::class);
        $column->method('getQuotedName')->willReturn('`name`');
        
        $table = $this->createMock(Table::class);
        $table->method('getColumns')->willReturn([$column]);
        
        $this->connection->method('createSchemaManager')->willReturn($this->schemaManager);
        $this->schemaManager->method('tablesExist')->with(['routes_data'])->willReturn(true);
        $this->schemaManager->method('introspectTable')->with('routes_data')->willReturn($table);
        $this->registry->method('getConnection')->with('default')->willReturn($this->connection);
        $this->registry->method('getManager')->with('default')->willReturn($this->entityManager);
        $this->entityManager->method('getMetadataFactory')->willReturn($this->metadataFactory);
        $this->metadataFactory->method('getMetadataFor')
            ->with('Nowo\PerformanceBundle\Entity\RouteData')
            ->willReturn($metadata);
        
        $this->connection->method('getDatabasePlatform')
            ->willReturn($this->createMock(\Doctrine\DBAL\Platforms\AbstractPlatform::class));
        
        $checker = new TableStatusChecker(
            $this->registry,
            'default',
            'routes_data'
        );
        
        // Should not throw exception when getQuotedName is available (DBAL 3.x)
        $missing = $checker->getMissingColumns();
        $this->assertIsArray($missing);
    }

    public function testGetMissingColumnsFallsBackToGetNameForDBAL2x(): void
    {
        $metadata = $this->createMock(\Doctrine\ORM\Mapping\ClassMetadata::class);
        $metadata->table = ['name' => 'routes_data'];
        
        $column = $this->createMock(Column::class);
        $column->method('getName')->willReturn('name');
        // getQuotedName doesn't exist in DBAL 2.x
        if (method_exists($column, 'getQuotedName')) {
            $column->method('getQuotedName')->willThrowException(new \BadMethodCallException());
        }
        
        $table = $this->createMock(Table::class);
        $table->method('getColumns')->willReturn([$column]);
        
        $this->connection->method('createSchemaManager')->willReturn($this->schemaManager);
        $this->schemaManager->method('tablesExist')->with(['routes_data'])->willReturn(true);
        $this->schemaManager->method('introspectTable')->with('routes_data')->willReturn($table);
        $this->registry->method('getConnection')->with('default')->willReturn($this->connection);
        $this->registry->method('getManager')->with('default')->willReturn($this->entityManager);
        $this->entityManager->method('getMetadataFactory')->willReturn($this->metadataFactory);
        $this->metadataFactory->method('getMetadataFor')
            ->with('Nowo\PerformanceBundle\Entity\RouteData')
            ->willReturn($metadata);
        
        $platform = $this->createMock(\Doctrine\DBAL\Platforms\AbstractPlatform::class);
        $this->connection->method('getDatabasePlatform')->willReturn($platform);
        
        $checker = new TableStatusChecker(
            $this->registry,
            'default',
            'routes_data'
        );
        
        // Should not throw exception when getName is used (DBAL 2.x fallback)
        $missing = $checker->getMissingColumns();
        $this->assertIsArray($missing);
    }

    public function testTableExistsUsesCacheWhenAvailable(): void
    {
        $cacheService = $this->createMock(PerformanceCacheService::class);
        $cacheKey = 'table_exists_default_routes_data';
        
        // Cache returns true
        $cacheService->expects($this->once())
            ->method('getCachedValue')
            ->with($cacheKey)
            ->willReturn(true);
        
        // Should not query database when cache hit
        $this->registry->expects($this->never())
            ->method('getConnection');
        
        $checker = new TableStatusChecker(
            $this->registry,
            'default',
            'routes_data'
        );
        $checker->setCacheService($cacheService);
        
        $this->assertTrue($checker->tableExists());
    }

    public function testTableExistsCachesResultWhenNotCached(): void
    {
        $cacheService = $this->createMock(PerformanceCacheService::class);
        $cacheKey = 'table_exists_default_routes_data';
        $metadata = $this->createMock(\Doctrine\ORM\Mapping\ClassMetadata::class);
        $metadata->table = ['name' => 'routes_data'];
        
        // Cache miss
        $cacheService->expects($this->once())
            ->method('getCachedValue')
            ->with($cacheKey)
            ->willReturn(null);
        
        // Cache the result
        $cacheService->expects($this->once())
            ->method('cacheValue')
            ->with($cacheKey, true, 300)
            ->willReturn(true);
        
        $this->connection->method('createSchemaManager')->willReturn($this->schemaManager);
        $this->schemaManager->method('tablesExist')->with(['routes_data'])->willReturn(true);
        $this->registry->method('getConnection')->with('default')->willReturn($this->connection);
        $this->registry->method('getManager')->with('default')->willReturn($this->entityManager);
        $this->entityManager->method('getMetadataFactory')->willReturn($this->metadataFactory);
        $this->metadataFactory->method('getMetadataFor')
            ->with('Nowo\PerformanceBundle\Entity\RouteData')
            ->willReturn($metadata);
        
        $checker = new TableStatusChecker(
            $this->registry,
            'default',
            'routes_data'
        );
        $checker->setCacheService($cacheService);
        
        $this->assertTrue($checker->tableExists());
    }

    public function testTableExistsWorksWithoutCache(): void
    {
        $metadata = $this->createMock(\Doctrine\ORM\Mapping\ClassMetadata::class);
        $metadata->table = ['name' => 'routes_data'];
        
        $this->connection->method('createSchemaManager')->willReturn($this->schemaManager);
        $this->schemaManager->method('tablesExist')->with(['routes_data'])->willReturn(true);
        $this->registry->method('getConnection')->with('default')->willReturn($this->connection);
        $this->registry->method('getManager')->with('default')->willReturn($this->entityManager);
        $this->entityManager->method('getMetadataFactory')->willReturn($this->metadataFactory);
        $this->metadataFactory->method('getMetadataFor')
            ->with('Nowo\PerformanceBundle\Entity\RouteData')
            ->willReturn($metadata);
        
        $checker = new TableStatusChecker(
            $this->registry,
            'default',
            'routes_data'
        );
        // No cache service set
        
        $this->assertTrue($checker->tableExists());
    }

    public function testTableIsCompleteUsesCacheWhenAvailable(): void
    {
        $cacheService = $this->createMock(PerformanceCacheService::class);
        $cacheKey = 'table_complete_default_routes_data';
        
        // Cache returns true
        $cacheService->expects($this->once())
            ->method('getCachedValue')
            ->with($cacheKey)
            ->willReturn(true);
        
        // Should not query database when cache hit
        $this->registry->expects($this->never())
            ->method('getConnection');
        
        $checker = new TableStatusChecker(
            $this->registry,
            'default',
            'routes_data'
        );
        $checker->setCacheService($cacheService);
        
        $this->assertTrue($checker->tableIsComplete());
    }

    public function testTableIsCompleteCachesResultWhenNotCached(): void
    {
        $cacheService = $this->createMock(PerformanceCacheService::class);
        $cacheKey = 'table_complete_default_routes_data';
        $metadata = $this->createMock(\Doctrine\ORM\Mapping\ClassMetadata::class);
        $metadata->table = ['name' => 'routes_data'];
        
        // Cache miss
        $cacheService->expects($this->once())
            ->method('getCachedValue')
            ->with($cacheKey)
            ->willReturn(null);
        
        // Cache the result
        $cacheService->expects($this->once())
            ->method('cacheValue')
            ->with($cacheKey, true, 300)
            ->willReturn(true);
        
        $this->connection->method('createSchemaManager')->willReturn($this->schemaManager);
        $this->schemaManager->method('tablesExist')->with(['routes_data'])->willReturn(true);
        $this->registry->method('getConnection')->with('default')->willReturn($this->connection);
        $this->registry->method('getManager')->with('default')->willReturn($this->entityManager);
        $this->entityManager->method('getMetadataFactory')->willReturn($this->metadataFactory);
        $this->metadataFactory->method('getMetadataFor')
            ->with('Nowo\PerformanceBundle\Entity\RouteData')
            ->willReturn($metadata);
        
        // Mock table introspection for tableIsComplete
        $table = $this->createMock(\Doctrine\DBAL\Schema\Table::class);
        $table->method('getColumns')->willReturn([]);
        $this->schemaManager->method('introspectTable')->with('routes_data')->willReturn($table);
        
        $checker = new TableStatusChecker(
            $this->registry,
            'default',
            'routes_data'
        );
        $checker->setCacheService($cacheService);
        
        $this->assertTrue($checker->tableIsComplete());
    }

    public function testGetMissingColumnsUsesCacheWhenAvailable(): void
    {
        $cacheService = $this->createMock(PerformanceCacheService::class);
        $cacheKey = 'missing_columns_default_routes_data';
        $cachedMissing = ['column1', 'column2'];
        
        // Cache returns missing columns
        $cacheService->expects($this->once())
            ->method('getCachedValue')
            ->with($cacheKey)
            ->willReturn($cachedMissing);
        
        // Should not query database when cache hit
        $this->registry->expects($this->never())
            ->method('getConnection');
        
        $checker = new TableStatusChecker(
            $this->registry,
            'default',
            'routes_data'
        );
        $checker->setCacheService($cacheService);
        
        $missing = $checker->getMissingColumns();
        $this->assertSame($cachedMissing, $missing);
    }
}
