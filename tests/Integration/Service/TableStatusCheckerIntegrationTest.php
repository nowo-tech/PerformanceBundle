<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Integration\Service;

use Doctrine\DBAL\Connection;
use Nowo\PerformanceBundle\Service\TableStatusChecker;
use Nowo\PerformanceBundle\Tests\Integration\TestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Integration tests for TableStatusChecker against a real DB (MySQL in docker test env).
 * Covers getRecordsMissingColumns when the records table is missing and schema introspection paths.
 */
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

    public function testGetRecordsMissingColumnsWhenTableMissingReturnsExpectedColumns(): void
    {
        $this->createTables();

        $registry = $this->kernel->getContainer()->get('doctrine');
        $checker  = new TableStatusChecker($registry, 'default', 'routes_data', true);

        /** @var Connection $connection */
        $connection = $registry->getConnection('default');
        $connection->executeStatement('DROP TABLE IF EXISTS routes_data_records');

        $missing = $checker->getRecordsMissingColumns();

        self::assertNotSame([], $missing, 'When the records table is missing, all entity columns are reported as missing');

        // Recreate for other tests / teardown
        (new CommandTester((new Application($this->kernel))->find('nowo:performance:create-records-table')))->execute([]);
    }

    public function testRecordsTableIsCompleteWhenTableDroppedReturnsFalse(): void
    {
        $this->createTables();

        $registry = $this->kernel->getContainer()->get('doctrine');
        $checker  = new TableStatusChecker($registry, 'default', 'routes_data', true);

        /** @var Connection $connection */
        $connection = $registry->getConnection('default');
        $connection->executeStatement('DROP TABLE IF EXISTS routes_data_records');

        self::assertFalse($checker->recordsTableIsComplete());

        (new CommandTester((new Application($this->kernel))->find('nowo:performance:create-records-table')))->execute([]);
    }

    private function createTables(): void
    {
        $application = new Application($this->kernel);
        $application->setAutoExit(false);
        (new CommandTester($application->find('nowo:performance:create-table')))->execute([]);
        (new CommandTester($application->find('nowo:performance:create-records-table')))->execute([]);
    }
}
