<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Form;

use Nowo\PerformanceBundle\Form\StatisticsEnvFilterType;
use Nowo\PerformanceBundle\Model\StatisticsEnvFilter;
use Symfony\Component\Form\Test\TypeTestCase;

final class StatisticsEnvFilterTypeTest extends TypeTestCase
{
    private StatisticsEnvFilterType $formType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formType = new StatisticsEnvFilterType();
    }

    public function testBuildFormCreatesEnvField(): void
    {
        $form = $this->factory->create(StatisticsEnvFilterType::class, null, [
            'environments' => ['dev', 'test', 'prod'],
        ]);

        $this->assertTrue($form->has('env'));
    }

    public function testConfigureOptionsSetsDefaults(): void
    {
        $resolver = $this->createMock(\Symfony\Component\OptionsResolver\OptionsResolver::class);
        $resolver->expects($this->once())
            ->method('setDefaults')
            ->with($this->callback(function (array $defaults): bool {
                return isset($defaults['data_class'])
                    && $defaults['data_class'] === StatisticsEnvFilter::class
                    && isset($defaults['method'])
                    && $defaults['method'] === 'GET'
                    && isset($defaults['csrf_protection'])
                    && $defaults['csrf_protection'] === false
                    && isset($defaults['environments']);
            }));
        $resolver->expects($this->once())->method('setAllowedTypes')->with('environments', 'array');

        $this->formType->configureOptions($resolver);
    }

    public function testGetBlockPrefix(): void
    {
        $this->assertSame('statistics_env', $this->formType->getBlockPrefix());
    }

    public function testFormSubmissionBindsToStatisticsEnvFilter(): void
    {
        $form = $this->factory->create(StatisticsEnvFilterType::class, null, [
            'environments' => ['dev', 'test', 'prod'],
        ]);

        $form->submit(['env' => 'prod']);

        $this->assertTrue($form->isValid());
        $data = $form->getData();
        $this->assertInstanceOf(StatisticsEnvFilter::class, $data);
        $this->assertSame('prod', $data->env);
    }
}
