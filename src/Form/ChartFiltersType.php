<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Client-side filters of the "Performance Trends" chart (REQ-TWIG-005).
 *
 * Never submitted: the chart script reads the selects by id (`chart-metric`, `chart-days`)
 * and fetches the chart data API. Both fields are unmapped; ids are kept for the script.
 *
 * @deprecated since 3.15.0, removed in 4.0: the dashboard renders a server-side trend chart (no chart script); only
 *             the deprecated nowo_performance.Charts component still builds it
 *
 * @extends AbstractType<mixed>
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class ChartFiltersType extends AbstractType
{
    /** HTML ids the chart script relies on. */
    public const FIELD_IDS = [
        'metric' => 'chart-metric',
        'days'   => 'chart-days',
    ];

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('metric', ChoiceType::class, [
                'mapped'  => false,
                'label'   => 'Metric:',
                'choices' => [
                    'Request Time'  => 'requestTime',
                    'Query Time'    => 'queryTime',
                    'Total Queries' => 'totalQueries',
                    'Memory Usage'  => 'memoryUsage',
                ],
                'data'                      => 'requestTime',
                'choice_translation_domain' => false,
            ])
            ->add('days', ChoiceType::class, [
                'mapped'  => false,
                'label'   => 'Days:',
                'choices' => [
                    'Last 7 days'  => '7',
                    'Last 14 days' => '14',
                    'Last 30 days' => '30',
                ],
                'data'                      => '7',
                'choice_translation_domain' => false,
            ]);
    }

    /**
     * @param FormView<mixed> $view
     * @param FormInterface<mixed> $form
     * @param array<string, mixed> $options
     */
    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        foreach (self::FIELD_IDS as $name => $id) {
            $view->children[$name]->vars['id'] = $id;
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method'             => 'GET',
            'csrf_protection'    => false,
            'translation_domain' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
