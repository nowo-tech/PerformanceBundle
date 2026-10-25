<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Twig\Component;

use Nowo\PerformanceBundle\Form\ChartFiltersType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormView;
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

    private ?FormView $filtersForm = null;

    public function __construct(
        private readonly ?FormFactoryInterface $formFactory = null,
    ) {
    }

    /**
     * Unmapped metric/days selects read by the chart script (REQ-TWIG-005); `this.filtersForm` in the template.
     */
    public function getFiltersForm(): FormView
    {
        return $this->filtersForm ??= ($this->formFactory ?? Forms::createFormFactory())
            ->createNamed('', ChartFiltersType::class)
            ->createView();
    }
}
