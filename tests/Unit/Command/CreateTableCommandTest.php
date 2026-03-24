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
use Nowo\PerformanceBundle\Command\CreateTableCommand;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Component\Console\Tester\CommandTester;

final class CreateTableCommandTest extends TestCase
{
    private MockObject $registry;
    private MockObject $connection;
    private MockObject $schemaManager;
    private MockObject $entityManager;
    private MockObject $metadataFactory;
    private MockObject $platform;
    private CreateTableCommand $command;

    protected function setUp(): void
    {
        $this->registry        = $this->createMock(ManagerRegistry::class);
        $this->connection      = $this->createMock(Connection::class);
        $this->schemaManager   = $this->createMock(AbstractSchemaManager::class);
        $this->entityManager   = $this->createMock(EntityManagerInterface::class);
        $this->metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $this->platform        = $this->createMock(AbstractPlatform::class);

        $this->registry->method('getConnection')->with('default')->willReturn($this->connection);
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

        $this->platform->method('quoteIdentifier')->willReturnCallback(static fn (string $name): string => "`{$name}`");
        $this->platform->method('quoteStringLiteral')->willReturnCallback(static fn (string $str): string => "'{$str}'");

        $this->command = new CreateTableCommand($this->registry, 'default', 'routes_data', false);
    }

    public function testCommandName(): void
    {
        $this->assertSame('nowo:performance:create-table', $this->command->getName());
    }

    public function testCommandDescription(): void
    {
        $this->assertSame('Create the performance metrics database table', $this->command->getDescription());
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

    public function testHelpContainsForceAndUpdate(): void
    {
        $help = $this->command->getHelp();
        $this->assertStringContainsString('--force', $help);
        $this->assertStringContainsString('--update', $help);
        $this->assertStringContainsString('performance', $help);
    }

    public function testHelpContainsDropObsolete(): void
    {
        $help = $this->command->getHelp();
        $this->assertStringContainsString('drop-obsolete', $help);
    }

    public function testExecuteWhenTableExistsWithoutOptions(): void
    {
        $metadata        = $this->createMock(ClassMetadata::class);
        $metadata->table = ['name' => 'routes_data'];

        $this->metadataFactory->method('getMetadataFor')->with(\Nowo\PerformanceBundle\Entity\RouteData::class)->willReturn($metadata);
        $this->schemaManager->method('tablesExist')->with(['routes_data'])->willReturn(true);

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('already exists', $tester->getDisplay());
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

    public function testExecuteWhenTableExistsWithForceDropsTable(): void
    {
        $metadata        = $this->createMock(ClassMetadata::class);
        $metadata->table = ['name' => 'routes_data'];

        $this->metadataFactory->method('getMetadataFor')->with(\Nowo\PerformanceBundle\Entity\RouteData::class)->willReturn($metadata);
        $this->metadataFactory->method('getAllMetadata')->willReturn([]);
        $this->entityManager->method('getMetadataFactory')->willReturn($this->metadataFactory);
        $this->entityManager->method('getConnection')->willReturn($this->connection);
        $this->schemaManager->method('tablesExist')->with(['routes_data'])->willReturn(true);
        $this->schemaManager->expects($this->once())->method('dropTable')->with('routes_data');

        $tester   = new CommandTester($this->command);
        $exitCode = $tester->execute(['--force' => true]);

        $this->assertSame(1, $exitCode, 'After drop, create path fails without full metadata so exit 1');
        $this->assertStringContainsString('Failed to create table', $tester->getDisplay());
    }

    public function testExecuteWithUpdateWhenTableDoesNotExistInSchemaManager(): void
    {
        $metadata        = $this->createMock(ClassMetadata::class);
        $metadata->table = ['name' => 'routes_data'];

        $this->metadataFactory->method('getMetadataFor')->with(\Nowo\PerformanceBundle\Entity\RouteData::class)->willReturn($metadata);
        $this->entityManager->method('getConnection')->willReturn($this->connection);
        $this->schemaManager->method('tablesExist')->with(['routes_data'])->willReturnOnConsecutiveCalls(true, false);

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
            ->with(\Nowo\PerformanceBundle\Entity\RouteData::class)
            ->willThrowException(new RuntimeException('Metadata mapping error'));

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('Failed to create table', $tester->getDisplay());
        self::assertStringContainsString('Metadata mapping error', $tester->getDisplay());
    }

    public function testExecuteWhenSchemaToolFindsNoRouteDataMetadataReturnsFailure(): void
    {
        $metadata        = $this->createMock(ClassMetadata::class);
        $metadata->table = ['name' => 'routes_data'];

        $this->metadataFactory->method('getMetadataFor')->with(\Nowo\PerformanceBundle\Entity\RouteData::class)->willReturn($metadata);
        $this->metadataFactory->method('getAllMetadata')->willReturn([]);
        $this->schemaManager->method('tablesExist')->with(['routes_data'])->willReturn(false);

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('Failed to create table', $tester->getDisplay());
        self::assertStringContainsString('RouteData entity metadata not found', $tester->getDisplay());
    }

    public function testExecuteUsesConfiguredTableNameWhenMetadataTableNameMissing(): void
    {
        $metadata = $this->createMock(ClassMetadata::class);
        // Simulates metadata without a usable table name (command falls back via ?? configured table_name).
        // @phpstan-ignore assign.propertyType
        $metadata->table = [];

        $this->metadataFactory->method('getMetadataFor')->with(\Nowo\PerformanceBundle\Entity\RouteData::class)->willReturn($metadata);
        $this->schemaManager->method('tablesExist')->with(['routes_data'])->willReturn(true);

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('routes_data', $tester->getDisplay());
        self::assertStringContainsString('already exists', $tester->getDisplay());
    }
}
