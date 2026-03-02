<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Form;

use DateTimeImmutable;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form type for performance dashboard filters.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class PerformanceFiltersType extends AbstractType
{
    /**
     * Build the form.
     *
     * @param FormBuilderInterface $builder The form builder
     * @param array<string, mixed> $options The form options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $environments  = $options['environments'] ?? ['dev', 'test', 'prod'];
        $currentEnv    = $options['current_env'] ?? null;
        $currentRoute  = $options['current_route'] ?? null;
        $currentSortBy = $options['current_sort_by'] ?? 'requestTime';
        $currentOrder  = $options['current_order'] ?? 'DESC';
        $currentLimit  = $options['current_limit'] ?? 100;

        $builder
            ->add('env', ChoiceType::class, [
                'label'              => 'filters.environment',
                'translation_domain' => 'nowo_performance',
                'choices'            => array_combine(
                    array_map('strtoupper', $environments),
                    $environments,
                ),
                'choice_translation_domain' => false,
                'data'                      => $currentEnv,
                'required'                  => false,
                'placeholder'               => false,
                'attr'                      => [
                    'class' => 'form-select',
                ],
            ])
            ->add('route', TextType::class, [
                'label'              => 'filters.route_name',
                'translation_domain' => 'nowo_performance',
                'required'           => false,
                'data'               => $currentRoute,
                'attr'               => [
                    'class' => 'form-control',
                ],
            ])
            ->add('sort', ChoiceType::class, [
                'label'              => 'filters.sort_by',
                'translation_domain' => 'nowo_performance',
                'choices'            => [
                    'sort_options.request_time'     => 'requestTime',
                    'sort_options.query_time'       => 'queryTime',
                    'sort_options.queries'          => 'totalQueries',
                    'sort_options.access_count'     => 'accessCount',
                    'sort_options.route_name'       => 'name',
                    'sort_options.created_at'       => 'createdAt',
                    'sort_options.last_accessed_at' => 'lastAccessedAt',
                ],
                'choice_translation_domain' => 'nowo_performance',
                'data'                      => $currentSortBy,
                'required'                  => false,
                'attr'                      => [
                    'class' => 'form-select',
                ],
            ])
            ->add('order', ChoiceType::class, [
                'label'              => 'filters.order',
                'translation_domain' => 'nowo_performance',
                'choices'            => [
                    'order_options.descending' => 'DESC',
                    'order_options.ascending'  => 'ASC',
                ],
                'choice_translation_domain' => 'nowo_performance',
                'data'                      => $currentOrder,
                'required'                  => false,
                'attr'                      => [
                    'class' => 'form-select',
                ],
            ])
            ->add('limit', IntegerType::class, [
                'label'              => 'filters.limit',
                'translation_domain' => 'nowo_performance',
                'required'           => false,
                'data'               => $currentLimit,
                'attr'               => [
                    'class' => 'form-control',
                    'min'   => 1,
                    'max'   => 1000,
                ],
            ])
            ->add('min_request_time', NumberType::class, [
                'label'              => 'filters.min_request_time',
                'translation_domain' => 'nowo_performance',
                'required'           => false,
                'data'               => $options['current_min_request_time'] ?? null,
                'scale'              => 4,
                'attr'               => [
                    'class' => 'form-control',
                    'step'  => '0.0001',
                ],
            ])
            ->add('max_request_time', NumberType::class, [
                'label'              => 'filters.max_request_time',
                'translation_domain' => 'nowo_performance',
                'required'           => false,
                'data'               => $options['current_max_request_time'] ?? null,
                'scale'              => 4,
                'attr'               => [
                    'class' => 'form-control',
                    'step'  => '0.0001',
                ],
            ])
            ->add('min_query_count', IntegerType::class, [
                'label'              => 'filters.min_query_count',
                'translation_domain' => 'nowo_performance',
                'required'           => false,
                'data'               => $options['current_min_query_count'] ?? null,
                'attr'               => [
                    'class' => 'form-control',
                    'min'   => 0,
                ],
            ])
            ->add('max_query_count', IntegerType::class, [
                'label'              => 'filters.max_query_count',
                'translation_domain' => 'nowo_performance',
                'required'           => false,
                'data'               => $options['current_max_query_count'] ?? null,
                'attr'               => [
                    'class' => 'form-control',
                    'min'   => 0,
                ],
            ])
            ->add('date_from', DateType::class, [
                'label'              => 'filters.date_from',
                'translation_domain' => 'nowo_performance',
                'required'           => false,
                'widget'             => 'single_text',
                'data'               => $options['current_date_from'] ?? null,
                'attr'               => [
                    'class' => 'form-control',
                ],
            ])
            ->add('date_to', DateType::class, [
                'label'              => 'filters.date_to',
                'translation_domain' => 'nowo_performance',
                'required'           => false,
                'widget'             => 'single_text',
                'data'               => $options['current_date_to'] ?? null,
                'attr'               => [
                    'class' => 'form-control',
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label'              => 'filters.apply_filters',
                'translation_domain' => 'nowo_performance',
                'attr'               => [
                    'class' => 'btn btn-primary',
                ],
            ]);
    }

    /**
     * Configure form options.
     *
     * @param OptionsResolver $resolver The options resolver
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method'                   => 'GET',
            'csrf_protection'          => false,
            'environments'             => ['dev', 'test', 'prod'],
            'current_env'              => null,
            'current_route'            => null,
            'current_sort_by'          => 'requestTime',
            'current_order'            => 'DESC',
            'current_limit'            => 100,
            'current_min_request_time' => null,
            'current_max_request_time' => null,
            'current_min_query_count'  => null,
            'current_max_query_count'  => null,
            'current_date_from'        => null,
            'current_date_to'          => null,
        ]);

        $resolver->setAllowedTypes('environments', 'array');
        $resolver->setAllowedTypes('current_env', ['string', 'null']);
        $resolver->setAllowedTypes('current_route', ['string', 'null']);
        $resolver->setAllowedTypes('current_sort_by', 'string');
        $resolver->setAllowedTypes('current_order', 'string');
        $resolver->setAllowedTypes('current_limit', 'int');
        $resolver->setAllowedTypes('current_min_request_time', ['float', 'int', 'null']);
        $resolver->setAllowedTypes('current_max_request_time', ['float', 'int', 'null']);
        $resolver->setAllowedTypes('current_min_query_count', ['int', 'null']);
        $resolver->setAllowedTypes('current_max_query_count', ['int', 'null']);
        $resolver->setAllowedTypes('current_date_from', [DateTimeImmutable::class, 'null']);
        $resolver->setAllowedTypes('current_date_to', [DateTimeImmutable::class, 'null']);
    }

    /**
     * Get the form block prefix.
     */
    public function getBlockPrefix(): string
    {
        return '';
    }
}
