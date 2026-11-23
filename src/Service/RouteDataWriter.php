<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Service;

use Closure;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\PerformanceBundle\Entity\RouteData;

/**
 * Default {@see RouteDataWriterInterface}: every call opens a fresh private entity manager on the connection, mapping
 * and listeners of the `connection` manager ({@see MetricsEntityManagerFactory}, as metrics recording does), writes and
 * flushes there, and closes it. Nothing is kept between calls (FrankenPHP worker mode, kernel reset off); the
 * application's entity manager is never flushed, cleared or detached.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class RouteDataWriter implements RouteDataWriterInterface
{
    public function __construct(
        private PerformanceMetricsService $metricsService,
        private ?MetricsEntityManagerFactory $entityManagerFactory = null,
    ) {
    }

    public function delete(int $id): bool
    {
        return $this->write(static function (EntityManagerInterface $entityManager) use ($id): bool {
            $routeData = $entityManager->find(RouteData::class, $id);
            if (!$routeData instanceof RouteData) {
                return false;
            }

            $entityManager->remove($routeData);
            $entityManager->flush();

            return true;
        });
    }

    public function markAsReviewed(int $id, ?bool $queriesImproved = null, ?bool $timeImproved = null, ?string $reviewedBy = null, ?DateTimeImmutable $reviewedAt = null, ?bool $saveAccessRecords = null): bool
    {
        return $this->write(static function (EntityManagerInterface $entityManager) use ($id, $queriesImproved, $timeImproved, $reviewedBy, $reviewedAt, $saveAccessRecords): bool {
            $routeData = $entityManager->find(RouteData::class, $id);
            if (!$routeData instanceof RouteData) {
                return false;
            }

            $routeData->markAsReviewed($queriesImproved, $timeImproved, $reviewedBy, $reviewedAt);
            if ($saveAccessRecords !== null) {
                $routeData->setSaveAccessRecords($saveAccessRecords);
            }
            $entityManager->flush();

            return true;
        });
    }

    /**
     * Runs $unit on a fresh private entity manager and closes it afterwards, also on failure.
     *
     * @param Closure(EntityManagerInterface): bool $unit
     */
    private function write(Closure $unit): bool
    {
        $entityManager = ($this->entityManagerFactory ?? new MetricsEntityManagerFactory())->create($this->metricsService->getEntityManager());

        try {
            return $unit($entityManager);
        } finally {
            $entityManager->close();
        }
    }
}
