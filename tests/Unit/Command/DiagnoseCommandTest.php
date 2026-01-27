<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Command;

use Nowo\PerformanceBundle\Command\DiagnoseCommand;
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
}
