<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Form;

use Nowo\PerformanceBundle\Form\ClearPerformanceDataType;
use Nowo\PerformanceBundle\Model\ClearPerformanceDataRequest;
use Symfony\Component\Form\Test\TypeTestCase;

final class ClearPerformanceDataTypeTest extends TypeTestCase
{
    private ClearPerformanceDataType $formType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formType = new ClearPerformanceDataType();
    }

    public function testBuildFormCreatesEnvAndSubmit(): void
    {
        $form = $this->factory->create(ClearPerformanceDataType::class, null, [
            'csrf_protection' => false,
        ]);

        $this->assertTrue($form->has('env'));
        $this->assertTrue($form->has('submit'));
    }

    public function testConfigureOptionsSetsDefaults(): void
    {
        $resolver = $this->createMock(\Symfony\Component\OptionsResolver\OptionsResolver::class);
        $resolver->expects($this->once())
            ->method('setDefaults')
            ->with($this->callback(static function (array $defaults): bool {
                return isset($defaults['data_class'])
                    && $defaults['data_class'] === ClearPerformanceDataRequest::class
                    && isset($defaults['method'])
                    && $defaults['method'] === 'POST'
                    && isset($defaults['csrf_protection'])
                    && $defaults['csrf_protection'] === true
                    && isset($defaults['csrf_field_name'])
                    && $defaults['csrf_field_name'] === '_token'
                    && isset($defaults['csrf_token_id'])
                    && $defaults['csrf_token_id'] === 'clear_performance_data';
            }));

        $this->formType->configureOptions($resolver);
    }

    public function testGetBlockPrefix(): void
    {
        $this->assertSame('clear_performance_data', $this->formType->getBlockPrefix());
    }

    public function testFormSubmissionBindsToClearPerformanceDataRequest(): void
    {
        $form = $this->factory->create(ClearPerformanceDataType::class, new ClearPerformanceDataRequest(), [
            'csrf_protection' => false,
        ]);

        $form->submit(['env' => 'prod']);

        $this->assertTrue($form->isValid());
        $data = $form->getData();
        $this->assertInstanceOf(ClearPerformanceDataRequest::class, $data);
        $this->assertSame('prod', $data->env);
    }

    public function testFormSubmissionWithEmptyEnv(): void
    {
        $form = $this->factory->create(ClearPerformanceDataType::class, new ClearPerformanceDataRequest(), [
            'csrf_protection' => false,
        ]);

        $form->submit(['env' => '']);

        $this->assertTrue($form->isValid());
        $data = $form->getData();
        $this->assertInstanceOf(ClearPerformanceDataRequest::class, $data);
        $this->assertNull($data->env);
    }

    public function testFormSubmissionWithDifferentEnvironments(): void
    {
        $form = $this->factory->create(ClearPerformanceDataType::class, new ClearPerformanceDataRequest(), [
            'csrf_protection' => false,
        ]);

        $form->submit(['env' => 'stage']);

        $this->assertTrue($form->isValid());
        $data = $form->getData();
        $this->assertSame('stage', $data->env);
    }

    public function testFormBuildsWithCsrfProtectionDisabled(): void
    {
        $form = $this->factory->create(ClearPerformanceDataType::class, null, [
            'csrf_protection' => false,
        ]);

        $this->assertFalse($form->getConfig()->getOption('csrf_protection'));
    }

    public function testFormBuildsWithTestEnvironment(): void
    {
        $form = $this->factory->create(ClearPerformanceDataType::class, new ClearPerformanceDataRequest(), [
            'csrf_protection' => false,
        ]);

        $form->submit(['env' => 'test']);

        $this->assertTrue($form->isValid());
        $data = $form->getData();
        $this->assertSame('test', $data->env);
    }

    public function testFormMethodIsPost(): void
    {
        $form = $this->factory->create(ClearPerformanceDataType::class, null, [
            'csrf_protection' => false,
        ]);

        $this->assertSame('POST', $form->getConfig()->getOption('method'));
    }
}
