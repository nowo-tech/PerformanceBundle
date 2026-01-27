<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\Persistence\ManagerRegistry;
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
}
