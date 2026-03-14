<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Command;

use DateTimeImmutable;
use Nowo\PerformanceBundle\Command\PurgeAccessRecordsCommand;
use Nowo\PerformanceBundle\Repository\RouteDataRecordRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class PurgeAccessRecordsCommandTest extends TestCase
{
    private \PHPUnit\Framework\MockObject\MockObject $parameterBag;

    protected function setUp(): void
    {
        $this->parameterBag = $this->createMock(ParameterBagInterface::class);
    }

    public function testCommandName(): void
    {
        $repo = $this->createMock(RouteDataRecordRepository::class);
        $this->parameterBag->method('get')->with('nowo_performance.enable_access_records')->willReturn(true);
        $command = new PurgeAccessRecordsCommand($repo, $this->parameterBag);
        $this->assertSame('nowo:performance:purge-records', $command->getName());
    }

    public function testExecuteFailsWhenAccessRecordsDisabled(): void
    {
        $this->parameterBag->method('get')->willReturnMap([
            ['nowo_performance.enable_access_records', false],
        ]);
        $command = new PurgeAccessRecordsCommand($this->createMock(RouteDataRecordRepository::class), $this->parameterBag);
        $input   = new ArrayInput([]);
        $output  = new BufferedOutput();

        $code = $command->run($input, $output);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Access records are disabled', $output->fetch());
    }

    public function testExecuteFailsWhenRecordRepositoryNull(): void
    {
        $this->parameterBag->method('get')->with('nowo_performance.enable_access_records')->willReturn(true);
        $command = new PurgeAccessRecordsCommand(null, $this->parameterBag);
        $input   = new ArrayInput([]);
        $output  = new BufferedOutput();

        $code = $command->run($input, $output);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('RouteDataRecordRepository is not available', $output->fetch());
    }

    public function testExecuteAllWithDryRun(): void
    {
        $repo = $this->createMock(RouteDataRecordRepository::class);
        $repo->expects($this->never())->method('deleteAllRecords');
        $this->parameterBag->method('get')->with('nowo_performance.enable_access_records')->willReturn(true);
        $command = new PurgeAccessRecordsCommand($repo, $this->parameterBag);
        $input   = new ArrayInput(['--all' => true, '--dry-run' => true]);
        $output  = new BufferedOutput();

        $code = $command->run($input, $output);

        $this->assertSame(0, $code);
        $display = $output->fetch();
        $this->assertStringContainsString('Dry-run', $display);
        $this->assertStringContainsString('would delete all access records', $display);
    }

    public function testExecuteAllWithDryRunAndEnv(): void
    {
        $repo = $this->createMock(RouteDataRecordRepository::class);
        $repo->expects($this->never())->method('deleteAllRecords');
        $this->parameterBag->method('get')->with('nowo_performance.enable_access_records')->willReturn(true);
        $command = new PurgeAccessRecordsCommand($repo, $this->parameterBag);
        $input   = new ArrayInput(['--all' => true, '--dry-run' => true, '--env' => 'prod']);
        $output  = new BufferedOutput();

        $code = $command->run($input, $output);

        $this->assertSame(0, $code);
        $display = $output->fetch();
        $this->assertStringContainsString('would delete all access records', $display);
        $this->assertStringContainsString('"prod"', $display);
    }

    public function testExecuteAllDeletesRecords(): void
    {
        $repo = $this->createMock(RouteDataRecordRepository::class);
        $repo->expects($this->once())->method('deleteAllRecords')->with(null)->willReturn(42);
        $this->parameterBag->method('get')->with('nowo_performance.enable_access_records')->willReturn(true);
        $command = new PurgeAccessRecordsCommand($repo, $this->parameterBag);
        $input   = new ArrayInput(['--all' => true]);
        $output  = new BufferedOutput();

        $code = $command->run($input, $output);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Deleted 42 access record(s)', $output->fetch());
    }

    public function testExecuteAllWithEnvDeletesRecordsForEnv(): void
    {
        $repo = $this->createMock(RouteDataRecordRepository::class);
        $repo->expects($this->once())->method('deleteAllRecords')->with('dev')->willReturn(10);
        $this->parameterBag->method('get')->with('nowo_performance.enable_access_records')->willReturn(true);
        $command = new PurgeAccessRecordsCommand($repo, $this->parameterBag);
        $input   = new ArrayInput(['--all' => true, '--env' => 'dev']);
        $output  = new BufferedOutput();

        $code = $command->run($input, $output);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Deleted 10 access record(s)', $output->fetch());
    }

    public function testExecuteFailsWhenNoRetentionAndNoOlderThan(): void
    {
        $repo = $this->createMock(RouteDataRecordRepository::class);
        $this->parameterBag->method('get')->willReturnMap([
            ['nowo_performance.enable_access_records', true],
            ['nowo_performance.access_records_retention_days', null],
        ]);
        $command = new PurgeAccessRecordsCommand($repo, $this->parameterBag);
        $input   = new ArrayInput([]);
        $output  = new BufferedOutput();

        $code = $command->run($input, $output);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('No retention configured', $output->fetch());
    }

    public function testExecuteFailsWhenDaysLessThanOne(): void
    {
        $repo = $this->createMock(RouteDataRecordRepository::class);
        $this->parameterBag->method('get')->willReturnMap([
            ['nowo_performance.enable_access_records', true],
            ['nowo_performance.access_records_retention_days', null],
        ]);
        $command = new PurgeAccessRecordsCommand($repo, $this->parameterBag);
        $input   = new ArrayInput(['--older-than' => '0']);
        $output  = new BufferedOutput();

        $code = $command->run($input, $output);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Days must be at least 1', $output->fetch());
    }

    public function testExecuteOlderThanWithDryRun(): void
    {
        $repo = $this->createMock(RouteDataRecordRepository::class);
        $repo->expects($this->never())->method('deleteOlderThan');
        $this->parameterBag->method('get')->willReturnMap([
            ['nowo_performance.enable_access_records', true],
            ['nowo_performance.access_records_retention_days', null],
        ]);
        $command = new PurgeAccessRecordsCommand($repo, $this->parameterBag);
        $input   = new ArrayInput(['--older-than' => '30', '--dry-run' => true]);
        $output  = new BufferedOutput();

        $code = $command->run($input, $output);

        $this->assertSame(0, $code);
        $display = $output->fetch();
        $this->assertStringContainsString('Dry-run', $display);
        $this->assertStringContainsString('would delete records older than', $display);
    }

    public function testExecuteOlderThanDeletesRecords(): void
    {
        $repo = $this->createMock(RouteDataRecordRepository::class);
        $repo->expects($this->once())
            ->method('deleteOlderThan')
            ->with(
                $this->callback(static function (DateTimeImmutable $d): bool {
                    $expected = new DateTimeImmutable('-7 days');

                    return $d->format('Y-m-d') === $expected->format('Y-m-d');
                }),
                null,
            )
            ->willReturn(5);
        $this->parameterBag->method('get')->willReturnMap([
            ['nowo_performance.enable_access_records', true],
            ['nowo_performance.access_records_retention_days', null],
        ]);
        $command = new PurgeAccessRecordsCommand($repo, $this->parameterBag);
        $input   = new ArrayInput(['--older-than' => '7']);
        $output  = new BufferedOutput();

        $code = $command->run($input, $output);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Deleted 5 access record(s) older than', $output->fetch());
    }

    public function testExecuteOlderThanUsesRetentionFromConfig(): void
    {
        $repo = $this->createMock(RouteDataRecordRepository::class);
        $repo->expects($this->once())
            ->method('deleteOlderThan')
            ->with(
                $this->callback(static function (DateTimeImmutable $d): bool {
                    $expected = new DateTimeImmutable('-90 days');

                    return $d->format('Y-m-d') === $expected->format('Y-m-d');
                }),
                'prod',
            )
            ->willReturn(3);
        $this->parameterBag->method('get')->willReturnMap([
            ['nowo_performance.enable_access_records', true],
            ['nowo_performance.access_records_retention_days', 90],
        ]);
        $command = new PurgeAccessRecordsCommand($repo, $this->parameterBag);
        $input   = new ArrayInput(['--env' => 'prod']);
        $output  = new BufferedOutput();

        $code = $command->run($input, $output);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Deleted 3 access record(s)', $output->fetch());
    }

    public function testExecuteOlderThanWithEmptyEnvOptionTreatedAsNull(): void
    {
        $repo = $this->createMock(RouteDataRecordRepository::class);
        $repo->expects($this->once())->method('deleteOlderThan')->with($this->anything(), null)->willReturn(0);
        $this->parameterBag->method('get')->willReturnMap([
            ['nowo_performance.enable_access_records', true],
            ['nowo_performance.access_records_retention_days', 30],
        ]);
        $command = new PurgeAccessRecordsCommand($repo, $this->parameterBag);
        $input   = new ArrayInput(['--env' => '']);
        $output  = new BufferedOutput();

        $code = $command->run($input, $output);

        $this->assertSame(0, $code);
    }

    public function testCommandHasOptions(): void
    {
        $repo = $this->createMock(RouteDataRecordRepository::class);
        $this->parameterBag->method('get')->with('nowo_performance.enable_access_records')->willReturn(true);
        $command = new PurgeAccessRecordsCommand($repo, $this->parameterBag);
        $def     = $command->getDefinition();

        $this->assertTrue($def->hasOption('older-than'));
        $this->assertTrue($def->hasOption('all'));
        $this->assertTrue($def->hasOption('env'));
        $this->assertTrue($def->hasOption('dry-run'));
    }

    public function testHelpContainsPurgeAndExamples(): void
    {
        $repo = $this->createMock(RouteDataRecordRepository::class);
        $this->parameterBag->method('get')->with('nowo_performance.enable_access_records')->willReturn(true);
        $command = new PurgeAccessRecordsCommand($repo, $this->parameterBag);
        $help    = $command->getHelp();

        $this->assertStringContainsString('purge', strtolower($help));
        $this->assertStringContainsString('--older-than', $help);
        $this->assertStringContainsString('--all', $help);
        $this->assertStringContainsString('--dry-run', $help);
    }
}
