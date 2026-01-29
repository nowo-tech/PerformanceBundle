<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Form;

use Nowo\PerformanceBundle\Entity\RouteData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form type for reviewing route performance records (create and edit).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class ReviewRouteDataType extends AbstractType
{
    /**
     * Build the form.
     *
     * @param FormBuilderInterface $builder The form builder
     * @param array<string, mixed> $options The form options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $routeData = $options['route_data'] ?? null;
        $queriesData = '';
        $timeData = '';
        if ($routeData instanceof RouteData) {
            $q = $routeData->getQueriesImproved();
            $queriesData = true === $q ? '1' : (false === $q ? '0' : '');
            $t = $routeData->getTimeImproved();
            $timeData = true === $t ? '1' : (false === $t ? '0' : '');
        }

        $submitLabel = ($routeData instanceof RouteData && $routeData->isReviewed())
            ? 'review.edit_review'
            : 'review.mark_as_reviewed';

        $builder
            ->add('queries_improved', ChoiceType::class, [
                'label' => 'review.queries_improved',
                'translation_domain' => 'nowo_performance',
                'choices' => [
                    'review.not_specified' => '',
                    'review.yes' => '1',
                    'review.no' => '0',
                ],
                'choice_translation_domain' => 'nowo_performance',
                'required' => false,
                'placeholder' => false,
                'data' => $queriesData,
                'attr' => [
                    'class' => 'form-select',
                ],
            ])
            ->add('time_improved', ChoiceType::class, [
                'label' => 'review.time_improved',
                'translation_domain' => 'nowo_performance',
                'choices' => [
                    'review.not_specified' => '',
                    'review.yes' => '1',
                    'review.no' => '0',
                ],
                'choice_translation_domain' => 'nowo_performance',
                'required' => false,
                'placeholder' => false,
                'data' => $timeData,
                'attr' => [
                    'class' => 'form-select',
                ],
            ]);

        if ($options['enable_access_records'] ?? false) {
            $saveAccessRecordsData = ($routeData instanceof RouteData) ? $routeData->getSaveAccessRecords() : true;
            $builder->add('save_access_records', CheckboxType::class, [
                'label' => 'review.save_access_records',
                'translation_domain' => 'nowo_performance',
                'required' => false,
                'data' => $saveAccessRecordsData,
                'attr' => [
                    'class' => 'form-check-input',
                ],
            ]);
        }

        $builder->add('submit', SubmitType::class, [
                'label' => $submitLabel,
                'translation_domain' => 'nowo_performance',
                'attr' => [
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
            'method' => 'POST',
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'review_performance_record',
            'route_data' => null,
            'enable_access_records' => false,
        ]);
        $resolver->setAllowedTypes('route_data', [RouteData::class, 'null']);
        $resolver->setAllowedTypes('enable_access_records', 'bool');
    }

    /**
     * Get the form block prefix.
     */
    public function getBlockPrefix(): string
    {
        return 'review_route_data';
    }
}
