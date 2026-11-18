<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Service;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Nowo\PerformanceBundle\Service\RouteDataDeduplicator;
use Nowo\PerformanceBundle\Tests\Support\OrmTestSupport;
use PHPUnit\Framework\TestCase;

/**
 * Duplicate RouteData rows (same env and name, from releases before the unique constraint) are merged into the oldest
 * row before uniq_route_env_name is created (SQLite; MySQL, MariaDB and PostgreSQL in tests/Integration/Concurrency).
 */
final class RouteDataDeduplicatorTest extends TestCase
{
    private EntityManager $em;
    private Connection $connection;

    protected function setUp(): void
    {
        $this->em         = OrmTestSupport::entityManager($this);
        $this->connection = $this->em->getConnection();
        $this->connection->executeStatement('DROP INDEX uniq_route_env_name');
    }

    public function testMergesEachDuplicatedRouteIntoItsOldestRow(): void
    {
        $keep  = $this->route('home', 'prod', '2026-01-01 00:00:00');
        $dup   = $this->route('home', 'prod', '2026-05-01 00:00:00');
        $other = $this->route('home', 'dev', '2026-02-01 00:00:00');
        $noAt1 = $this->route('about', 'prod', null);
        $noAt2 = $this->route('about', 'prod', null);
        $this->route(null, 'prod', null);
        $this->route(null, 'prod', null);
        $this->record($keep, 'a');
        $this->record($dup, 'b');
        $this->record($dup, 'c');
        $this->record($noAt2, 'd');

        self::assertSame(2, (new RouteDataDeduplicator())->merge($this->em));

        self::assertSame([$keep], $this->ids("env = 'prod' AND name = 'home'"));
        self::assertSame([$noAt1], $this->ids("env = 'prod' AND name = 'about'"));
        self::assertSame([$other], $this->ids("env = 'dev' AND name = 'home'"));
        self::assertCount(2, $this->ids('name IS NULL'), 'Rows without a name are never merged.');
        self::assertSame(3, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM routes_data_records WHERE route_data_id = ?', [$keep]));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM routes_data_records WHERE route_data_id = ?', [$noAt1]));
        self::assertSame('2026-05-01 00:00:00', $this->connection->fetchOne('SELECT last_accessed_at FROM routes_data WHERE id = ?', [$keep]));
        self::assertNull($this->connection->fetchOne('SELECT last_accessed_at FROM routes_data WHERE id = ?', [$noAt1]));
        self::assertSame(0, $this->connection->getTransactionNestingLevel());

        self::assertSame(0, (new RouteDataDeduplicator())->merge($this->em), 'A second run has nothing to merge.');
    }

    public function testWorksWithoutTheAccessRecordsTable(): void
    {
        $this->connection->executeStatement('DROP TABLE routes_data_records');
        $keep = $this->route('home', 'prod', '2026-01-01 00:00:00');
        $this->route('home', 'prod', '2026-01-02 00:00:00');

        self::assertSame(1, (new RouteDataDeduplicator())->merge($this->em));
        self::assertSame([$keep], $this->ids("name = 'home'"));
    }

    private function route(?string $name, string $env, ?string $lastAccessedAt): int
    {
        $this->connection->insert('routes_data', ['name' => $name, 'env' => $env, 'last_accessed_at' => $lastAccessedAt, 'reviewed' => 0, 'save_access_records' => 1]);

        return (int) $this->connection->lastInsertId();
    }

    private function record(int $routeId, string $requestId): void
    {
        $this->connection->insert('routes_data_records', ['route_data_id' => $routeId, 'accessed_at' => '2026-01-01 00:00:00', 'request_id' => $requestId]);
    }

    /**
     * @return list<int>
     */
    private function ids(string $where): array
    {
        return array_map(intval(...), $this->connection->fetchFirstColumn('SELECT id FROM routes_data WHERE ' . $where . ' ORDER BY id'));
    }
}
