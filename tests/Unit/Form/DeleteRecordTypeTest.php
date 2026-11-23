<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Form;

use Nowo\PerformanceBundle\Form\DeleteRecordType;
use Nowo\PerformanceBundle\Tests\Support\FormKitTestSupport;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function array_key_exists;

final class DeleteRecordTypeTest extends TypeTestCase
{
    private DeleteRecordType $formType;

    /**
     * @return list<object>
     */
    protected function getTypes(): array
    {
        return [
            FormKitTestSupport::withMerger(new DeleteRecordType()),
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->formType = FormKitTestSupport::withMerger(new DeleteRecordType());
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
        $resolver = $this->createMock(OptionsResolver::class);
        $resolver->expects($this->once())
            ->method('setDefaults')
            ->with($this->callback(static fn (array $defaults): bool => isset($defaults['method']) && $defaults['method'] === 'POST'
                && isset($defaults['csrf_protection']) && $defaults['csrf_protection'] === true
                && isset($defaults['csrf_field_name']) && $defaults['csrf_field_name'] === '_token'
                && isset($defaults['csrf_token_id']) && $defaults['csrf_token_id'] === 'delete_performance_record'
                && !array_key_exists('submit_attr_class', $defaults)));
        $resolver->expects($this->never())->method('setAllowedTypes');
        $resolver->expects($this->never())->method('setDeprecated');
        $resolver->expects($this->once())
            ->method('setRequired')
            ->with('csrf_token_id');

        $this->formType->configureOptions($resolver);
    }

    public function testGetBlockPrefix(): void
    {
        $this->assertSame('delete_record', $this->formType->getBlockPrefix());
    }

    /** The submit_attr_class option (deprecated and ignored since 3.15.0) was removed in 4.0: passing it fails. */
    public function testRemovedSubmitAttrClassOptionIsRefused(): void
    {
        $this->expectException(UndefinedOptionsException::class);
        $this->expectExceptionMessage('submit_attr_class');

        $this->factory->create(DeleteRecordType::class, null, [
            'csrf_protection'   => false,
            'csrf_token_id'     => 'delete_performance_record',
            'submit_attr_class' => 'custom-btn custom-danger',
        ]);
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
