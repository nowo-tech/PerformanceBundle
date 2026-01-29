<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Command;

use Nowo\PerformanceBundle\Command\CreateRecordsTableCommand;
use Nowo\PerformanceBundle\Command\CreateTableCommand;
use Nowo\PerformanceBundle\Command\SyncSchemaCommand;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class SyncSchemaCommandTest extends TestCase
{
    private CreateTableCommand&MockObject $createTableCommand;
    private CreateRecordsTableCommand&MockObject $createRecordsTableCommand;
    private SyncSchemaCommand $command;

    protected function setUp(): void
    {
        $this->createTableCommand = $this->createMock(CreateTableCommand::class);
        $this->createRecordsTableCommand = $this->createMock(CreateRecordsTableCommand::class);
        $this->command = new SyncSchemaCommand($this->createTableCommand, $this->createRecordsTableCommand);
    }

    public function testCommandName(): void
    {
        $this->assertSame('nowo:performance:sync-schema', $this->command->getName());
    }

    public function testCommandDescription(): void
    {
        $this->assertSame(
            'Sync database schema with entity metadata (add missing, alter differing, optionally drop obsolete)',
            $this->command->getDescription()
        );
    }

    public function testCommandHasDropObsoleteOption(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('drop-obsolete'));
    }

    public function testCommandHelpMentionsBothTables(): void
    {
        $help = $this->command->getHelp();
        $this->assertStringContainsString('routes_data', $help);
        $this->assertStringContainsString('routes_data_records', $help);
        $this->assertStringContainsString('sync-schema', $help);
    }

    public function testExecuteCallsCreateTableThenCreateRecordsTable(): void
    {
        $this->createTableCommand
            ->expects($this->once())
            ->method('run')
            ->with(
                $this->callback(function (ArrayInput $input) {
                    return $input->getOption('update') === true && $input->getOption('drop-obsolete') === false;
                }),
                $this->anything()
            )
            ->willReturn(Command::SUCCESS);

        $this->createRecordsTableCommand
            ->expects($this->once())
            ->method('run')
            ->with(
                $this->callback(function (ArrayInput $input) {
                    return $input->getOption('update') === true && $input->getOption('drop-obsolete') === false;
                }),
                $this->anything()
            )
            ->willReturn(Command::SUCCESS);

        $exitCode = $this->command->run(
            new ArrayInput([]),
            new BufferedOutput()
        );

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testExecuteWithDropObsoletePassesOptionToBothCommands(): void
    {
        $this->createTableCommand
            ->expects($this->once())
            ->method('run')
            ->with(
                $this->callback(function (ArrayInput $input) {
                    return $input->getOption('drop-obsolete') === true;
                }),
                $this->anything()
            )
            ->willReturn(Command::SUCCESS);

        $this->createRecordsTableCommand
            ->expects($this->once())
            ->method('run')
            ->with(
                $this->callback(function (ArrayInput $input) {
                    return $input->getOption('drop-obsolete') === true;
                }),
                $this->anything()
            )
            ->willReturn(Command::SUCCESS);

        $exitCode = $this->command->run(
            new ArrayInput(['--drop-obsolete' => true]),
            new BufferedOutput()
        );

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testExecutePassesNonInteractiveInputToChildCommands(): void
    {
        $this->createTableCommand
            ->expects($this->once())
            ->method('run')
            ->with(
                $this->callback(function (ArrayInput $input) {
                    return $input->isInteractive() === false
                        && $input->getOption('update') === true
                        && $input->getOption('drop-obsolete') === false;
                }),
                $this->anything()
            )
            ->willReturn(Command::SUCCESS);

        $this->createRecordsTableCommand
            ->expects($this->once())
            ->method('run')
            ->with(
                $this->callback(function (ArrayInput $input) {
                    return $input->isInteractive() === false;
                }),
                $this->anything()
            )
            ->willReturn(Command::SUCCESS);

        $exitCode = $this->command->run(new ArrayInput([]), new BufferedOutput());
        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testExecuteReturnsFailureWhenCreateTableFails(): void
    {
        $this->createTableCommand
            ->expects($this->once())
            ->method('run')
            ->willReturn(Command::FAILURE);

        $this->createRecordsTableCommand->expects($this->never())->method('run');

        $exitCode = $this->command->run(new ArrayInput([]), new BufferedOutput());

        $this->assertSame(Command::FAILURE, $exitCode);
    }

    public function testExecuteReturnsFailureWhenCreateRecordsTableFails(): void
    {
        $this->createTableCommand->method('run')->willReturn(Command::SUCCESS);
        $this->createRecordsTableCommand->method('run')->willReturn(Command::FAILURE);

        $exitCode = $this->command->run(new ArrayInput([]), new BufferedOutput());

        $this->assertSame(Command::FAILURE, $exitCode);
    }

    public function testExecutePassesUpdateOptionToBothCommands(): void
    {
        $this->createTableCommand
            ->expects($this->once())
            ->method('run')
            ->with(
                $this->callback(function (ArrayInput $input) {
                    return $input->getOption('update') === true;
                }),
                $this->anything()
            )
            ->willReturn(Command::SUCCESS);

        $this->createRecordsTableCommand
            ->expects($this->once())
            ->method('run')
            ->with(
                $this->callback(function (ArrayInput $input) {
                    return $input->getOption('update') === true;
                }),
                $this->anything()
            )
            ->willReturn(Command::SUCCESS);

        $exitCode = $this->command->run(new ArrayInput([]), new BufferedOutput());

        $this->assertSame(Command::SUCCESS, $exitCode);
    }
}
