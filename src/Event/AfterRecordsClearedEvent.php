<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched after all performance records are cleared.
 *
 * This event allows listeners to perform actions after clearing.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
final class AfterRecordsClearedEvent extends Event
{
    /**
     * Constructor.
     *
     * @param int $deletedCount Number of records deleted
     * @param string|null $env Optional environment filter (null = all environments)
     */
    public function __construct(
        private readonly int $deletedCount,
        private readonly ?string $env = null
    ) {
    }

    public function getDeletedCount(): int
    {
        return $this->deletedCount;
    }

    public function getEnv(): ?string
    {
        return $this->env;
    }
}
