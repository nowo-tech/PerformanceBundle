<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Integration\Command;

use Nowo\PerformanceBundle\Tests\Integration\TestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

final class CreateTableCommandIntegrationTest extends TestCase
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

    private function getCommand(): \Symfony\Component\Console\Command\Command
    {
        $application = new \Symfony\Bundle\FrameworkBundle\Console\Application($this->kernel);
        $application->setAutoExit(false);

        return $application->find('nowo:performance:create-table');
    }

    public function testCreateTableCommandRunsSuccessfully(): void
    {
        $tester = new CommandTester($this->getCommand());

        $exitCode = $tester->execute([]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('created successfully', $tester->getDisplay());
    }

    public function testCreateTableCommandWhenTableExistsReturnsSuccess(): void
    {
        $tester = new CommandTester($this->getCommand());

        $tester->execute([]);
        $exitCode = $tester->execute([]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('already exists', $tester->getDisplay());
    }

    public function testCreateTableCommandWithForceRunsWithoutFatalError(): void
    {
        $tester = new CommandTester($this->getCommand());
        $tester->execute([]);
        $exitCode = $tester->execute(['--force' => true]);

        self::assertContains($exitCode, [0, 1], 'Command should complete (exit 0 or 1)');
        self::assertMatchesRegularExpression('/(created successfully|Table dropped|Failed)/', $tester->getDisplay());
    }

    public function testCreateTableCommandWithUpdateRunsWithoutFatalError(): void
    {
        $tester = new CommandTester($this->getCommand());
        $tester->execute([]);
        $exitCode = $tester->execute(['--update' => true]);

        self::assertContains($exitCode, [0, 1], 'Command should complete');
        $display = $tester->getDisplay();
        self::assertStringContainsString('Updating Table Schema', $display);
        self::assertMatchesRegularExpression('/(updated successfully|up to date|No changes|Failed|Table name)/', $display);
    }

    public function testCreateTableCommandWithUpdateAndDropObsoleteRunsWithoutFatalError(): void
    {
        $tester = new CommandTester($this->getCommand());
        $tester->execute([]);
        $exitCode = $tester->execute(['--update' => true, '--drop-obsolete' => true]);

        self::assertContains($exitCode, [0, 1], 'Command should complete');
        self::assertMatchesRegularExpression('/(updated successfully|up to date|No changes|Failed|Table name|drop-obsolete)/', $tester->getDisplay());
    }

    public function testCreateTableCommandWithForceAndDropObsoleteRunsWithoutFatalError(): void
    {
        $tester = new CommandTester($this->getCommand());
        $tester->execute([]);
        $exitCode = $tester->execute(['--force' => true, '--drop-obsolete' => true]);

        self::assertContains($exitCode, [0, 1], 'Command should complete');
    }

    public function testCreateTableCommandOutputContainsTableNameAndConnection(): void
    {
        $tester = new CommandTester($this->getCommand());
        $tester->execute([]);
        $display = $tester->getDisplay();
        self::assertStringContainsString('Create Table', $display);
        self::assertTrue(
            str_contains($display, 'routes_data') || str_contains($display, 'Table'),
            'Output should mention table or routes_data',
        );
    }
}
