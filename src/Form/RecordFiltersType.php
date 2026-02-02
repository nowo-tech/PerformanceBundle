<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Form;

use Nowo\PerformanceBundle\Model\RecordFilters;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form type for record/access statistics filters (GET).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class RecordFiltersType extends AbstractType
{
    /**
     * Builds the filter form (start/end date, env, route, status code, query time and memory filters).
     *
     * @param FormBuilderInterface $builder The form builder
     * @param array<string, mixed> $options Options (environments, available_routes, all_routes_label, all_status_label)
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $environments = $options['environments'] ?? ['dev', 'test', 'prod'];
        $availableRoutes = $options['available_routes'] ?? [];
        $availableRoutes = array_values(array_unique($availableRoutes));
        sort($availableRoutes);
        $choicesEnv = array_combine(array_map('strtoupper', $environments), $environments);
        $allRoutesLabel = $options['all_routes_label'];
        $allStatusLabel = $options['all_status_label'];
        $choicesRoute = [$allRoutesLabel => ''] + array_combine($availableRoutes, $availableRoutes);
        $choicesStatus = [
            $allStatusLabel => '',
            '200 OK' => '200',
            '404 Not Found' => '404',
            '500 Server Error' => '500',
            '503 Service Unavailable' => '503',
        ];

        $builder
            ->add('start_date', DateTimeType::class, [
                'label' => 'access_statistics.start_date',
                'translation_domain' => 'nowo_performance',
                'widget' => 'single_text',
                // 'html5' => false,
                // 'format' => 'yyyy-MM-dd',
                'required' => false,
                'property_path' => 'startDate',
                'input' => 'datetime_immutable',
                'with_seconds' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('end_date', DateTimeType::class, [
                'label' => 'access_statistics.end_date',
                'translation_domain' => 'nowo_performance',
                'widget' => 'single_text',
                // 'html5' => false,
                // 'format' => 'yyyy-MM-dd',
                'required' => false,
                'property_path' => 'endDate',
                'input' => 'datetime_immutable',
                'with_seconds' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('env', ChoiceType::class, [
                'label' => 'access_statistics.environment',
                'translation_domain' => 'nowo_performance',
                'choices' => $choicesEnv,
                'choice_translation_domain' => false,
                'required' => false,
                'placeholder' => false,
                'attr' => ['class' => 'form-select'],
            ])
            ->add('route', ChoiceType::class, [
                'label' => 'access_statistics.route',
                'translation_domain' => 'nowo_performance',
                'choices' => $choicesRoute,
                'choice_translation_domain' => false,
                'required' => false,
                'placeholder' => false,
                'attr' => ['class' => 'form-select'],
            ])
            ->add('status_code', ChoiceType::class, [
                'label' => 'access_statistics.status_code',
                'translation_domain' => 'nowo_performance',
                'choices' => $choicesStatus,
                'choice_translation_domain' => false,
                'required' => false,
                'placeholder' => false,
                'property_path' => 'statusCode',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('min_query_time', NumberType::class, [
                'label' => 'access_statistics.min_query_time',
                'translation_domain' => 'nowo_performance',
                'required' => false,
                'property_path' => 'minQueryTime',
                'scale' => 3,
                'attr' => ['class' => 'form-control', 'placeholder' => '0.001', 'step' => '0.001'],
            ])
            ->add('max_query_time', NumberType::class, [
                'label' => 'access_statistics.max_query_time',
                'translation_domain' => 'nowo_performance',
                'required' => false,
                'property_path' => 'maxQueryTime',
                'scale' => 3,
                'attr' => ['class' => 'form-control', 'placeholder' => '5', 'step' => '0.001'],
            ])
            ->add('min_memory_mb', NumberType::class, [
                'label' => 'access_statistics.min_memory_mb',
                'translation_domain' => 'nowo_performance',
                'required' => false,
                'mapped' => false,
                'data' => isset($options['data']) && null !== $options['data']->minMemoryUsage
                    ? round($options['data']->minMemoryUsage / 1024 / 1024, 2) : null,
                'attr' => ['class' => 'form-control', 'placeholder' => '0', 'step' => '0.1'],
            ])
            ->add('max_memory_mb', NumberType::class, [
                'label' => 'access_statistics.max_memory_mb',
                'translation_domain' => 'nowo_performance',
                'required' => false,
                'mapped' => false,
                'data' => isset($options['data']) && null !== $options['data']->maxMemoryUsage
                    ? round($options['data']->maxMemoryUsage / 1024 / 1024, 2) : null,
                'attr' => ['class' => 'form-control', 'placeholder' => '100', 'step' => '0.1'],
            ])
            ->add('referer', TextType::class, [
                'label' => 'access_statistics.referer',
                'translation_domain' => 'nowo_performance',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'example.com'],
            ])
            ->add('user', TextType::class, [
                'label' => 'access_statistics.user',
                'translation_domain' => 'nowo_performance',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'username@example.com'],
            ])
        ;
        $builder->get('status_code')->addModelTransformer(new CallbackTransformer(
            static function (?int $value): string {
                return null !== $value ? (string) $value : '';
            },
            static function (mixed $value): ?int {
                if (null === $value || '' === $value) {
                    return null;
                }

                return \is_int($value) ? $value : (int) $value;
            }
        ));
        $builder
            ->add('filter', SubmitType::class, [
                'label' => 'access_statistics.filter',
                'translation_domain' => 'nowo_performance',
                'attr' => ['class' => 'btn btn-primary'],
            ]);
    }

    /**
     * Configures form options (data_class, method GET, translation labels).
     *
     * @param OptionsResolver $resolver The options resolver
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RecordFilters::class,
            'method' => 'GET',
            'csrf_protection' => false,
            'environments' => ['dev', 'test', 'prod'],
            'available_routes' => [],
            'all_routes_label' => 'access_statistics.all_routes',
            'all_status_label' => 'access_statistics.all_status_codes',
        ]);
        $resolver->setAllowedTypes('environments', 'array');
        $resolver->setAllowedTypes('available_routes', 'array');
    }

    /**
     * Returns empty block prefix for GET query parameter names without prefix.
     *
     * @return string Empty string
     */
    public function getBlockPrefix(): string
    {
        return '';
    }
}
