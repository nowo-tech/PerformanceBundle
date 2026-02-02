<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Twig\Component;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Statistics component for displaying performance statistics.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
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

    /**
     * Whether access records are enabled (shows total records and top rankings).
     */
    public bool $enableAccessRecords = false;

    /**
     * Current environment for links.
     */
    public string $environment = 'dev';
}
