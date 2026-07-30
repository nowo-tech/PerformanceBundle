<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Twig globals for the performance dashboard UI.
 *
 * Global {@see self::GLOBAL_LAYOUT_TEMPLATE}: layout extended by
 * {@code Performance/base.html.twig} (from
 * {@code nowo_performance.dashboard.layout_template}).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class PerformanceLayoutExtension extends AbstractExtension implements GlobalsInterface
{
    public const GLOBAL_LAYOUT_TEMPLATE = 'nowo_performance_layout_template';

    public function __construct(
        private readonly string $layoutTemplate,
    ) {
    }

    public function getGlobals(): array
    {
        return [
            self::GLOBAL_LAYOUT_TEMPLATE => $this->layoutTemplate,
        ];
    }
}
