<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Support;

use Nowo\PerformanceBundle\Service\PerformanceMetricsService;

/**
 * Records the replay pauses of the metrics write unit instead of sleeping.
 */
final class PausingMetricsService extends PerformanceMetricsService
{
    /** @var list<int> */
    public array $pauses = [];

    protected function pauseBeforeReplay(int $attempt): void
    {
        $this->pauses[] = $attempt;
    }
}
