<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Twig\Component;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Charts component for displaying performance trends.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[AsTwigComponent('nowo_performance.Charts', template: '@NowoPerformanceBundle/components/Charts.html.twig')]
final class ChartsComponent
{
    /**
     * Current environment.
     */
    public string $environment = 'dev';

    /**
     * Current route name (optional filter).
     */
    public ?string $currentRoute = null;

    /**
     * Chart data API URL.
     */
    public string $chartDataUrl;

    /**
     * Template framework (bootstrap or tailwind).
     */
    public string $template = 'bootstrap';
}
