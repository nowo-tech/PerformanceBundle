<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form type for delete single record action (POST, CSRF only).
 *
 * Contains only a submit button; used per-row in the routes table.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class DeleteRecordType extends AbstractType
{
    /**
     * Builds the form with a single submit button.
     *
     * @param FormBuilderInterface $builder The form builder
     * @param array<string, mixed> $options Options (submit_attr_class for button CSS)
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /*
        $builder->add('submit', SubmitType::class, [
            'label' => 'Delete',
            'attr' => ['class' => $options['submit_attr_class'] ?? 'btn btn-danger btn-sm'],
        ]);
        */
    }

    /**
     * Configures options (method POST, CSRF, submit_attr_class).
     *
     * @param OptionsResolver $resolver The options resolver
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method'            => 'POST',
            'csrf_protection'   => true,
            'csrf_field_name'   => '_token',
            'csrf_token_id'     => 'delete_performance_record',
            'submit_attr_class' => 'btn btn-danger btn-sm',
        ]);
        $resolver->setRequired('csrf_token_id');
    }

    /**
     * Returns block prefix for CSRF token and submit button name.
     *
     * @return string Block prefix
     */
    public function getBlockPrefix(): string
    {
        return 'delete_record';
    }
}
