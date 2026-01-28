<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Model;

/**
 * DTO for statistics page environment selector form (GET).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @property string|null $env Selected environment (e.g. dev, prod).
 */
final class StatisticsEnvFilter
{
    /**
     * @param string|null $env Selected environment (e.g. dev, prod).
     */
    public function __construct(
        public ?string $env = null,
    ) {
    }
}
