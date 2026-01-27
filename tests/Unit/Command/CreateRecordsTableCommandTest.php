<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\Command\CreateRecordsTableCommand;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Tester\CommandTester;

final class CreateRecordsTableCommandTest extends TestCase
{
    private ManagerRegistry|MockObject $registry;
    private Connection|MockObject $connection;
    private AbstractSchemaManager|MockObject $schemaManager;
    private EntityManagerInterface|MockObject $entityManager;
    private ClassMetadataFactory|MockObject $metadataFactory;
    private AbstractPlatform|MockObject $platform;
    private CreateRecordsTableCommand $command;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->connection = $this->createMock(Connection::class);
        $this->schemaManager = $this->createMock(AbstractSchemaManager::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $this->platform = $this->createMock(AbstractPlatform::class);

        $this->registry->method('getConnection')->with('default')->willReturn($this->connection);
        // Support both createSchemaManager() (DBAL 3.x) and getSchemaManager() (DBAL 2.x)
        if (method_exists($this->connection, 'createSchemaManager')) {
            $this->connection->method('createSchemaManager')->willReturn($this->schemaManager);
        }
        if (method_exists($this->connection, 'getSchemaManager')) {
            $this->connection->method('getSchemaManager')->willReturn($this->schemaManager);
        }
        $this->connection->method('getDatabasePlatform')->willReturn($this->platform);
        $this->registry->method('getManager')->with('default')->willReturn($this->entityManager);
        $this->entityManager->method('getMetadataFactory')->willReturn($this->metadataFactory);
        $this->entityManager->method('getConnection')->willReturn($this->connection);

        $this->platform->method('quoteIdentifier')->willReturnCallback(fn($name) => "`$name`");
        $this->platform->method('quoteStringLiteral')->willReturnCallback(fn($str) => "'$str'");

        $this->command = new CreateRecordsTableCommand($this->registry, 'default', 'routes_data_records');
    }

    public function testCommandName(): void
    {
        $this->assertSame('nowo:performance:create-records-table', $this->command->getName());
    }

    public function testCommandDescription(): void
    {
        $this->assertSame('Create the performance metrics records database table', $this->command->getDescription());
    }

    public function testCommandHasForceOption(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('force'));
    }

    public function testCommandHasUpdateOption(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('update'));
    }

    public function testExecuteWhenTableExistsWithoutOptions(): void
    {
        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->table = ['name' => 'routes_data_records'];

        $this->metadataFactory->method('getMetadataFor')->willReturn($classMetadata);
        $this->schemaManager->method('tablesExist')->willReturn(true);

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('already exists', $tester->getDisplay());
    }

    public function testExecuteWhenTableDoesNotExist(): void
    {
        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->table = ['name' => 'routes_data_records'];

        $this->metadataFactory->method('getMetadataFor')->willReturn($classMetadata);
        $this->schemaManager->method('tablesExist')->willReturn(false);

        // Mock SchemaTool
        $schemaTool = $this->getMockBuilder(\Doctrine\ORM\Tools\SchemaTool::class)
            ->disableOriginalConstructor()
            ->getMock();

        $schemaTool->expects($this->once())
            ->method('getCreateSchemaSql')
            ->willReturn(['CREATE TABLE routes_data_records ...']);

        $this->connection->expects($this->once())
            ->method('executeStatement')
            ->with($this->stringContains('CREATE TABLE'));

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('created successfully', $tester->getDisplay());
    }

    public function testExecuteWithForceOptionDropsExistingTable(): void
    {
        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->table = ['name' => 'routes_data_records'];

        $this->metadataFactory->method('getMetadataFor')->willReturn($classMetadata);
        $this->schemaManager->method('tablesExist')->willReturn(true);
        $this->schemaManager->expects($this->once())
            ->method('dropTable')
            ->with('routes_data_records');

        $tester = new CommandTester($this->command);
        $tester->execute(['--force' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Table dropped', $tester->getDisplay());
    }

    public function testExecuteWithUpdateOptionUpdatesTable(): void
    {
        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->table = ['name' => 'routes_data_records'];
        $classMetadata->method('getFieldNames')->willReturn(['id', 'accessedAt', 'statusCode', 'responseTime']);
        $classMetadata->method('getColumnName')->willReturnMap([
            ['id', 'id'],
            ['accessedAt', 'accessed_at'],
            ['statusCode', 'status_code'],
            ['responseTime', 'response_time'],
        ]);
        $classMetadata->method('getTypeOfField')->willReturnMap([
            ['id', 'integer'],
            ['accessedAt', 'datetime_immutable'],
            ['statusCode', 'integer'],
            ['responseTime', 'float'],
        ]);
        $classMetadata->method('isNullable')->willReturnMap([
            ['id', false],
            ['accessedAt', false],
            ['statusCode', true],
            ['responseTime', true],
        ]);
        // getFieldMapping() returns array in ORM 2.x, FieldMapping object in ORM 3.x
        // We'll return objects that can be cast to array
        $classMetadata->method('getFieldMapping')->willReturnCallback(function ($field) {
            return match ($field) {
                'id' => (object) ['type' => 'integer', 'options' => [], 'length' => null, 'default' => null],
                'accessedAt' => (object) ['type' => 'datetime_immutable', 'options' => [], 'length' => null, 'default' => null],
                'statusCode' => (object) ['type' => 'integer', 'options' => [], 'length' => null, 'default' => null],
                'responseTime' => (object) ['type' => 'float', 'options' => [], 'length' => null, 'default' => null],
                default => (object) ['type' => 'string', 'options' => [], 'length' => null, 'default' => null],
            };
        });
        // getAssociationMapping() returns array in ORM 2.x, AssociationMapping object in ORM 3.x
        $classMetadata->method('getAssociationMapping')->willReturnCallback(function () {
            return (object) [
                'joinColumns' => [
                    (object) ['name' => 'route_data_id', 'nullable' => false],
                ],
            ];
        });

        $this->metadataFactory->method('getMetadataFor')->willReturn($classMetadata);
        $this->schemaManager->method('tablesExist')->willReturn(true);

        $table = $this->createMock(\Doctrine\DBAL\Schema\Table::class);
        $table->method('getColumns')->willReturn([]);
        $table->method('getIndexes')->willReturn([]);
        $table->method('hasColumn')->willReturn(false);

        $this->schemaManager->method('introspectTable')->willReturn($table);

        $this->connection->expects($this->atLeastOnce())
            ->method('executeStatement')
            ->with($this->stringContains('ALTER TABLE'));

        $tester = new CommandTester($this->command);
        $tester->execute(['--update' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('updated successfully', $tester->getDisplay());
    }
}
