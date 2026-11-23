<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Service;

use DateTimeImmutable;

/**
 * Writes a routes_data row by id through the bundle's own private entity manager (since 4.0; REQ-ARCH-001 rules 1, 3
 * and 6): the application's entity manager is never flushed, so changes it holds stay pending for it to flush or drop.
 * Replaces RouteDataRepository::deleteById() / markAsReviewed(), which flush the manager that maps RouteData.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
interface RouteDataWriterInterface
{
    /**
     * Deletes the route row (and, by the mapping, its access records).
     *
     * @return bool True if deleted, false if no row has this id
     */
    public function delete(int $id): bool;

    /**
     * Marks the route row as reviewed; $saveAccessRecords (when not null) also sets its per-route access-record switch
     * in the same flush.
     *
     * @param DateTimeImmutable|null $reviewedAt Review time (default: system time)
     *
     * @return bool True if updated, false if no row has this id
     */
    public function markAsReviewed(int $id, ?bool $queriesImproved = null, ?bool $timeImproved = null, ?string $reviewedBy = null, ?DateTimeImmutable $reviewedAt = null, ?bool $saveAccessRecords = null): bool;
}
