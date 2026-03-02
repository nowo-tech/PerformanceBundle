<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Form;

use Nowo\PerformanceBundle\Model\ClearPerformanceDataRequest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form type for clear performance data action (POST).
 *
 * Contains hidden env field and submit button; CSRF protected.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class ClearPerformanceDataType extends AbstractType
{
    /**
     * Builds the form with hidden env and submit button.
     *
     * @param FormBuilderInterface $builder The form builder
     * @param array<string, mixed> $options Form options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('env', HiddenType::class)
            ->add('submit', SubmitType::class, [
                'label'              => 'dashboard.clear_all_records',
                'translation_domain' => 'nowo_performance',
                'attr'               => ['class' => 'btn btn-danger'],
            ]);
    }

    /**
     * Configures options (data_class, POST, CSRF).
     *
     * @param OptionsResolver $resolver The options resolver
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'      => ClearPerformanceDataRequest::class,
            'method'          => 'POST',
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id'   => 'clear_performance_data',
        ]);
    }

    /**
     * Returns block prefix for form field names and CSRF token.
     *
     * @return string Block prefix
     */
    public function getBlockPrefix(): string
    {
        return 'clear_performance_data';
    }
}
