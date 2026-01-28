<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Form;

use Nowo\PerformanceBundle\Form\RecordFiltersType;
use Nowo\PerformanceBundle\Model\RecordFilters;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Test\TypeTestCase;

final class RecordFiltersTypeTest extends TypeTestCase
{
    private RecordFiltersType $formType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formType = new RecordFiltersType();
    }

    public function testBuildFormCreatesAllFields(): void
    {
        $form = $this->factory->create(RecordFiltersType::class, null, [
            'environments' => ['dev', 'test'],
            'available_routes' => ['app_home'],
            'all_routes_label' => 'access_statistics.all_routes',
            'all_status_label' => 'access_statistics.all_status_codes',
        ]);

        $this->assertTrue($form->has('start_date'));
        $this->assertTrue($form->has('end_date'));
        $this->assertTrue($form->has('env'));
        $this->assertTrue($form->has('route'));
        $this->assertTrue($form->has('status_code'));
        $this->assertTrue($form->has('min_query_time'));
        $this->assertTrue($form->has('max_query_time'));
        $this->assertTrue($form->has('min_memory_mb'));
        $this->assertTrue($form->has('max_memory_mb'));
        $this->assertTrue($form->has('filter'));
    }

    public function testConfigureOptionsSetsDefaults(): void
    {
        $resolver = $this->createMock(\Symfony\Component\OptionsResolver\OptionsResolver::class);
        $resolver->expects($this->once())
            ->method('setDefaults')
            ->with($this->callback(function (array $defaults): bool {
                return isset($defaults['data_class'])
                    && $defaults['data_class'] === RecordFilters::class
                    && isset($defaults['method'])
                    && $defaults['method'] === 'GET'
                    && isset($defaults['csrf_protection'])
                    && $defaults['csrf_protection'] === false
                    && isset($defaults['environments'])
                    && isset($defaults['available_routes']);
            }));
        $resolver->expects($this->atLeastOnce())->method('setAllowedTypes');

        $this->formType->configureOptions($resolver);
    }

    public function testGetBlockPrefixReturnsEmptyString(): void
    {
        $this->assertSame('', $this->formType->getBlockPrefix());
    }

    public function testFormSubmissionBindsToRecordFilters(): void
    {
        $form = $this->factory->create(RecordFiltersType::class, null, [
            'environments' => ['dev', 'test', 'prod'],
            'available_routes' => ['app_home', 'api_foo'],
            'all_routes_label' => 'All',
            'all_status_label' => 'All statuses',
        ]);

        $form->submit([
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'env' => 'dev',
            'route' => 'app_home',
            'status_code' => '200',
            'min_query_time' => '0.05',
            'max_query_time' => '2.0',
        ]);

        $this->assertTrue($form->isValid());
        $data = $form->getData();
        $this->assertInstanceOf(RecordFilters::class, $data);
        $this->assertNotNull($data->startDate);
        $this->assertSame('2026-01-01', $data->startDate->format('Y-m-d'));
        $this->assertNotNull($data->endDate);
        $this->assertSame('2026-01-31', $data->endDate->format('Y-m-d'));
        $this->assertSame('dev', $data->env);
        $this->assertSame('app_home', $data->route);
        $this->assertSame(200, $data->statusCode);
        $this->assertSame(0.05, $data->minQueryTime);
        $this->assertSame(2.0, $data->maxQueryTime);
    }
}
