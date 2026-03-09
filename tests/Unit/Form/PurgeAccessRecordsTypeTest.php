<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Form;

use Nowo\PerformanceBundle\Form\PurgeAccessRecordsType;
use Nowo\PerformanceBundle\Model\PurgeAccessRecordsRequest;
use Symfony\Component\Form\Test\TypeTestCase;

final class PurgeAccessRecordsTypeTest extends TypeTestCase
{
    private PurgeAccessRecordsType $formType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formType = new PurgeAccessRecordsType();
    }

    public function testBuildFormCreatesExpectedFields(): void
    {
        $form = $this->factory->create(PurgeAccessRecordsType::class, new PurgeAccessRecordsRequest(), [
            'environments' => ['dev', 'prod'],
            'default_days' => 30,
        ]);

        $this->assertTrue($form->has('purgeType'));
        $this->assertTrue($form->has('days'));
        $this->assertTrue($form->has('env'));
        $this->assertTrue($form->has('submit'));
    }

    public function testConfigureOptionsSetsDefaults(): void
    {
        $resolver = $this->createMock(\Symfony\Component\OptionsResolver\OptionsResolver::class);
        $resolver->expects($this->once())
            ->method('setDefaults')
            ->with($this->callback(static fn (array $defaults): bool => isset($defaults['data_class'])
                && $defaults['data_class'] === PurgeAccessRecordsRequest::class
                && isset($defaults['method'])
                && $defaults['method'] === 'POST'
                && isset($defaults['csrf_protection'])
                && $defaults['csrf_protection'] === true
                && isset($defaults['environments'])
                && isset($defaults['default_days'])
                && $defaults['default_days'] === 30));
        $resolver->expects($this->once())->method('setAllowedTypes')->with('environments', 'array');

        $this->formType->configureOptions($resolver);
    }

    public function testGetBlockPrefix(): void
    {
        $this->assertSame('purge_access_records', $this->formType->getBlockPrefix());
    }

    public function testFormSubmissionBindsData(): void
    {
        $form = $this->factory->create(PurgeAccessRecordsType::class, new PurgeAccessRecordsRequest(), [
            'environments' => ['dev', 'prod'],
            'default_days' => 30,
        ]);

        $form->submit([
            'purgeType' => PurgeAccessRecordsRequest::PURGE_ALL,
            'days'      => '90',
            'env'       => 'prod',
        ]);

        $this->assertTrue($form->isValid());
        $data = $form->getData();
        $this->assertInstanceOf(PurgeAccessRecordsRequest::class, $data);
        $this->assertSame(PurgeAccessRecordsRequest::PURGE_ALL, $data->purgeType);
        $this->assertSame(90, $data->days);
        $this->assertSame('prod', $data->env);
    }

    public function testFormBuildsWithCustomEnvironments(): void
    {
        $form = $this->factory->create(PurgeAccessRecordsType::class, new PurgeAccessRecordsRequest(), [
            'environments' => ['dev', 'stage', 'prod'],
            'default_days' => 7,
        ]);

        $this->assertSame(7, $form->get('days')->getData());
        $this->assertTrue($form->has('env'));
    }
}
