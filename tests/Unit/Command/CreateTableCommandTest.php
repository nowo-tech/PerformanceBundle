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
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Tester\CommandTester;

final class CreateTableCommandTest extends TestCase
{
    private ManagerRegistry|MockObject $registry;
    private Connection|MockObject $connection;
    private AbstractSchemaManager|MockObject $schemaManager;
    private EntityManagerInterface|MockObject $entityManager;
    private ClassMetadataFactory|MockObject $metadataFactory;
    private AbstractPlatform|MockObject $platform;
    private CreateTableCommand $command;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->connection = $this->createMock(Connection::class);
        $this->schemaManager = $this->createMock(AbstractSchemaManager::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $this->platform = $this->createMock(AbstractPlatform::class);

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

        $this->platform->method('quoteIdentifier')->willReturnCallback(fn (string $name) => "`$name`");
        $this->platform->method('quoteStringLiteral')->willReturnCallback(fn (string $str) => "'$str'");

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
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->table = ['name' => 'routes_data'];

        $this->metadataFactory->method('getMetadataFor')->with('Nowo\PerformanceBundle\Entity\RouteData')->willReturn($metadata);
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
        $this->registry->method('getConnection')->willThrowException(new \RuntimeException('Connection failed'));

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Failed to create table', $tester->getDisplay());
        $this->assertStringContainsString('Connection failed', $tester->getDisplay());
    }
}
