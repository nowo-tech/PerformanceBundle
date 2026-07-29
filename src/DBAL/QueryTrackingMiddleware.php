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
use Exception;
use PDO;

/**
 * DBAL Middleware for tracking database queries.
 *
 * Counters live on {@see QueryTrackingCounters} (shared DI service) so FrankenPHP
 * workers do not leak mutable static state across requests.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class QueryTrackingMiddleware implements Middleware
{
    public function __construct(
        private readonly QueryTrackingCounters $counters = new QueryTrackingCounters(),
    ) {
    }

    public function getCounters(): QueryTrackingCounters
    {
        return $this->counters;
    }

    public function wrap(Driver $driver): Driver
    {
        $counters = $this->counters;

        return new class($driver, $counters) extends AbstractDriverMiddleware {
            public function __construct(
                Driver $driver,
                private readonly QueryTrackingCounters $counters,
            ) {
                parent::__construct($driver);
            }

            public function connect(array $params): Connection
            {
                return new QueryTrackingConnection(
                    parent::connect($params),
                    $this->counters,
                );
            }
        };
    }
}

/**
 * Connection wrapper that tracks queries.
 */
class QueryTrackingConnection implements Connection
{
    public function __construct(
        private readonly Connection $connection,
        private readonly QueryTrackingCounters $counters,
    ) {
    }

    public function prepare(string $sql): Statement
    {
        $queryId = $this->counters->nextQueryId($this);
        $this->counters->startQuery($queryId);

        $statement = $this->connection->prepare($sql);
        $counters  = $this->counters;

        return new class($statement, $queryId, $counters) extends AbstractStatementMiddleware {
            public function __construct(
                Statement $statement,
                private readonly string $queryId,
                private readonly QueryTrackingCounters $counters,
            ) {
                parent::__construct($statement);
            }

            public function execute(mixed $params = null): Result
            {
                try {
                    $result = parent::execute();
                    $this->counters->stopQuery($this->queryId);

                    return $result;
                } catch (Exception $e) {
                    $this->counters->stopQuery($this->queryId);
                    throw $e;
                }
            }
        };
    }

    public function query(string $sql): Result
    {
        $queryId = $this->counters->nextQueryId($this);
        $this->counters->startQuery($queryId);

        try {
            $result = $this->connection->query($sql);
            $this->counters->stopQuery($queryId);

            return $result;
        } catch (Exception $e) {
            $this->counters->stopQuery($queryId);
            throw $e;
        }
    }

    public function exec(string $sql): int
    {
        $queryId = $this->counters->nextQueryId($this);
        $this->counters->startQuery($queryId);

        try {
            $result = $this->connection->exec($sql);
            $this->counters->stopQuery($queryId);

            return (int) $result;
        } catch (Exception $e) {
            $this->counters->stopQuery($queryId);
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
        return $this->connection->getServerVersion();
    }

    public function quote(string $value, int $type = PDO::PARAM_STR): string
    {
        return $this->connection->quote($value);
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
