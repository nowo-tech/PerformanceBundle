<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\Command\CreateRecordsTableCommand;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Component\Console\Tester\CommandTester;

final class CreateRecordsTableCommandTest extends TestCase
{
    private MockObject $registry;
    private MockObject $connection;
    private MockObject $schemaManager;
    private MockObject $entityManager;
    private MockObject $metadataFactory;
    private CreateRecordsTableCommand $command;

    protected function setUp(): void
    {
        $this->registry        = $this->createMock(ManagerRegistry::class);
        $this->connection      = $this->createMock(Connection::class);
        $this->schemaManager   = $this->createMock(AbstractSchemaManager::class);
        $this->entityManager   = $this->createMock(EntityManagerInterface::class);
        $this->metadataFactory = $this->createMock(ClassMetadataFactory::class);

        $this->registry->method('getConnection')->with('default')->willReturn($this->connection);
        if (method_exists($this->connection, 'createSchemaManager')) {
            $this->connection->method('createSchemaManager')->willReturn($this->schemaManager);
        }
        if (method_exists($this->connection, 'getSchemaManager')) {
            $this->connection->method('getSchemaManager')->willReturn($this->schemaManager);
        }
        $this->registry->method('getManager')->with('default')->willReturn($this->entityManager);
        $this->entityManager->method('getMetadataFactory')->willReturn($this->metadataFactory);

        $this->command = new CreateRecordsTableCommand($this->registry, 'default', 'routes_data');
    }

    public function testCommandName(): void
    {
        $this->assertSame('nowo:performance:create-records-table', $this->command->getName());
    }

    public function testCommandDescription(): void
    {
        $this->assertSame('Create the access records database table for temporal analysis', $this->command->getDescription());
    }

    public function testCommandHasForceOption(): void
    {
        $this->assertTrue($this->command->getDefinition()->hasOption('force'));
    }

    public function testCommandHasUpdateOption(): void
    {
        $this->assertTrue($this->command->getDefinition()->hasOption('update'));
    }

    public function testCommandHasDropObsoleteOption(): void
    {
        $this->assertTrue($this->command->getDefinition()->hasOption('drop-obsolete'));
    }

    public function testCommandHasNoArguments(): void
    {
        $this->assertCount(0, $this->command->getDefinition()->getArguments());
    }

    public function testHelpContainsCreateRecordsTable(): void
    {
        $help = $this->command->getHelp();
        $this->assertStringContainsString('access records table', $help);
        $this->assertStringContainsString('access records', $help);
        $this->assertStringContainsString('--update', $help);
        $this->assertStringContainsString('--force', $help);
        $this->assertStringContainsString('access records', $help);
        $this->assertStringContainsString('--update', $help);
        $this->assertStringContainsString('--force', $help);
    }

    public function testExecuteWhenTableExistsWithoutOptions(): void
    {
        $metadata = $this->createMock(\Doctrine\ORM\Mapping\ClassMetadata::class);
        $metadata->method('getTableName')->willReturn('routes_data_records');
        $metadata->table = ['name' => 'routes_data_records'];

        $this->metadataFactory
            ->method('getMetadataFor')
            ->with(\Nowo\PerformanceBundle\Entity\RouteDataRecord::class)
            ->willReturn($metadata);
        $this->schemaManager->method('tablesExist')->with(['routes_data_records'])->willReturn(true);

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('already exists', $tester->getDisplay());
        $this->assertStringContainsString('routes_data_records', $tester->getDisplay());
        $this->assertStringContainsString('--update', $tester->getDisplay());
        $this->assertStringContainsString('--force', $tester->getDisplay());
    }

    public function testExecuteReturnsFailureOnException(): void
    {
        $this->registry->method('getConnection')->willThrowException(new RuntimeException('Connection failed'));

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        $this->assertSame(1, $tester->getStatusCode());
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Failed to create table', $display);
        $this->assertStringContainsString('Connection failed', $display);
        $this->assertStringContainsString('doctrine:schema:update', $display);
        $this->assertStringContainsString('doctrine:migrations:diff', $display);
    }

    public function testExecuteWhenRegistryReturnsNonConnectionReturnsFailure(): void
    {
        $this->registry->method('getConnection')->with('default')->willReturn(new stdClass());

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Failed to create table', $tester->getDisplay());
    }

    public function testExecuteWhenRegistryReturnsNonEntityManagerReturnsFailure(): void
    {
        $objectManager = $this->createMock(\Doctrine\Persistence\ObjectManager::class);
        $this->registry->method('getConnection')->with('default')->willReturn($this->connection);
        $this->registry->method('getManager')->with('default')->willReturn($objectManager);
        $this->schemaManager->method('tablesExist')->willReturn(false);

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Failed to create table', $tester->getDisplay());
    }

    public function testExecuteWithUpdateWhenTableDoesNotExistInSchemaManager(): void
    {
        $metadata = $this->createMock(\Doctrine\ORM\Mapping\ClassMetadata::class);
        $metadata->method('getTableName')->willReturn('routes_data_records');
        $metadata->table = ['name' => 'routes_data_records'];

        $this->metadataFactory
            ->method('getMetadataFor')
            ->with(\Nowo\PerformanceBundle\Entity\RouteDataRecord::class)
            ->willReturn($metadata);
        $this->entityManager->method('getConnection')->willReturn($this->connection);
        $this->schemaManager->method('tablesExist')->with(['routes_data_records'])->willReturnOnConsecutiveCalls(true, false);

        $tester = new CommandTester($this->command);
        $tester->execute(['--update' => true]);

        self::assertSame(0, $tester->getStatusCode());
        $display = $tester->getDisplay();
        self::assertStringContainsString('does not exist', $display);
        self::assertMatchesRegularExpression('/without\s+--update/', $display);
    }

    public function testExecuteWhenGetMetadataForThrowsReturnsFailure(): void
    {
        $this->metadataFactory->method('getMetadataFor')
            ->with(\Nowo\PerformanceBundle\Entity\RouteDataRecord::class)
            ->willThrowException(new RuntimeException('Record metadata error'));

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('Failed to create table', $tester->getDisplay());
        self::assertStringContainsString('Record metadata error', $tester->getDisplay());
    }

    public function testExecuteWhenSchemaToolFindsNoRouteDataRecordMetadataReturnsFailure(): void
    {
        $metadata = $this->createMock(\Doctrine\ORM\Mapping\ClassMetadata::class);
        $metadata->method('getTableName')->willReturn('routes_data_records');
        $metadata->table = ['name' => 'routes_data_records'];

        $this->metadataFactory
            ->method('getMetadataFor')
            ->with(\Nowo\PerformanceBundle\Entity\RouteDataRecord::class)
            ->willReturn($metadata);
        $this->metadataFactory->method('getAllMetadata')->willReturn([]);
        $this->schemaManager->method('tablesExist')->with(['routes_data_records'])->willReturn(false);
        $this->entityManager->method('getConnection')->willReturn($this->connection);

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('Failed to create table', $tester->getDisplay());
        self::assertStringContainsString('RouteDataRecord entity metadata not found', $tester->getDisplay());
    }

    public function testExecuteWhenTableExistsWithForceDropsTableThenFailsCreateWithoutMetadata(): void
    {
        $metadata = $this->createMock(\Doctrine\ORM\Mapping\ClassMetadata::class);
        $metadata->method('getTableName')->willReturn('routes_data_records');
        $metadata->table = ['name' => 'routes_data_records'];

        $this->metadataFactory
            ->method('getMetadataFor')
            ->with(\Nowo\PerformanceBundle\Entity\RouteDataRecord::class)
            ->willReturn($metadata);
        $this->metadataFactory->method('getAllMetadata')->willReturn([]);
        $this->entityManager->method('getConnection')->willReturn($this->connection);
        $this->schemaManager->method('tablesExist')->with(['routes_data_records'])->willReturn(true);
        $this->schemaManager->expects(self::once())->method('dropTable')->with('routes_data_records');

        $tester   = new CommandTester($this->command);
        $exitCode = $tester->execute(['--force' => true]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Failed to create table', $tester->getDisplay());
        self::assertStringContainsString('RouteDataRecord entity metadata not found', $tester->getDisplay());
    }
}
