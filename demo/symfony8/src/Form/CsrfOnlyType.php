<?php

declare(strict_types=1);

namespace App\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * CSRF-only POST (demo product delete, REQ-TWIG-005), a FormKit form (REQ-DEMO-015, profile `demo`). Empty block prefix
 * keeps {@code _token} flat.
 *
 * @extends AbstractType<null>
 */
#[FormKitConfig('demo')]
final class CsrfOnlyType extends AbstractType
{
    use FormOptionsTrait;

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_token_id'   => 'demo',
            'csrf_field_name' => '_token',
        ]);
        $resolver->setAllowedTypes('csrf_token_id', 'string');
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
