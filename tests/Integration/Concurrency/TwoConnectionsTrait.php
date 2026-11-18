<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Integration\Concurrency;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Nowo\PerformanceBundle\Tests\Support\OrmTestSupport;
use Throwable;

use function explode;
use function getenv;
use function getmypid;
use function in_array;
use function is_string;

/**
 * Two connections on one dedicated real database (REQ-RUNTIME-003 race tests): `$this->worker` is the connection the
 * bundle writes with, `$this->other` plays a second worker. MySQL 8.4 (DATABASE_URL), MariaDB 11 (DATABASE_URL_MARIADB)
 * and PostgreSQL 16 (DATABASE_URL_PG); a driver without a URL is skipped, unless it is listed in
 * PERF_REQUIRE_DATABASES (comma-separated: mysql,mariadb,postgresql), as the "Databases" CI job does.
 *
 * @phpstan-require-extends \PHPUnit\Framework\TestCase
 */
trait TwoConnectionsTrait
{
    private ?Connection $worker = null;

    private ?Connection $other = null;

    private ?EntityManager $workerEm = null;

    private string $driver = '';

    /**
     * @return iterable<string, array{string}>
     */
    public static function databases(): iterable
    {
        yield 'mysql' => ['mysql'];
        yield 'mariadb' => ['mariadb'];
        yield 'postgresql' => ['postgresql'];
    }

    private static function databaseName(): string
    {
        return 'perf_race_' . getmypid();
    }

    /**
     * Connects both connections to a fresh dedicated database and creates the bundle's tables on it.
     */
    protected function connectBoth(string $driver): EntityManager
    {
        $params = $this->serverParams($driver);

        try {
            $server = DriverManager::getConnection(['dbname' => $driver === 'postgresql' ? 'postgres' : null] + $params);
            $server->executeStatement('DROP DATABASE IF EXISTS ' . self::databaseName());
            $server->executeStatement('CREATE DATABASE ' . self::databaseName());
            $server->close();
        } catch (Throwable $e) {
            $this->skipOrFail($driver, $driver . ' unavailable: ' . $e->getMessage());
        }

        // Only now: tearDown drops the database of a driver that was actually set up.
        $this->driver = $driver;

        $this->worker   = DriverManager::getConnection(['dbname' => self::databaseName()] + $params);
        $this->other    = DriverManager::getConnection(['dbname' => self::databaseName()] + $params);
        $this->workerEm = OrmTestSupport::entityManager($this, $this->worker);

        return $this->workerEm;
    }

    protected function dropBoth(): void
    {
        $this->workerEm?->close();
        $this->worker?->close();
        $this->other?->close();
        $this->workerEm = null;
        $this->worker   = null;
        $this->other    = null;
        if ($this->driver === '') {
            return;
        }

        $params = $this->serverParams($this->driver);
        $server = DriverManager::getConnection(['dbname' => $this->driver === 'postgresql' ? 'postgres' : null] + $params);
        $server->executeStatement('DROP DATABASE IF EXISTS ' . self::databaseName());
        $server->close();
        $this->driver = '';
    }

    /**
     * Makes a lock wait on the worker connection give up quickly (MySQL / MariaDB: 1 s, PostgreSQL: 300 ms).
     */
    protected function shortLockWait(Connection $connection): void
    {
        $connection->executeStatement($this->driver === 'postgresql' ? "SET lock_timeout = '300ms'" : 'SET SESSION innodb_lock_wait_timeout = 1');
    }

    /**
     * Inserts a route row on a connection (autocommit) and returns its id.
     */
    protected function insertRoute(Connection $connection, string $name, string $env = 'prod', string $lastAccessedAt = '2026-11-18 09:00:00'): int
    {
        $connection->insert('routes_data', [
            'name'                => $name,
            'env'                 => $env,
            'created_at'          => '2026-11-18 09:00:00',
            'last_accessed_at'    => $lastAccessedAt,
            'reviewed'            => false,
            'save_access_records' => true,
        ], ['reviewed' => 'boolean', 'save_access_records' => 'boolean']);

        return (int) $connection->lastInsertId();
    }

    /**
     * Inserts an access record on a connection (autocommit).
     */
    protected function insertRecord(Connection $connection, int $routeId, ?string $requestId, string $accessedAt = '2026-11-18 09:00:00'): void
    {
        $connection->insert('routes_data_records', ['route_data_id' => $routeId, 'accessed_at' => $accessedAt, 'request_id' => $requestId]);
    }

    /**
     * @param list<mixed> $params
     */
    protected function rows(string $sql, array $params = []): int
    {
        return (int) $this->other?->fetchOne($sql, $params);
    }

    /**
     * @return array<string, mixed>
     */
    private function serverParams(string $driver): array
    {
        $variable = match ($driver) {
            'mysql'   => 'DATABASE_URL',
            'mariadb' => 'DATABASE_URL_MARIADB',
            default   => 'DATABASE_URL_PG',
        };
        $url = getenv($variable);
        if (!is_string($url) || $url === '') {
            $this->skipOrFail($driver, 'No ' . $driver . ' database configured (' . $variable . ').');
        }

        return (new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql']))->parse($url);
    }

    private function skipOrFail(string $driver, string $reason): never
    {
        $required = getenv('PERF_REQUIRE_DATABASES');
        if (is_string($required) && in_array($driver, explode(',', $required), true)) {
            self::fail($reason);
        }

        self::markTestSkipped($reason);
    }
}
