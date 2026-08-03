<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\DBAL;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Exception;
use PDO;

/**
 * Connection wrapper that tracks queries.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
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
