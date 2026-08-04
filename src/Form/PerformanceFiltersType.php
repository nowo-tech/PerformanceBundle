<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Form;

use DateTimeImmutable;
use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form type for performance dashboard filters.
 *
 * @extends AbstractType<mixed>
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[FormKitConfig('performance')]
class PerformanceFiltersType extends AbstractType
{
    use FormOptionsTrait;

    /**
     * Build the form.
     *
     * @param FormBuilderInterface<mixed> $builder The form builder
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

        $this->addChoice($builder, 'env', [
            'label'              => 'filters.environment',
            'translation_domain' => 'NowoPerformanceBundle',
            'choices'            => array_combine(
                array_map(strtoupper(...), $environments),
                $environments,
            ),
            'choice_translation_domain' => false,
            'data'                      => $currentEnv,
            'required'                  => false,
            'placeholder'               => false,
            'attr'                      => [
                'class' => 'form-select',
            ],
        ]);
        $this->addText($builder, 'route', [
            'label'              => 'filters.route_name',
            'translation_domain' => 'NowoPerformanceBundle',
            'required'           => false,
            'data'               => $currentRoute,
            'attr'               => [
                'class' => 'form-control',
            ],
        ]);
        $this->addText($builder, 'path', [
            'label'              => 'filters.path_url',
            'translation_domain' => 'NowoPerformanceBundle',
            'required'           => false,
            'data'               => $options['current_path'] ?? null,
            'attr'               => [
                'class'       => 'form-control',
                'placeholder' => '/path',
            ],
        ]);
        $this->addChoice($builder, 'sort', [
            'label'              => 'filters.sort_by',
            'translation_domain' => 'NowoPerformanceBundle',
            'choices'            => [
                'sort_options.request_time'     => 'requestTime',
                'sort_options.query_time'       => 'queryTime',
                'sort_options.queries'          => 'totalQueries',
                'sort_options.access_count'     => 'accessCount',
                'sort_options.route_name'       => 'name',
                'sort_options.created_at'       => 'createdAt',
                'sort_options.last_accessed_at' => 'lastAccessedAt',
            ],
            'choice_translation_domain' => 'NowoPerformanceBundle',
            'data'                      => $currentSortBy,
            'required'                  => false,
            'attr'                      => [
                'class' => 'form-select',
            ],
        ]);
        $this->addChoice($builder, 'order', [
            'label'              => 'filters.order',
            'translation_domain' => 'NowoPerformanceBundle',
            'choices'            => [
                'order_options.descending' => 'DESC',
                'order_options.ascending'  => 'ASC',
            ],
            'choice_translation_domain' => 'NowoPerformanceBundle',
            'data'                      => $currentOrder,
            'required'                  => false,
            'attr'                      => [
                'class' => 'form-select',
            ],
        ]);
        $this->addInteger($builder, 'limit', [
            'label'              => 'filters.limit',
            'translation_domain' => 'NowoPerformanceBundle',
            'required'           => false,
            'data'               => $currentLimit,
            'attr'               => [
                'class' => 'form-control',
                'min'   => 1,
                'max'   => 1000,
            ],
        ]);
        $this->addNumber($builder, 'min_request_time', [
            'label'              => 'filters.min_request_time',
            'translation_domain' => 'NowoPerformanceBundle',
            'required'           => false,
            'data'               => $options['current_min_request_time'] ?? null,
            'scale'              => 4,
            'attr'               => [
                'class' => 'form-control',
                'step'  => '0.0001',
            ],
        ]);
        $this->addNumber($builder, 'max_request_time', [
            'label'              => 'filters.max_request_time',
            'translation_domain' => 'NowoPerformanceBundle',
            'required'           => false,
            'data'               => $options['current_max_request_time'] ?? null,
            'scale'              => 4,
            'attr'               => [
                'class' => 'form-control',
                'step'  => '0.0001',
            ],
        ]);
        $this->addInteger($builder, 'min_query_count', [
            'label'              => 'filters.min_query_count',
            'translation_domain' => 'NowoPerformanceBundle',
            'required'           => false,
            'data'               => $options['current_min_query_count'] ?? null,
            'attr'               => [
                'class' => 'form-control',
                'min'   => 0,
            ],
        ]);
        $this->addInteger($builder, 'max_query_count', [
            'label'              => 'filters.max_query_count',
            'translation_domain' => 'NowoPerformanceBundle',
            'required'           => false,
            'data'               => $options['current_max_query_count'] ?? null,
            'attr'               => [
                'class' => 'form-control',
                'min'   => 0,
            ],
        ]);
        $this->addWithDefaults($builder, 'date_from', DateType::class, [
            'label'              => 'filters.date_from',
            'translation_domain' => 'NowoPerformanceBundle',
            'required'           => false,
            'widget'             => 'single_text',
            'data'               => $options['current_date_from'] ?? null,
            'attr'               => [
                'class' => 'form-control',
            ],
        ]);
        $this->addWithDefaults($builder, 'date_to', DateType::class, [
            'label'              => 'filters.date_to',
            'translation_domain' => 'NowoPerformanceBundle',
            'required'           => false,
            'widget'             => 'single_text',
            'data'               => $options['current_date_to'] ?? null,
            'attr'               => [
                'class' => 'form-control',
            ],
        ]);
        $this->addWithDefaults($builder, 'submit', SubmitType::class, [
            'label'              => 'filters.apply_filters',
            'translation_domain' => 'NowoPerformanceBundle',
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
            'current_path'             => null,
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
        $resolver->setAllowedTypes('current_path', ['string', 'null']);
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
