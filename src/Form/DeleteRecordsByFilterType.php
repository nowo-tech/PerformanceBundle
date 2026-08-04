<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Form;

use Nowo\PerformanceBundle\Model\DeleteRecordsByFilterRequest;
use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
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
 * @extends AbstractType<mixed>
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[FormKitConfig('performance')]
class DeleteRecordsByFilterType extends AbstractType
{
    use FormOptionsTrait;

    /**
     * Builds the form with hidden fields for filter state and a submit button.
     *
     * @param FormBuilderInterface<mixed> $builder The form builder
     * @param array<string, mixed> $options Options (from_value: origin page identifier)
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addWithDefaults($builder, '_from', HiddenType::class, [
                'property_path' => 'from',
                'data'          => $options['from_value'],
            ]);
        $this->addWithDefaults($builder, 'env', HiddenType::class, []);
        $this->addWithDefaults($builder, 'start_date', HiddenType::class, ['property_path' => 'startDate']);
        $this->addWithDefaults($builder, 'end_date', HiddenType::class, ['property_path' => 'endDate']);
        $this->addWithDefaults($builder, 'route', HiddenType::class, []);
        $this->addWithDefaults($builder, 'path', HiddenType::class, []);
        $this->addWithDefaults($builder, 'status_code', HiddenType::class, ['property_path' => 'statusCode']);
        $this->addWithDefaults($builder, 'min_query_time', HiddenType::class, ['property_path' => 'minQueryTime']);
        $this->addWithDefaults($builder, 'max_query_time', HiddenType::class, ['property_path' => 'maxQueryTime']);
        $this->addWithDefaults($builder, 'min_memory_usage', HiddenType::class, ['property_path' => 'minMemoryUsage']);
        $this->addWithDefaults($builder, 'max_memory_usage', HiddenType::class, ['property_path' => 'maxMemoryUsage']);
        $this->addWithDefaults($builder, 'referer', HiddenType::class, []);
        $this->addWithDefaults($builder, 'user', HiddenType::class, []);
        $this->addWithDefaults($builder, 'submit', SubmitType::class, [
                'label'              => 'access_statistics.delete_records_matching_filter',
                'translation_domain' => 'NowoPerformanceBundle',
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
