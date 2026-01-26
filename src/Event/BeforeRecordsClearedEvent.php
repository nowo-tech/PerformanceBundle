<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched before all performance records are cleared.
 *
 * This event allows listeners to prevent clearing or perform actions before clearing.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
final class BeforeRecordsClearedEvent extends Event
{
    private bool $clearingPrevented = false;

    /**
     * Constructor.
     *
     * @param string|null $env Optional environment filter (null = all environments)
     */
    public function __construct(
        private readonly ?string $env = null,
    ) {
    }

    public function getEnv(): ?string
    {
        return $this->env;
    }

    /**
     * Prevent the clearing from happening.
     */
    public function preventClearing(): void
    {
        $this->clearingPrevented = true;
    }

    public function isClearingPrevented(): bool
    {
        return $this->clearingPrevented;
    }
}
