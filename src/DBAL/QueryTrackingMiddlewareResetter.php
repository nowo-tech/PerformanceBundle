<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\DBAL;

use Symfony\Contracts\Service\ResetInterface;

/**
 * Clears query-tracking counters between requests (FrankenPHP worker / long-lived FPM).
 */
final class QueryTrackingMiddlewareResetter implements ResetInterface
{
    public function __construct(
        private readonly QueryTrackingCounters $counters,
    ) {
    }

    public function reset(): void
    {
        $this->counters->reset();
    }
}
