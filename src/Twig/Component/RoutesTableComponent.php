<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Twig\Component;

use Nowo\PerformanceBundle\Entity\RouteData;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Routes table component for displaying route performance data.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[AsTwigComponent('nowo_performance.RoutesTable', template: '@NowoPerformanceBundle/components/RoutesTable.html.twig')]
final class RoutesTableComponent
{
    /**
     * Array of RouteData entities.
     *
     * @var RouteData[]
     */
    public array $routes = [];

    /**
     * Template framework (bootstrap or tailwind).
     */
    public string $template = 'bootstrap';

    /**
     * Performance thresholds for warning and critical levels.
     *
     * @var array<string, array<string, float|int>>
     */
    public array $thresholds = [];

    /**
     * Enable record management (delete individual records).
     */
    public bool $enableRecordManagement = false;

    /**
     * Enable review system.
     */
    public bool $enableReviewSystem = false;

    /**
     * Review forms indexed by route ID.
     *
     * @var array<int, \Symfony\Component\Form\FormView>
     */
    public array $reviewForms = [];

    /**
     * Delete-record forms indexed by route ID (FormType with CSRF only).
     *
     * @var array<int, \Symfony\Component\Form\FormView>
     */
    public array $deleteForms = [];
}
