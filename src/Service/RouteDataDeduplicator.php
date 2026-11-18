<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Entity\RouteDataRecord;
use Nowo\TransactionDoctrine\DoctrineTransactionRunner;

use function count;
use function sprintf;

/**
 * Merges duplicate RouteData rows (same env and name) into one, before the unique constraint `uniq_route_env_name`
 * is added (3.18.0, REQ-RUNTIME-003). Releases up to 3.17 could insert one route twice when two workers recorded its
 * first request at the same time.
 *
 * For each duplicated (env, name) the oldest row (lowest id) is kept, and the group is first locked in id order
 * (`SELECT … FOR UPDATE`). The access records of the other rows are moved to it, and it takes from the group:
 * - the latest `last_accessed_at`;
 * - `save_access_records` AND-ed over the group (one "do not save" wins);
 * - `reviewed` OR-ed over the group, with `reviewed_at`, `reviewed_by`, `queries_improved` and `time_improved` of the
 *   most recently reviewed row.
 * Its HTTP method and parameters stay as they were; the other rows are deleted. Each group is merged in its own
 * transaction (short locks); env and name are compared with the column collation, as the unique index does. Rows whose
 * env or name is NULL are never merged (NULLs never collide in a unique index).
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
        $connection = $entityManager->getConnection();
        $routeMeta  = $entityManager->getClassMetadata(RouteData::class);
        $recordMeta = $entityManager->getClassMetadata(RouteDataRecord::class);
        $columns    = [];
        foreach (['id', 'env', 'name', 'lastAccessedAt', 'saveAccessRecords', 'reviewed', 'reviewedAt', 'reviewedBy', 'queriesImproved', 'timeImproved'] as $field) {
            $columns[$field] = $routeMeta->getColumnName($field);
        }
        $routeTable   = $routeMeta->getTableName();
        $recordsTable = $recordMeta->getTableName();
        $joinColumn   = $recordMeta->getSingleAssociationJoinColumnName('routeData');
        $hasRecords   = $connection->createSchemaManager()->tablesExist([$recordsTable]);
        $runner       = DoctrineTransactionRunner::forConnection($connection);

        $groups = $connection->fetchAllAssociative(sprintf(
            'SELECT MIN(%1$s) AS keep_id FROM %2$s WHERE %3$s IS NOT NULL AND %4$s IS NOT NULL GROUP BY %3$s, %4$s HAVING COUNT(*) > 1',
            $columns['id'],
            $routeTable,
            $columns['env'],
            $columns['name'],
        ));

        $merged = 0;
        foreach ($groups as $group) {
            $keepId = (int) $group['keep_id'];
            $merged += $runner->run(fn (): int => $this->mergeGroup(
                $connection,
                $keepId,
                $routeTable,
                $hasRecords ? $recordsTable : null,
                $joinColumn,
                $columns,
            ));
        }

        return $merged;
    }

    /**
     * Merges the duplicates of the route kept as $keepId into it; returns how many rows were deleted.
     *
     * @param array<string, string> $c Column names by field name
     */
    private function mergeGroup(
        Connection $connection,
        int $keepId,
        string $routeTable,
        ?string $recordsTable,
        string $joinColumn,
        array $c,
    ): int {
        $platform = $connection->getDatabasePlatform();

        // Lock the whole group (kept row and duplicates) in id order, the same order for every merger, before reading
        // or moving anything: a live worker that inserts an access record for a duplicate (FK check: FOR KEY SHARE on
        // PostgreSQL, a shared lock on MySQL / MariaDB) waits for the merge, then fails on the foreign key instead of
        // having its record cascade-deleted silently. SQLite serializes writers and has no FOR UPDATE.
        $rows = $connection->fetchAllAssociative(sprintf(
            'SELECT d.%1$s AS id, d.%5$s AS last_accessed_at, d.%6$s AS save_access_records, d.%7$s AS reviewed, d.%8$s AS reviewed_at, d.%9$s AS reviewed_by, d.%10$s AS queries_improved, d.%11$s AS time_improved'
            . ' FROM %2$s d INNER JOIN %2$s k ON k.%3$s = d.%3$s AND k.%4$s = d.%4$s WHERE k.%1$s = ? ORDER BY d.%1$s%12$s',
            $c['id'],
            $routeTable,
            $c['env'],
            $c['name'],
            $c['lastAccessedAt'],
            $c['saveAccessRecords'],
            $c['reviewed'],
            $c['reviewedAt'],
            $c['reviewedBy'],
            $c['queriesImproved'],
            $c['timeImproved'],
            $platform instanceof SQLitePlatform ? '' : ' FOR UPDATE',
        ), [$keepId]);

        $boolean      = Type::getType(Types::BOOLEAN);
        $duplicateIds = [];
        $latest       = null;
        $saveAll      = true;
        $lastReview   = null;
        foreach ($rows as $row) {
            $rowId = (int) $row['id'];
            if ($rowId !== $keepId) {
                $duplicateIds[] = $rowId;
            }
            if ($row['last_accessed_at'] !== null && ($latest === null || (string) $row['last_accessed_at'] > $latest)) {
                $latest = (string) $row['last_accessed_at'];
            }
            // "Do not save access records" on any row of the route wins.
            $saveAll = $saveAll && $boolean->convertToPHPValue($row['save_access_records'], $platform) !== false;
            // The review of the most recently reviewed row (reviewed_at, then id) wins.
            if ($boolean->convertToPHPValue($row['reviewed'], $platform) === true
                && ($lastReview === null || $this->reviewedLater($row, $lastReview))) {
                $lastReview = $row;
            }
        }

        if ($recordsTable !== null) {
            $connection->executeStatement(
                sprintf('UPDATE %s SET %s = ? WHERE %s IN (?)', $recordsTable, $joinColumn, $joinColumn),
                [$keepId, $duplicateIds],
                [ParameterType::INTEGER, ArrayParameterType::INTEGER],
            );
        }

        $set   = [$c['lastAccessedAt'] => $latest, $c['saveAccessRecords'] => $saveAll];
        $types = [$c['saveAccessRecords'] => Types::BOOLEAN];
        if ($lastReview !== null) {
            $set += [
                $c['reviewed']        => true,
                $c['reviewedAt']      => $lastReview['reviewed_at'],
                $c['reviewedBy']      => $lastReview['reviewed_by'],
                $c['queriesImproved'] => $boolean->convertToPHPValue($lastReview['queries_improved'], $platform),
                $c['timeImproved']    => $boolean->convertToPHPValue($lastReview['time_improved'], $platform),
            ];
            $types += [$c['reviewed'] => Types::BOOLEAN, $c['queriesImproved'] => Types::BOOLEAN, $c['timeImproved'] => Types::BOOLEAN];
        }
        $connection->update($routeTable, $set, [$c['id'] => $keepId], $types);

        $connection->executeStatement(
            sprintf('DELETE FROM %s WHERE %s IN (?)', $routeTable, $c['id']),
            [$duplicateIds],
            [ArrayParameterType::INTEGER],
        );

        return count($duplicateIds);
    }

    /**
     * Whether $row was reviewed after $other (a known review time beats an unknown one; then the higher id).
     *
     * @param array<string, mixed> $row
     * @param array<string, mixed> $other
     */
    private function reviewedLater(array $row, array $other): bool
    {
        return [$row['reviewed_at'] !== null, (string) $row['reviewed_at'], (int) $row['id']]
            > [$other['reviewed_at'] !== null, (string) $other['reviewed_at'], (int) $other['id']];
    }
}
