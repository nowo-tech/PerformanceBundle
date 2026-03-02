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

    /** Form type builds without adding a submit child when buildForm submit block is commented out. */
    public function testBuildFormBuildsSuccessfully(): void
    {
        $form = $this->factory->create(DeleteRecordType::class, null, [
            'csrf_protection' => false,
            'csrf_token_id'   => 'delete_performance_record',
        ]);

        $this->assertFalse($form->has('submit'));
        $this->assertCount(0, $form);
    }

    public function testConfigureOptionsSetsDefaultsAndRequired(): void
    {
        $resolver = $this->createMock(\Symfony\Component\OptionsResolver\OptionsResolver::class);
        $resolver->expects($this->once())
            ->method('setDefaults')
            ->with($this->callback(static function (array $defaults): bool {
                return isset($defaults['method']) && $defaults['method'] === 'POST'
                    && isset($defaults['csrf_protection']) && $defaults['csrf_protection'] === true
                    && isset($defaults['csrf_field_name']) && $defaults['csrf_field_name'] === '_token'
                    && isset($defaults['csrf_token_id']) && $defaults['csrf_token_id'] === 'delete_performance_record'
                    && isset($defaults['submit_attr_class']) && $defaults['submit_attr_class'] === 'btn btn-danger btn-sm';
            }));
        $resolver->expects($this->once())
            ->method('setRequired')
            ->with('csrf_token_id');

        $this->formType->configureOptions($resolver);
    }

    public function testGetBlockPrefix(): void
    {
        $this->assertSame('delete_record', $this->formType->getBlockPrefix());
    }

    /** Form accepts submit_attr_class option (used when submit is present); no submit child in current build. */
    public function testFormAcceptsSubmitAttrClassOption(): void
    {
        $form = $this->factory->create(DeleteRecordType::class, null, [
            'csrf_protection'   => false,
            'csrf_token_id'     => 'delete_performance_record',
            'submit_attr_class' => 'custom-btn custom-danger',
        ]);

        $this->assertFalse($form->has('submit'));
        $this->assertCount(0, $form);
    }

    public function testFormBuildsWithCustomCsrfTokenId(): void
    {
        $form = $this->factory->create(DeleteRecordType::class, null, [
            'csrf_protection' => false,
            'csrf_token_id'   => 'custom_delete_token',
        ]);

        $this->assertCount(0, $form);
    }

    public function testFormHasCsrfProtectionEnabledByDefault(): void
    {
        $form = $this->factory->create(DeleteRecordType::class, null, [
            'csrf_token_id' => 'delete_performance_record',
        ]);

        $this->assertTrue($form->getConfig()->getOption('csrf_protection'));
    }

    public function testFormMethodIsPost(): void
    {
        $form = $this->factory->create(DeleteRecordType::class, null, [
            'csrf_protection' => false,
            'csrf_token_id'   => 'delete_performance_record',
        ]);

        $this->assertSame('POST', $form->getConfig()->getOption('method'));
    }

    public function testFormHasCorrectCsrfFieldNameAndTokenId(): void
    {
        $form = $this->factory->create(DeleteRecordType::class, null, [
            'csrf_protection' => false,
            'csrf_token_id'   => 'delete_performance_record',
        ]);

        $this->assertSame('_token', $form->getConfig()->getOption('csrf_field_name'));
        $this->assertSame('delete_performance_record', $form->getConfig()->getOption('csrf_token_id'));
    }
}
