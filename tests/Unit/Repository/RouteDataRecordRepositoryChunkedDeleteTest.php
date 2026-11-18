<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManager;
use Nowo\PerformanceBundle\Entity\RouteDataRecord;
use Nowo\PerformanceBundle\Repository\RouteDataRecordRepository;
use Nowo\PerformanceBundle\Tests\Support\OrmTestSupport;
use Nowo\TransactionDoctrine\WriteUnit\ConcurrencyConflict;
use PHPUnit\Framework\TestCase;

/**
 * Chunked deletes commit batch by batch; a failure that is not a lock conflict is rethrown unchanged, never marked as
 * a partially committed conflict (SQLite; the lock-conflict cases run on real databases in tests/Integration/Concurrency).
 */
final class RouteDataRecordRepositoryChunkedDeleteTest extends TestCase
{
    private EntityManager $em;
    private RouteDataRecordRepository $repository;

    protected function setUp(): void
    {
        $this->em   = OrmTestSupport::entityManager($this);
        $repository = $this->em->getRepository(RouteDataRecord::class);
        self::assertInstanceOf(RouteDataRecordRepository::class, $repository);
        $this->repository = $repository;

        $connection = $this->em->getConnection();
        $connection->insert('routes_data', ['name' => 'home', 'env' => 'prod', 'reviewed' => 0, 'save_access_records' => 1]);
        $routeId = (int) $connection->lastInsertId();
        foreach ([1, 2, 3] as $i) {
            $connection->insert('routes_data_records', ['route_data_id' => $routeId, 'accessed_at' => '2026-01-01 00:00:0' . $i]);
        }
    }

    public function testDeletesInBatches(): void
    {
        self::assertSame(3, $this->repository->deleteOlderThan(new DateTimeImmutable('2026-06-01'), null, 2));
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM routes_data_records'));
    }

    public function testAFailureThatIsNotALockConflictIsRethrownUnchangedAfterACommittedBatch(): void
    {
        $this->em->getConnection()->executeStatement(
            "CREATE TRIGGER refuse_third BEFORE DELETE ON routes_data_records WHEN OLD.accessed_at = '2026-01-01 00:00:03' BEGIN SELECT RAISE(ABORT, 'refused'); END",
        );

        try {
            $this->repository->deleteOlderThan(new DateTimeImmutable('2026-06-01'), null, 2);
            self::fail('The failure was expected.');
        } catch (DbalException $e) {
            self::assertNotInstanceOf(ConcurrencyConflict::class, $e);
        }

        self::assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM routes_data_records'), 'The first batch was committed.');
    }
}
