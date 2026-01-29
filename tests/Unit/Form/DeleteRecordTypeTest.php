<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Form;

use Nowo\PerformanceBundle\Form\DeleteRecordType;
use Symfony\Component\Form\Test\TypeTestCase;

final class DeleteRecordTypeTest extends TypeTestCase
{
    private DeleteRecordType $formType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formType = new DeleteRecordType();
    }

    public function testBuildFormCreatesSubmitOnly(): void
    {
        $form = $this->factory->create(DeleteRecordType::class, null, [
            'csrf_token_id' => 'delete_performance_record',
        ]);

        $this->assertTrue($form->has('submit'));
    }

    public function testConfigureOptionsSetsDefaults(): void
    {
        $resolver = $this->createMock(\Symfony\Component\OptionsResolver\OptionsResolver::class);
        $resolver->expects($this->once())
            ->method('setDefaults')
            ->with($this->callback(function (array $defaults): bool {
                return isset($defaults['method'])
                    && $defaults['method'] === 'POST'
                    && isset($defaults['csrf_protection'])
                    && $defaults['csrf_protection'] === true
                    && isset($defaults['csrf_field_name'])
                    && $defaults['csrf_field_name'] === '_token'
                    && isset($defaults['csrf_token_id'])
                    && isset($defaults['submit_attr_class']);
            }));
        $resolver->expects($this->once())->method('setRequired')->with('csrf_token_id');

        $this->formType->configureOptions($resolver);
    }

    public function testGetBlockPrefix(): void
    {
        $this->assertSame('delete_record', $this->formType->getBlockPrefix());
    }

    public function testSubmitButtonUsesCustomAttrClassWhenProvided(): void
    {
        $form = $this->factory->create(DeleteRecordType::class, null, [
            'csrf_token_id' => 'delete_performance_record',
            'submit_attr_class' => 'custom-delete-class',
        ]);

        $submit = $form->get('submit');
        $this->assertSame('custom-delete-class', $submit->getConfig()->getOption('attr')['class']);
    }
}
