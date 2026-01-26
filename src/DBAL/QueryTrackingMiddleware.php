<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\DBAL;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;

/**
 * DBAL Middleware for tracking database queries.
 *
 * This middleware intercepts all database queries and tracks their count and execution time.
 * Compatible with DBAL 3.x (which removed SQLLogger).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
class QueryTrackingMiddleware implements Middleware
{
    /**
     * Query count tracker.
     */
    private static int $queryCount = 0;

    /**
     * Total query execution time in seconds.
     */
    private static float $totalQueryTime = 0.0;

    /**
     * Start times for queries being tracked.
     *
     * @var array<string, float>
     */
    private static array $queryStartTimes = [];

    public function wrap(Driver $driver): Driver
    {
        return new class($driver) extends AbstractDriverMiddleware {
            public function connect(array $params): Connection
            {
                return new QueryTrackingConnection(
                    parent::connect($params)
                );
            }
        };
    }

    /**
     * Get the total number of queries executed.
     *
     * @return int The query count
     */
    public static function getQueryCount(): int
    {
        return self::$queryCount;
    }

    /**
     * Get the total execution time for all queries.
     *
     * @return float The total query time in seconds
     */
    public static function getTotalQueryTime(): float
    {
        return self::$totalQueryTime;
    }

    /**
     * Reset all query tracking metrics.
     */
    public static function reset(): void
    {
        self::$queryCount = 0;
        self::$totalQueryTime = 0.0;
        self::$queryStartTimes = [];
    }

    /**
     * Record the start of a query execution.
     *
     * @param string $queryId Unique identifier for the query
     */
    public static function startQuery(string $queryId): void
    {
        self::$queryStartTimes[$queryId] = microtime(true);
    }

    /**
     * Record the end of a query execution.
     *
     * @param string $queryId Unique identifier for the query
     */
    public static function stopQuery(string $queryId): void
    {
        if (!isset(self::$queryStartTimes[$queryId])) {
            return;
        }

        ++self::$queryCount;
        self::$totalQueryTime += microtime(true) - self::$queryStartTimes[$queryId];
        unset(self::$queryStartTimes[$queryId]);
    }
}

/**
 * Connection wrapper that tracks queries.
 */
class QueryTrackingConnection implements Connection
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function prepare(string $sql): Statement
    {
        $queryId = spl_object_hash($this).'_'.md5($sql);
        QueryTrackingMiddleware::startQuery($queryId);

        $statement = $this->connection->prepare($sql);

        return new class($statement, $queryId) extends AbstractStatementMiddleware {
            private string $queryId;

            public function __construct(Statement $statement, string $queryId)
            {
                parent::__construct($statement);
                $this->queryId = $queryId;
            }

            public function execute($params = null): Result
            {
                $startTime = microtime(true);
                try {
                    $result = parent::execute($params);
                    QueryTrackingMiddleware::stopQuery($this->queryId);

                    return $result;
                } catch (\Exception $e) {
                    QueryTrackingMiddleware::stopQuery($this->queryId);
                    throw $e;
                }
            }
        };
    }

    public function query(string $sql): Result
    {
        $queryId = spl_object_hash($this).'_'.md5($sql);
        QueryTrackingMiddleware::startQuery($queryId);

        try {
            $result = $this->connection->query($sql);
            QueryTrackingMiddleware::stopQuery($queryId);

            return $result;
        } catch (\Exception $e) {
            QueryTrackingMiddleware::stopQuery($queryId);
            throw $e;
        }
    }

    public function exec(string $sql): int
    {
        $queryId = spl_object_hash($this).'_'.md5($sql);
        QueryTrackingMiddleware::startQuery($queryId);

        try {
            $result = $this->connection->exec($sql);
            QueryTrackingMiddleware::stopQuery($queryId);

            return $result;
        } catch (\Exception $e) {
            QueryTrackingMiddleware::stopQuery($queryId);
            throw $e;
        }
    }

    public function beginTransaction(): void
    {
        $this->connection->beginTransaction();
    }

    public function commit(): void
    {
        $this->connection->commit();
    }

    public function rollBack(): void
    {
        $this->connection->rollBack();
    }

    public function getServerVersion(): string
    {
        return $this->connection->getServerVersion() ?? '';
    }

    public function quote(string $value, int $type = \PDO::PARAM_STR): string
    {
        return $this->connection->quote($value, $type);
    }

    public function lastInsertId(): string|int
    {
        return $this->connection->lastInsertId();
    }

    public function getNativeConnection(): mixed
    {
        return $this->connection->getNativeConnection();
    }
}
