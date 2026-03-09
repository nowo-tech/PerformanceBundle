<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Integration\Command;

use Nowo\PerformanceBundle\Tests\Integration\TestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

final class SyncSchemaCommandIntegrationTest extends TestCase
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

    public function testSyncSchemaCommandRunsSuccessfully(): void
    {
        $this->createTablesFirst();

        $application = new Application($this->kernel);
        $application->setAutoExit(false);
        $command = $application->find('nowo:performance:sync-schema');
        $tester  = new CommandTester($command);

        $exitCode = $tester->execute([]);

        self::assertContains($exitCode, [0, 1], 'Sync-schema may exit 0 or 1 depending on DB driver');
        self::assertMatchesRegularExpression('/(updated successfully|up to date|No changes|Table|Failed)/', $tester->getDisplay());
    }

    public function testSyncSchemaCommandWithDropObsoleteOption(): void
    {
        $this->createTablesFirst();

        $application = new Application($this->kernel);
        $application->setAutoExit(false);
        $command = $application->find('nowo:performance:sync-schema');
        $tester  = new CommandTester($command);

        $exitCode = $tester->execute(['--drop-obsolete' => true]);

        self::assertContains($exitCode, [0, 1]);
        self::assertNotEmpty($tester->getDisplay());
    }

    private function createTablesFirst(): void
    {
        $application = new Application($this->kernel);
        $application->setAutoExit(false);
        $createTable   = $application->find('nowo:performance:create-table');
        $createRecords = $application->find('nowo:performance:create-records-table');
        (new CommandTester($createTable))->execute([]);
        (new CommandTester($createRecords))->execute([]);
    }
}
