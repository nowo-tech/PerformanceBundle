<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form type for delete single record action (POST, CSRF only).
 *
 * Contains only a submit button; used per-row in the routes table.
 *
 * @extends AbstractType<mixed>
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[FormKitConfig('performance')]
class DeleteRecordType extends AbstractType
{
    use FormOptionsTrait;

    /**
     * Builds a CSRF-only form (no fields).
     *
     * @param FormBuilderInterface<mixed> $builder The form builder
     * @param array<string, mixed> $options Options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // CSRF token only: the page renders the button (UiKit delete Action with a confirm, REQ-UI-003).
    }

    /**
     * Configures options (method POST, CSRF; the submit_attr_class option was removed in 4.0).
     *
     * @param OptionsResolver $resolver The options resolver
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method'          => 'POST',
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id'   => 'delete_performance_record',
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
