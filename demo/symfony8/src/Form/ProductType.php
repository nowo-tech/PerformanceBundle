<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Product;
use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Demo product form (REQ-DEMO-015): a FormKit form, profile `demo`.
 *
 * @extends AbstractType<Product>
 */
#[FormKitConfig('demo')]
class ProductType extends AbstractType
{
    use FormOptionsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addText($builder, 'name', [
            'label'              => 'Name',
            'required'           => true,
            'translation_domain' => false,
            'placeholder'        => false,
            'help'               => false,
        ]);
        $this->addTextarea($builder, 'description', [
            'label'              => 'Description',
            'required'           => false,
            'translation_domain' => false,
            'placeholder'        => false,
            'help'               => false,
            'attr'               => ['rows' => 4],
        ]);
        $this->addWithDefaults($builder, 'price', MoneyType::class, [
            'label'              => 'Price',
            'required'           => true,
            'currency'           => 'USD',
            'translation_domain' => false,
            'placeholder'        => false,
            'help'               => false,
        ]);
        $this->addUrl($builder, 'imageUrl', [
            'label'              => 'Image URL',
            'required'           => false,
            'translation_domain' => false,
            'placeholder'        => false,
            'help'               => false,
        ]);
        $this->addChoice($builder, 'status', [
            'label'   => 'Status',
            'choices' => [
                'Active'   => 'active',
                'Inactive' => 'inactive',
                'Draft'    => 'draft',
            ],
            'required'           => true,
            'translation_domain' => false,
            'placeholder'        => false,
            'help'               => false,
        ]);
        $this->addWithDefaults($builder, 'createdAt', DateTimeType::class, [
            'label'              => 'Created at',
            'required'           => false,
            'widget'             => 'single_text',
            'translation_domain' => false,
            'placeholder'        => false,
            'help'               => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
        ]);
    }
}
