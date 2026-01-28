<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Twig\Component;

use Symfony\Component\Form\FormView;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Filters component for the performance dashboard.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[AsTwigComponent('nowo_performance.Filters', template: '@NowoPerformanceBundle/components/Filters.html.twig')]
final class FiltersComponent
{
    /**
     * Form view for filters.
     */
    public FormView $form;

    /**
     * Template framework (bootstrap or tailwind).
     */
    public string $template = 'bootstrap';
}
