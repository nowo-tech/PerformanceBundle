<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Integration\Command;

use Nowo\PerformanceBundle\Tests\Integration\TestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

final class DiagnoseCommandIntegrationTest extends TestCase
{
    private KernelInterface $kernel;

    protected function setUp(): void
    {
        $this->kernel = new TestKernel('test', true);
        $this->kernel->boot();
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
    }

    public function testDiagnoseCommandRunsSuccessfully(): void
    {
        $application = new Application($this->kernel);
        $application->setAutoExit(false);
        $command = $application->find('nowo:performance:diagnose');
        $tester  = new CommandTester($command);

        $exitCode = $tester->execute([]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Performance Bundle', $tester->getDisplay());
    }

    public function testDiagnoseCommandWithTablesCreatedShowsTableStatus(): void
    {
        $application = new Application($this->kernel);
        $application->setAutoExit(false);
        (new CommandTester($application->find('nowo:performance:create-table')))->execute([]);
        (new CommandTester($application->find('nowo:performance:create-records-table')))->execute([]);

        $tester   = new CommandTester($application->find('nowo:performance:diagnose'));
        $exitCode = $tester->execute([]);

        self::assertSame(0, $exitCode);
        $display = $tester->getDisplay();
        self::assertTrue(
            str_contains($display, 'table') || str_contains($display, 'routes_data') || str_contains($display, 'Configuration'),
            'Diagnose with tables should show table or configuration info',
        );
    }
}
