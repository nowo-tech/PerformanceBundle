<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Nowo\PerformanceBundle\Entity\RouteData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form type for reviewing route performance records (create and edit).
 *
 * @extends AbstractType<mixed>
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[FormKitConfig('performance')]
class ReviewRouteDataType extends AbstractType
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
        $routeData   = $options['route_data'] ?? null;
        $queriesData = '';
        $timeData    = '';
        if ($routeData instanceof RouteData) {
            $q           = $routeData->getQueriesImproved();
            $queriesData = $q === true ? '1' : ($q === false ? '0' : '');
            $t           = $routeData->getTimeImproved();
            $timeData    = $t === true ? '1' : ($t === false ? '0' : '');
        }

        $submitLabel = ($routeData instanceof RouteData && $routeData->isReviewed())
            ? 'review.edit_review'
            : 'review.mark_as_reviewed';

        $this->addChoice($builder, 'queries_improved', [
            'label'              => 'review.queries_improved',
            'translation_domain' => 'NowoPerformanceBundle',
            'choices'            => [
                'review.not_specified' => '',
                'review.yes'           => '1',
                'review.no'            => '0',
            ],
            'choice_translation_domain' => 'NowoPerformanceBundle',
            'required'                  => false,
            'placeholder'               => false,
            'data'                      => $queriesData,
            'attr'                      => [
                'class' => 'form-select',
            ],
        ]);
        $this->addChoice($builder, 'time_improved', [
            'label'              => 'review.time_improved',
            'translation_domain' => 'NowoPerformanceBundle',
            'choices'            => [
                'review.not_specified' => '',
                'review.yes'           => '1',
                'review.no'            => '0',
            ],
            'choice_translation_domain' => 'NowoPerformanceBundle',
            'required'                  => false,
            'placeholder'               => false,
            'data'                      => $timeData,
            'attr'                      => [
                'class' => 'form-select',
            ],
        ]);

        if ($options['enable_access_records'] ?? false) {
            $saveAccessRecordsData = ($routeData instanceof RouteData) ? $routeData->getSaveAccessRecords() : true;
            $this->addCheckbox($builder, 'save_access_records', [
                'label'              => 'review.save_access_records',
                'translation_domain' => 'NowoPerformanceBundle',
                'required'           => false,
                'data'               => $saveAccessRecordsData,
                'attr'               => [
                    'class' => 'form-check-input',
                ],
            ]);
        }

        $this->addWithDefaults($builder, 'submit', SubmitType::class, [
            'label'              => $submitLabel,
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
            'method'                => 'POST',
            'csrf_protection'       => true,
            'csrf_field_name'       => '_token',
            'csrf_token_id'         => 'review_performance_record',
            'route_data'            => null,
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
