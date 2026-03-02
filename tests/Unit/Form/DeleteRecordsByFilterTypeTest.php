<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Form;

use Nowo\PerformanceBundle\Form\DeleteRecordsByFilterType;
use Nowo\PerformanceBundle\Model\DeleteRecordsByFilterRequest;
use Symfony\Component\Form\Test\TypeTestCase;

final class DeleteRecordsByFilterTypeTest extends TypeTestCase
{
    private DeleteRecordsByFilterType $formType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formType = new DeleteRecordsByFilterType();
    }

    public function testBuildFormCreatesAllFields(): void
    {
        $form = $this->factory->create(DeleteRecordsByFilterType::class, null, [
            'from_value' => 'access_records',
        ]);

        $this->assertTrue($form->has('_from'));
        $this->assertTrue($form->has('env'));
        $this->assertTrue($form->has('start_date'));
        $this->assertTrue($form->has('end_date'));
        $this->assertTrue($form->has('route'));
        $this->assertTrue($form->has('status_code'));
        $this->assertTrue($form->has('min_query_time'));
        $this->assertTrue($form->has('max_query_time'));
        $this->assertTrue($form->has('min_memory_usage'));
        $this->assertTrue($form->has('max_memory_usage'));
        $this->assertTrue($form->has('submit'));
    }

    public function testConfigureOptionsSetsDefaults(): void
    {
        $resolver = $this->createMock(\Symfony\Component\OptionsResolver\OptionsResolver::class);
        $resolver->expects($this->once())
            ->method('setDefaults')
            ->with($this->callback(static function (array $defaults): bool {
                return isset($defaults['data_class'])
                    && $defaults['data_class'] === DeleteRecordsByFilterRequest::class
                    && isset($defaults['method'])
                    && $defaults['method'] === 'POST'
                    && isset($defaults['csrf_protection'])
                    && $defaults['csrf_protection'] === true
                    && isset($defaults['from_value'])
                    && $defaults['from_value'] === 'access_records';
            }));

        $this->formType->configureOptions($resolver);
    }

    public function testGetBlockPrefix(): void
    {
        $this->assertSame('delete_records_by_filter', $this->formType->getBlockPrefix());
    }

    public function testFormSubmissionBindsToDeleteRecordsByFilterRequest(): void
    {
        $form = $this->factory->create(DeleteRecordsByFilterType::class, null, [
            'from_value'      => 'access_records',
            'csrf_protection' => false,
        ]);

        $form->submit([
            '_from'            => 'access_records',
            'env'              => 'prod',
            'start_date'       => '2026-01-01',
            'end_date'         => '2026-01-31',
            'route'            => 'app_user',
            'status_code'      => '404',
            'min_query_time'   => '0.1',
            'max_query_time'   => '3',
            'min_memory_usage' => '2097152',
            'max_memory_usage' => '104857600',
        ]);

        $this->assertTrue($form->isValid());
        $data = $form->getData();
        $this->assertInstanceOf(DeleteRecordsByFilterRequest::class, $data);
        $this->assertSame('access_records', $data->from);
        $this->assertSame('prod', $data->env);
        $this->assertSame('2026-01-01', $data->startDate);
        $this->assertSame('2026-01-31', $data->endDate);
        $this->assertSame('app_user', $data->route);
        $this->assertSame('404', $data->statusCode);
        $this->assertSame('0.1', $data->minQueryTime);
        $this->assertSame('3', $data->maxQueryTime);
        $this->assertSame('2097152', $data->minMemoryUsage);
        $this->assertSame('104857600', $data->maxMemoryUsage);
    }

    public function testFormBuildsWithFromValueAccessStatistics(): void
    {
        $form = $this->factory->create(DeleteRecordsByFilterType::class, null, [
            'from_value'      => 'access_statistics',
            'csrf_protection' => false,
        ]);

        $this->assertTrue($form->has('_from'));
        $this->assertSame('access_statistics', $form->get('_from')->getData());
    }

    public function testFormSubmissionWithPartialData(): void
    {
        $form = $this->factory->create(DeleteRecordsByFilterType::class, null, [
            'from_value'      => 'access_records',
            'csrf_protection' => false,
        ]);

        $form->submit([
            '_from' => 'access_records',
            'env'   => 'dev',
            'route' => 'app_home',
        ]);

        $this->assertTrue($form->isValid());
        $data = $form->getData();
        $this->assertInstanceOf(DeleteRecordsByFilterRequest::class, $data);
        $this->assertSame('access_records', $data->from);
        $this->assertSame('dev', $data->env);
        $this->assertSame('app_home', $data->route);
    }

    public function testFormSubmissionWithEmptyOptionalFields(): void
    {
        $form = $this->factory->create(DeleteRecordsByFilterType::class, null, [
            'from_value'      => 'access_records',
            'csrf_protection' => false,
        ]);

        $form->submit([
            '_from'            => 'access_statistics',
            'env'              => 'prod',
            'start_date'       => '',
            'end_date'         => '',
            'route'            => '',
            'status_code'      => '',
            'min_query_time'   => '',
            'max_query_time'   => '',
            'min_memory_usage' => '',
            'max_memory_usage' => '',
        ]);

        $this->assertTrue($form->isValid());
        $data = $form->getData();
        $this->assertSame('access_statistics', $data->from);
        $this->assertSame('prod', $data->env);
    }

    public function testFormSubmissionWithTestEnvironment(): void
    {
        $form = $this->factory->create(DeleteRecordsByFilterType::class, null, [
            'from_value'      => 'access_records',
            'csrf_protection' => false,
        ]);

        $form->submit([
            '_from' => 'access_records',
            'env'   => 'test',
            'route' => 'app_home',
        ]);

        $this->assertTrue($form->isValid());
        $data = $form->getData();
        $this->assertSame('test', $data->env);
        $this->assertSame('app_home', $data->route);
    }

    public function testFormSubmissionWithStageEnvironment(): void
    {
        $form = $this->factory->create(DeleteRecordsByFilterType::class, null, [
            'from_value'      => 'access_records',
            'csrf_protection' => false,
        ]);

        $form->submit([
            '_from' => 'access_records',
            'env'   => 'stage',
            'route' => 'api_dashboard',
        ]);

        $this->assertTrue($form->isValid());
        $data = $form->getData();
        $this->assertSame('stage', $data->env);
        $this->assertSame('api_dashboard', $data->route);
    }

    public function testFormMethodIsPost(): void
    {
        $form = $this->factory->create(DeleteRecordsByFilterType::class, null, [
            'from_value'      => 'access_records',
            'csrf_protection' => false,
        ]);

        $this->assertSame('POST', $form->getConfig()->getOption('method'));
    }

    public function testFormBuildsWithFromValueAccessRecords(): void
    {
        $form = $this->factory->create(DeleteRecordsByFilterType::class, null, [
            'from_value'      => 'access_records',
            'csrf_protection' => false,
        ]);

        $this->assertTrue($form->has('_from'));
        $this->assertSame('access_records', $form->get('_from')->getData());
    }
}
