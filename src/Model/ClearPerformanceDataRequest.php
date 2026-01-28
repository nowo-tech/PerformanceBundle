<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Model;

/**
 * DTO for clear performance data form (POST).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @property string|null $env Environment whose records will be cleared (e.g. dev, prod).
 */
final class ClearPerformanceDataRequest
{
    /**
     * @param string|null $env Environment whose records will be cleared (e.g. dev, prod).
     */
    public function __construct(
        public ?string $env = null,
    ) {
    }
}
