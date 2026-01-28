<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched after a performance record is deleted.
 *
 * This event allows listeners to perform actions after deletion.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class AfterRecordDeletedEvent extends Event
{
    /**
     * Creates a new instance.
     *
     * @param int    $recordId  The ID of the deleted record
     * @param string $routeName The route name of the deleted record
     * @param string $env       The environment of the deleted record
     */
    public function __construct(
        private readonly int $recordId,
        private readonly string $routeName,
        private readonly string $env,
    ) {
    }

    public function getRecordId(): int
    {
        return $this->recordId;
    }

    public function getRouteName(): string
    {
        return $this->routeName;
    }

    public function getEnv(): string
    {
        return $this->env;
    }
}
