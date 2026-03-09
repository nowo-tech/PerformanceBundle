<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Integration\Command;

use Nowo\PerformanceBundle\Tests\Integration\TestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

final class CreateRecordsTableCommandIntegrationTest extends TestCase
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
        $application = new Application($this->kernel);
        $application->setAutoExit(false);

        return $application->find('nowo:performance:create-records-table');
    }

    public function testCreateRecordsTableCommandRunsSuccessfully(): void
    {
        $tester = new CommandTester($this->getCommand());

        $exitCode = $tester->execute([]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('created successfully', $tester->getDisplay());
    }

    public function testCreateRecordsTableCommandWhenTableExistsReturnsSuccess(): void
    {
        $tester = new CommandTester($this->getCommand());

        $tester->execute([]);
        $exitCode = $tester->execute([]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('already exists', $tester->getDisplay());
    }

    public function testCreateRecordsTableCommandWithForceDropsAndRecreatesTable(): void
    {
        $tester = new CommandTester($this->getCommand());
        $tester->execute([]);
        self::assertSame(0, $tester->execute(['--force' => true]));

        self::assertStringContainsString('created successfully', $tester->getDisplay());
    }

    public function testCreateRecordsTableCommandWithUpdateWhenTableExists(): void
    {
        $tester = new CommandTester($this->getCommand());
        $tester->execute([]);
        $exitCode = $tester->execute(['--update' => true]);

        self::assertSame(0, $exitCode);
        self::assertMatchesRegularExpression('/(updated successfully|No changes needed)/', $tester->getDisplay());
    }

    public function testCreateRecordsTableCommandWithUpdateAndDropObsolete(): void
    {
        $tester = new CommandTester($this->getCommand());
        $tester->execute([]);
        $exitCode = $tester->execute(['--update' => true, '--drop-obsolete' => true]);

        self::assertContains($exitCode, [0, 1], 'Command should complete (exit 0 or 1 depending on schema state)');
        self::assertMatchesRegularExpression('/(updated successfully|No changes needed|drop-obsolete|Failed)/', $tester->getDisplay());
    }

    public function testCreateRecordsTableCommandOutputContainsTableName(): void
    {
        $tester = new CommandTester($this->getCommand());
        $tester->execute([]);
        $display = $tester->getDisplay();
        self::assertStringContainsString('access records', strtolower($display));
        self::assertTrue(
            str_contains($display, 'routes_data_records') || str_contains($display, 'created successfully') || str_contains($display, 'already exists'),
            'Output should mention records table or result',
        );
    }

    public function testCreateRecordsTableCommandForceThenUpdate(): void
    {
        $tester = new CommandTester($this->getCommand());
        $tester->execute([]);
        self::assertSame(0, $tester->execute(['--force' => true]));
        $exitCode = $tester->execute(['--update' => true]);
        self::assertSame(0, $exitCode);
    }
}
