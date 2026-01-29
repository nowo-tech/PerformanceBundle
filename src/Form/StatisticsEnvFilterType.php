<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Form;

use Nowo\PerformanceBundle\Model\StatisticsEnvFilter;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form type for statistics page environment selector (GET).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class StatisticsEnvFilterType extends AbstractType
{
    /**
     * Builds the form with a single environment choice field.
     *
     * @param FormBuilderInterface $builder The form builder
     * @param array<string, mixed> $options Options (environments, attr_class, attr_extra)
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $environments = $options['environments'] ?? ['dev', 'test', 'prod'];
        $choices = array_combine(array_map('strtoupper', $environments), $environments);

        $builder->add('env', ChoiceType::class, [
            'label' => 'Environment',
            'choices' => $choices,
            'choice_translation_domain' => false,
            'required' => true,
            'placeholder' => false,
            'attr' => array_merge(
                ['class' => $options['attr_class'] ?? 'form-select'],
                $options['attr_extra'] ?? []
            ),
        ]);
    }

    /**
     * Configures options (data_class, GET, environments list).
     *
     * @param OptionsResolver $resolver The options resolver
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => StatisticsEnvFilter::class,
            'method' => 'GET',
            'csrf_protection' => false,
            'environments' => ['dev', 'test', 'prod'],
            'attr_class' => 'form-select',
            'attr_extra' => [],
        ]);
        $resolver->setAllowedTypes('environments', 'array');
    }

    /**
     * Returns block prefix for form field names.
     *
     * @return string Block prefix
     */
    public function getBlockPrefix(): string
    {
        return 'statistics_env';
    }
}
