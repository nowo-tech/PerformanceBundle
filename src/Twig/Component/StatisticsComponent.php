<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Twig\Component;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Statistics component for displaying performance statistics.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
#[AsTwigComponent('nowo_performance.Statistics', template: '@NowoPerformanceBundle/components/Statistics.html.twig')]
final class StatisticsComponent
{
    /**
     * Statistics data.
     *
     * @var array<string, mixed>
     */
    public array $stats = [];

    /**
     * Template framework (bootstrap or tailwind).
     */
    public string $template = 'bootstrap';
}
