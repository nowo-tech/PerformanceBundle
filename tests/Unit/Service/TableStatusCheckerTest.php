<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Service;

use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\Service\PerformanceCacheService;
use Nowo\PerformanceBundle\Service\TableStatusChecker;
use PHPUnit\Framework\TestCase;

final class TableStatusCheckerTest extends TestCase
{
    public function testGetTableName(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $checker = new TableStatusChecker($registry, 'default', 'routes_data', false);

        $this->assertSame('routes_data', $checker->getTableName());
    }

    public function testIsAccessRecordsEnabledWhenTrue(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $checker = new TableStatusChecker($registry, 'default', 'routes_data', true);

        $this->assertTrue($checker->isAccessRecordsEnabled());
    }

    public function testIsAccessRecordsEnabledWhenFalse(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $checker = new TableStatusChecker($registry, 'default', 'routes_data', false);

        $this->assertFalse($checker->isAccessRecordsEnabled());
    }

    public function testSetCacheService(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $checker = new TableStatusChecker($registry, 'default', 'routes_data', false);
        $cache = $this->createMock(PerformanceCacheService::class);

        $checker->setCacheService($cache);
        $this->addToAssertionCount(1);
    }

    public function testSetCacheServiceNull(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $checker = new TableStatusChecker($registry, 'default', 'routes_data', false);

        $checker->setCacheService(null);
        $this->addToAssertionCount(1);
    }

    public function testRecordsTableExistsWhenAccessRecordsDisabled(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $checker = new TableStatusChecker($registry, 'default', 'routes_data', false);

        $this->assertTrue($checker->recordsTableExists());
    }

    public function testRecordsTableIsCompleteWhenAccessRecordsDisabled(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $checker = new TableStatusChecker($registry, 'default', 'routes_data', false);

        $this->assertTrue($checker->recordsTableIsComplete());
    }

    public function testGetRecordsMissingColumnsWhenAccessRecordsDisabled(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $checker = new TableStatusChecker($registry, 'default', 'routes_data', false);

        $this->assertSame([], $checker->getRecordsMissingColumns());
    }

    public function testGetRecordsTableNameWhenExceptionReturnsSuffix(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManager')->willThrowException(new \RuntimeException('no manager'));

        $checker = new TableStatusChecker($registry, 'default', 'my_table', true);

        $this->assertSame('my_table_records', $checker->getRecordsTableName());
    }

    public function testGetTableNameWithCustomTableName(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $checker = new TableStatusChecker($registry, 'custom_conn', 'custom_performance_table', false);

        $this->assertSame('custom_performance_table', $checker->getTableName());
    }

    public function testGetRecordsTableNameWithCustomBaseTableReturnsSuffixWhenException(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManager')->willThrowException(new \RuntimeException('no manager'));

        $checker = new TableStatusChecker($registry, 'default', 'perf_metrics', true);

        $this->assertSame('perf_metrics_records', $checker->getRecordsTableName());
    }
}
