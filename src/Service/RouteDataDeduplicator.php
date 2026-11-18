<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Entity\RouteDataRecord;
use Nowo\TransactionDoctrine\DoctrineTransactionRunner;

use function array_map;
use function array_values;
use function count;
use function is_numeric;
use function sprintf;

/**
 * Merges duplicate RouteData rows (same env and name) into one, before the unique constraint `uniq_route_env_name`
 * is added (3.18.0, REQ-RUNTIME-003). Releases up to 3.17 could insert one route twice when two workers recorded its
 * first request at the same time.
 *
 * For each duplicated (env, name) the oldest row (lowest id) is kept: the access records of the other rows are moved to
 * it, it takes the latest `last_accessed_at` of the group, and the other rows are deleted. Its review state, HTTP
 * method, parameters and "save access records" flag stay as they were. Each group is merged in its own transaction
 * (short locks); env and name are compared with the column collation, as the unique index does. Rows whose env or name
 * is NULL are never merged (NULLs never collide in a unique index).
 *
 * Stateless: safe as a shared service in a long-running worker.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class RouteDataDeduplicator
{
    /**
     * Merges the duplicate routes of the entity manager's tables.
     *
     * @return int Number of duplicate RouteData rows merged away (deleted)
     */
    public function merge(EntityManagerInterface $entityManager): int
    {
        $connection   = $entityManager->getConnection();
        $routeMeta    = $entityManager->getClassMetadata(RouteData::class);
        $recordMeta   = $entityManager->getClassMetadata(RouteDataRecord::class);
        $routeTable   = $routeMeta->getTableName();
        $recordsTable = $recordMeta->getTableName();
        $id           = $routeMeta->getColumnName('id');
        $env          = $routeMeta->getColumnName('env');
        $name         = $routeMeta->getColumnName('name');
        $lastAccessed = $routeMeta->getColumnName('lastAccessedAt');
        $joinColumn   = $recordMeta->getSingleAssociationJoinColumnName('routeData');
        $hasRecords   = $connection->createSchemaManager()->tablesExist([$recordsTable]);
        $runner       = DoctrineTransactionRunner::forConnection($connection);

        $groups = $connection->fetchAllAssociative(sprintf(
            'SELECT MIN(%1$s) AS keep_id FROM %2$s WHERE %3$s IS NOT NULL AND %4$s IS NOT NULL GROUP BY %3$s, %4$s HAVING COUNT(*) > 1',
            $id,
            $routeTable,
            $env,
            $name,
        ));

        $merged = 0;
        foreach ($groups as $group) {
            $keepId = (int) $group['keep_id'];
            $merged += $runner->run(fn (): int => $this->mergeGroup(
                $connection,
                $keepId,
                $routeTable,
                $hasRecords ? $recordsTable : null,
                $id,
                $env,
                $name,
                $lastAccessed,
                $joinColumn,
            ));
        }

        return $merged;
    }

    /**
     * Merges the duplicates of the route kept as $keepId into it; returns how many rows were deleted.
     */
    private function mergeGroup(
        Connection $connection,
        int $keepId,
        string $routeTable,
        ?string $recordsTable,
        string $id,
        string $env,
        string $name,
        string $lastAccessed,
        string $joinColumn,
    ): int {
        // The duplicates of the kept row, locked in id order (the same order for every merger).
        $duplicateIds = array_values(array_map(
            static fn (mixed $value): int => is_numeric($value) ? (int) $value : 0,
            $connection->fetchFirstColumn(sprintf(
                'SELECT d.%1$s FROM %2$s d INNER JOIN %2$s k ON k.%3$s = d.%3$s AND k.%4$s = d.%4$s WHERE k.%1$s = ? AND d.%1$s <> ? ORDER BY d.%1$s',
                $id,
                $routeTable,
                $env,
                $name,
            ), [$keepId, $keepId]),
        ));

        $latest = $connection->fetchOne(
            sprintf('SELECT MAX(%s) FROM %s WHERE %s IN (?)', $lastAccessed, $routeTable, $id),
            [[$keepId, ...$duplicateIds]],
            [ArrayParameterType::INTEGER],
        );

        if ($recordsTable !== null) {
            $connection->executeStatement(
                sprintf('UPDATE %s SET %s = ? WHERE %s IN (?)', $recordsTable, $joinColumn, $joinColumn),
                [$keepId, $duplicateIds],
                [ParameterType::INTEGER, ArrayParameterType::INTEGER],
            );
        }

        if ($latest !== null && $latest !== false) {
            $connection->executeStatement(
                sprintf('UPDATE %s SET %s = ? WHERE %s = ?', $routeTable, $lastAccessed, $id),
                [$latest, $keepId],
            );
        }

        $connection->executeStatement(
            sprintf('DELETE FROM %s WHERE %s IN (?)', $routeTable, $id),
            [$duplicateIds],
            [ArrayParameterType::INTEGER],
        );

        return count($duplicateIds);
    }
}
