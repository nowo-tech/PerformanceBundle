<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Integration\Service;

use Nowo\PerformanceBundle\Service\TableStatusChecker;
use Nowo\PerformanceBundle\Tests\Integration\TestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

final class TableStatusCheckerIntegrationTest extends TestCase
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

    public function testTableExistsReturnsTrueAfterCreateTable(): void
    {
        $this->runCreateTableCommand();
        $checker = $this->kernel->getContainer()->get(TableStatusChecker::class);

        self::assertTrue($checker->tableExists());
        self::assertSame('routes_data', $checker->getTableName());
    }

    public function testRecordsTableExistsAfterCreatingBothTables(): void
    {
        $this->runCreateTableCommand();
        $this->runCreateRecordsTableCommand();
        $checker = $this->kernel->getContainer()->get(TableStatusChecker::class);

        self::assertTrue($checker->tableExists());
        self::assertTrue($checker->recordsTableExists());
        self::assertTrue($checker->isAccessRecordsEnabled());
    }

    public function testGetMainTableStatusReturnsArray(): void
    {
        $this->runCreateTableCommand();
        $checker = $this->kernel->getContainer()->get(TableStatusChecker::class);

        $status = $checker->getMainTableStatus();
        self::assertIsArray($status);
    }

    public function testTableIsCompleteAfterCreate(): void
    {
        $this->runCreateTableCommand();
        $checker = $this->kernel->getContainer()->get(TableStatusChecker::class);

        self::assertTrue($checker->tableIsComplete());
    }

    public function testGetMissingColumnsReturnsArray(): void
    {
        $this->runCreateTableCommand();
        $checker = $this->kernel->getContainer()->get(TableStatusChecker::class);

        $missing = $checker->getMissingColumns();
        self::assertIsArray($missing);
    }

    public function testGetRecordsTableStatusAfterCreatingRecordsTable(): void
    {
        $this->runCreateTableCommand();
        $this->runCreateRecordsTableCommand();
        $checker = $this->kernel->getContainer()->get(TableStatusChecker::class);

        $status = $checker->getRecordsTableStatus();
        self::assertIsArray($status);
    }

    public function testRecordsTableIsComplete(): void
    {
        $this->runCreateTableCommand();
        $this->runCreateRecordsTableCommand();
        $checker = $this->kernel->getContainer()->get(TableStatusChecker::class);

        self::assertTrue($checker->recordsTableIsComplete());
    }

    public function testGetRecordsTableName(): void
    {
        $checker = $this->kernel->getContainer()->get(TableStatusChecker::class);

        self::assertNotEmpty($checker->getRecordsTableName());
    }

    private function runCreateTableCommand(): void
    {
        $application = new Application($this->kernel);
        $application->setAutoExit(false);
        (new CommandTester($application->find('nowo:performance:create-table')))->execute([]);
    }

    private function runCreateRecordsTableCommand(): void
    {
        $application = new Application($this->kernel);
        $application->setAutoExit(false);
        (new CommandTester($application->find('nowo:performance:create-records-table')))->execute([]);
    }
}
