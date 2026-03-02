<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Form;

use Nowo\PerformanceBundle\Model\PurgeAccessRecordsRequest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form type for purge access records (POST).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class PurgeAccessRecordsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $environments = $options['environments'] ?? ['dev', 'test', 'prod'];
        $environments = array_values(array_unique($environments));
        sort($environments);
        $choicesEnv = ['access_statistics.all_environments' => ''] + array_combine(
            array_map('strtoupper', $environments),
            $environments,
        );

        $builder
            ->add('purgeType', ChoiceType::class, [
                'label'              => 'access_statistics.purge_type',
                'translation_domain' => 'nowo_performance',
                'choices'            => [
                    'access_statistics.purge_all'        => PurgeAccessRecordsRequest::PURGE_ALL,
                    'access_statistics.purge_older_than' => PurgeAccessRecordsRequest::PURGE_OLDER_THAN,
                ],
                'choice_translation_domain' => 'nowo_performance',
                'attr'                      => ['class' => 'form-select purge-type-select'],
            ])
            ->add('days', IntegerType::class, [
                'label'              => 'access_statistics.older_than_days',
                'translation_domain' => 'nowo_performance',
                'required'           => false,
                'data'               => $options['default_days'] ?? 30,
                'attr'               => ['class' => 'form-control', 'min' => 1, 'placeholder' => '30'],
            ])
            ->add('env', ChoiceType::class, [
                'label'                     => 'access_statistics.environment',
                'translation_domain'        => 'nowo_performance',
                'choices'                   => $choicesEnv,
                'choice_translation_domain' => 'nowo_performance',
                'required'                  => false,
                'attr'                      => ['class' => 'form-select'],
            ])
            ->add('submit', SubmitType::class, [
                'label'              => 'access_statistics.purge_records',
                'translation_domain' => 'nowo_performance',
                'attr'               => ['class' => 'btn btn-warning'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'      => PurgeAccessRecordsRequest::class,
            'method'          => 'POST',
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id'   => 'purge_access_records',
            'environments'    => ['dev', 'test', 'prod'],
            'default_days'    => 30,
        ]);
        $resolver->setAllowedTypes('environments', 'array');
    }

    public function getBlockPrefix(): string
    {
        return 'purge_access_records';
    }
}
