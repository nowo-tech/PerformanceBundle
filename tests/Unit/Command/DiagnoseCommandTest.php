<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Command;

use Nowo\PerformanceBundle\Command\DiagnoseCommand;
use Nowo\PerformanceBundle\Service\TableStatusChecker;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class DiagnoseCommandTest extends TestCase
{
    private ParameterBagInterface|MockObject $parameterBag;
    private DiagnoseCommand $command;

    protected function setUp(): void
    {
        $this->parameterBag = $this->createMock(ParameterBagInterface::class);
        $this->command = new DiagnoseCommand($this->parameterBag);
    }

    public function testCommandName(): void
    {
        $this->assertSame('nowo:performance:diagnose', $this->command->getName());
    }

    public function testCommandDescription(): void
    {
        $this->assertSame('Diagnose Performance Bundle configuration and query tracking status', $this->command->getDescription());
    }

    public function testExecuteShowsConfiguration(): void
    {
        $this->parameterBag->method('get')->willReturnCallback(function ($key) {
            return match ($key) {
                'nowo_performance.enabled' => true,
                'nowo_performance.track_queries' => true,
                'nowo_performance.track_request_time' => true,
                'nowo_performance.connection' => 'default',
                'nowo_performance.environments' => ['dev', 'test'],
                default => null,
            };
        });

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Configuration', $output);
        $this->assertStringContainsString('Enabled', $output);
        $this->assertStringContainsString('Track Queries', $output);
        $this->assertStringContainsString('Connection', $output);
    }

    public function testExecuteShowsWarningWhenQueryTrackingDisabled(): void
    {
        $this->parameterBag->method('get')->willReturnCallback(function ($key) {
            return match ($key) {
                'nowo_performance.enabled' => false,
                'nowo_performance.track_queries' => false,
                'nowo_performance.track_request_time' => true,
                'nowo_performance.connection' => 'default',
                'nowo_performance.environments' => ['dev'],
                default => null,
            };
        });

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Query tracking is disabled', $output);
    }

    public function testCommandHelpMentionsDiagnoseAndQueryTracking(): void
    {
        $help = $this->command->getHelp();
        $this->assertStringContainsString('diagnostic', $help);
        $this->assertStringContainsString('query tracking', $help);
        $this->assertStringContainsString('nowo:performance:diagnose', $help);
    }

    public function testExecuteWhenEnabledShowsMiddlewareSection(): void
    {
        $this->parameterBag->method('get')->willReturnCallback(function ($key) {
            return match ($key) {
                'nowo_performance.enabled' => true,
                'nowo_performance.track_queries' => true,
                'nowo_performance.track_request_time' => true,
                'nowo_performance.connection' => 'default',
                'nowo_performance.environments' => ['dev', 'prod'],
                default => null,
            };
        });

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Query Tracking Middleware', $output);
        $this->assertStringContainsString('Initial query count', $output);
        $this->assertStringContainsString('QueryTrackingMiddleware class is available', $output);
        $this->assertStringContainsString('Query Tracking Status', $output);
        $this->assertStringContainsString('How Query Tracking Works', $output);
    }

    public function testExecuteShowsDatabaseTablesSectionWhenTableStatusCheckerInjected(): void
    {
        $this->parameterBag->method('get')->willReturnCallback(function ($key) {
            return match ($key) {
                'nowo_performance.enabled' => true,
                'nowo_performance.track_queries' => true,
                'nowo_performance.track_request_time' => true,
                'nowo_performance.connection' => 'default',
                'nowo_performance.environments' => ['dev'],
                default => null,
            };
        });

        $checker = $this->createMock(TableStatusChecker::class);
        $checker->method('tableExists')->willReturn(true);
        $checker->method('tableIsComplete')->willReturn(true);
        $checker->method('getTableName')->willReturn('routes_data');
        $checker->method('getMissingColumns')->willReturn([]);
        $checker->method('isAccessRecordsEnabled')->willReturn(false);

        $command = new DiagnoseCommand($this->parameterBag, $checker);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Database Tables', $output);
        $this->assertStringContainsString('routes_data', $output);
    }

    public function testExecuteShowsRecordsTableRowAndNoteWhenAccessRecordsEnabledAndColumnsMissing(): void
    {
        $this->parameterBag->method('get')->willReturnCallback(function ($key) {
            return match ($key) {
                'nowo_performance.enabled' => true,
                'nowo_performance.track_queries' => true,
                'nowo_performance.track_request_time' => true,
                'nowo_performance.connection' => 'default',
                'nowo_performance.environments' => ['dev'],
                default => null,
            };
        });

        $checker = $this->createMock(TableStatusChecker::class);
        $checker->method('tableExists')->willReturn(true);
        $checker->method('tableIsComplete')->willReturn(true);
        $checker->method('getTableName')->willReturn('routes_data');
        $checker->method('getMissingColumns')->willReturn([]);
        $checker->method('isAccessRecordsEnabled')->willReturn(true);
        $checker->method('recordsTableExists')->willReturn(true);
        $checker->method('recordsTableIsComplete')->willReturn(false);
        $checker->method('getRecordsTableName')->willReturn('routes_data_records');
        $checker->method('getRecordsMissingColumns')->willReturn(['request_id']);

        $command = new DiagnoseCommand($this->parameterBag, $checker);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Database Tables', $output);
        $this->assertStringContainsString('routes_data_records', $output);
        $this->assertStringContainsString('request_id', $output);
        $this->assertStringContainsString('sync-schema', $output);
        $this->assertStringContainsString('create-records-table', $output);
    }

    public function testCommandHasNoArguments(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertCount(0, $definition->getArguments());
    }

    public function testCommandHelpContainsConfiguration(): void
    {
        $help = $this->command->getHelp();
        $this->assertStringContainsString('configuration', $help);
    }

    public function testCommandDescriptionIsNonEmpty(): void
    {
        $description = $this->command->getDescription();

        $this->assertNotEmpty($description);
        $this->assertStringContainsString('Diagnose', $description);
    }
}
