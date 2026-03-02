<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Form;

use Nowo\PerformanceBundle\Model\DeleteRecordsByFilterRequest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form type for delete-records-by-filter action (POST).
 *
 * Uses hidden fields to carry current filter state (env, dates, route, status code, query time, memory).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class DeleteRecordsByFilterType extends AbstractType
{
    /**
     * Builds the form with hidden fields for filter state and a submit button.
     *
     * @param FormBuilderInterface $builder The form builder
     * @param array<string, mixed> $options Options (from_value: origin page identifier)
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('_from', HiddenType::class, [
                'property_path' => 'from',
                'data'          => $options['from_value'],
            ])
            ->add('env', HiddenType::class)
            ->add('start_date', HiddenType::class, ['property_path' => 'startDate'])
            ->add('end_date', HiddenType::class, ['property_path' => 'endDate'])
            ->add('route', HiddenType::class)
            ->add('status_code', HiddenType::class, ['property_path' => 'statusCode'])
            ->add('min_query_time', HiddenType::class, ['property_path' => 'minQueryTime'])
            ->add('max_query_time', HiddenType::class, ['property_path' => 'maxQueryTime'])
            ->add('min_memory_usage', HiddenType::class, ['property_path' => 'minMemoryUsage'])
            ->add('max_memory_usage', HiddenType::class, ['property_path' => 'maxMemoryUsage'])
            ->add('referer', HiddenType::class)
            ->add('user', HiddenType::class)
            ->add('submit', SubmitType::class, [
                'label'              => 'access_statistics.delete_records_matching_filter',
                'translation_domain' => 'nowo_performance',
                'attr'               => ['class' => 'btn btn-danger'],
            ]);
    }

    /**
     * Configures options (data_class, POST, CSRF, from_value).
     *
     * @param OptionsResolver $resolver The options resolver
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'      => DeleteRecordsByFilterRequest::class,
            'method'          => 'POST',
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id'   => 'delete_records_by_filter',
            'from_value'      => 'access_records',
        ]);
    }

    /**
     * Returns block prefix for form field names and CSRF token.
     *
     * @return string Block prefix
     */
    public function getBlockPrefix(): string
    {
        return 'delete_records_by_filter';
    }
}
