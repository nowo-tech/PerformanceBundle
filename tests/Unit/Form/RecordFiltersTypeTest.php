<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Form;

use Nowo\PerformanceBundle\Form\RecordFiltersType;
use Nowo\PerformanceBundle\Model\RecordFilters;
use Symfony\Component\Form\Test\TypeTestCase;

final class RecordFiltersTypeTest extends TypeTestCase
{
    private RecordFiltersType $formType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formType = new RecordFiltersType();
    }

    public function testBuildFormCreatesExpectedFields(): void
    {
        $form = $this->factory->create(RecordFiltersType::class, new RecordFilters(), [
            'environments' => ['dev', 'test', 'prod'],
            'available_routes' => ['app_home', 'api_foo'],
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
                    && isset($defaults['available_routes'])
                    && isset($defaults['all_routes_label'])
                    && isset($defaults['all_status_label']);
            }));
        $resolver->expects($this->exactly(2))->method('setAllowedTypes');

        $this->formType->configureOptions($resolver);
    }

    public function testGetBlockPrefixReturnsEmpty(): void
    {
        $this->assertSame('', $this->formType->getBlockPrefix());
    }

    public function testFormBuildsWithCustomAllRoutesAndAllStatusLabels(): void
    {
        $form = $this->factory->create(RecordFiltersType::class, new RecordFilters(), [
            'environments' => ['dev'],
            'available_routes' => ['api_foo'],
            'all_routes_label' => 'All routes',
            'all_status_label' => 'All statuses',
        ]);

        $this->assertTrue($form->has('route'));
        $this->assertTrue($form->has('status_code'));
    }

    public function testFormSubmissionWithPartialData(): void
    {
        $form = $this->factory->create(RecordFiltersType::class, new RecordFilters(), [
            'environments' => ['dev', 'prod'],
            'available_routes' => ['app_home', 'api_foo'],
        ]);

        $form->submit([
            'env' => 'prod',
            'route' => 'api_foo',
        ]);

        $this->assertTrue($form->isValid());
        $data = $form->getData();
        $this->assertInstanceOf(RecordFilters::class, $data);
        $this->assertSame('prod', $data->env);
        $this->assertSame('api_foo', $data->route);
    }

    public function testFormBuildsWithEmptyAvailableRoutes(): void
    {
        $form = $this->factory->create(RecordFiltersType::class, new RecordFilters(), [
            'environments' => ['dev'],
            'available_routes' => [],
        ]);

        $this->assertTrue($form->has('route'));
        $choices = $form->get('route')->getConfig()->getOption('choices');
        $this->assertArrayHasKey('access_statistics.all_routes', $choices);
        $this->assertSame('', $choices['access_statistics.all_routes']);
    }

    public function testFormSubmissionWithStatusCodeTransformsToInt(): void
    {
        $form = $this->factory->create(RecordFiltersType::class, new RecordFilters(), [
            'environments' => ['dev', 'prod'],
            'available_routes' => ['app_home'],
        ]);

        $form->submit([
            'env' => 'dev',
            'route' => '',
            'status_code' => '404',
        ]);

        $this->assertTrue($form->isValid());
        $data = $form->getData();
        $this->assertInstanceOf(RecordFilters::class, $data);
        $this->assertSame(404, $data->statusCode);
    }

    public function testFormSubmissionWithStatusCode503(): void
    {
        $form = $this->factory->create(RecordFiltersType::class, new RecordFilters(), [
            'environments' => ['dev'],
            'available_routes' => [],
        ]);

        $form->submit([
            'env' => 'dev',
            'route' => '',
            'status_code' => '503',
        ]);

        $this->assertTrue($form->isValid());
        $data = $form->getData();
        $this->assertSame(503, $data->statusCode);
    }

    public function testFormSubmissionWithStatusCode200(): void
    {
        $form = $this->factory->create(RecordFiltersType::class, new RecordFilters(), [
            'environments' => ['dev'],
            'available_routes' => [],
        ]);

        $form->submit([
            'env' => 'dev',
            'route' => '',
            'status_code' => '200',
        ]);

        $this->assertTrue($form->isValid());
        $data = $form->getData();
        $this->assertSame(200, $data->statusCode);
    }

    public function testFormSubmissionWithEmptyStatusCodeTransformsToNull(): void
    {
        $form = $this->factory->create(RecordFiltersType::class, new RecordFilters(), [
            'environments' => ['dev'],
            'available_routes' => [],
        ]);

        $form->submit([
            'env' => 'dev',
            'route' => '',
            'status_code' => '',
        ]);

        $this->assertTrue($form->isValid());
        $data = $form->getData();
        $this->assertInstanceOf(RecordFilters::class, $data);
        $this->assertNull($data->statusCode);
    }

    public function testFormSubmissionWithQueryTimeFilters(): void
    {
        $form = $this->factory->create(RecordFiltersType::class, new RecordFilters(), [
            'environments' => ['dev', 'prod'],
            'available_routes' => ['app_home'],
        ]);

        $form->submit([
            'env' => 'dev',
            'route' => 'app_home',
            'status_code' => '',
            'min_query_time' => '0.1',
            'max_query_time' => '2.5',
        ]);

        $this->assertTrue($form->isValid());
        $data = $form->getData();
        $this->assertInstanceOf(RecordFilters::class, $data);
        $this->assertSame(0.1, $data->minQueryTime);
        $this->assertSame(2.5, $data->maxQueryTime);
    }

    public function testFormBuildsWithMemoryFields(): void
    {
        $form = $this->factory->create(RecordFiltersType::class, new RecordFilters(), [
            'environments' => ['dev'],
            'available_routes' => ['app_home'],
        ]);

        $this->assertTrue($form->has('min_memory_mb'));
        $this->assertTrue($form->has('max_memory_mb'));
        $this->assertTrue($form->has('filter'));
    }

    public function testFormSubmissionWithStageEnvironment(): void
    {
        $form = $this->factory->create(RecordFiltersType::class, new RecordFilters(), [
            'environments' => ['dev', 'stage', 'prod'],
            'available_routes' => ['app_home'],
        ]);

        $form->submit([
            'env' => 'stage',
            'route' => '',
            'status_code' => '',
        ]);

        $this->assertTrue($form->isValid());
        $data = $form->getData();
        $this->assertSame('stage', $data->env);
    }

    public function testFormMethodIsGet(): void
    {
        $form = $this->factory->create(RecordFiltersType::class, new RecordFilters(), [
            'environments' => ['dev'],
            'available_routes' => [],
        ]);

        $this->assertSame('GET', $form->getConfig()->getOption('method'));
    }

    public function testFormBuildsWithDataHavingMemoryUsageInitializesMemoryFields(): void
    {
        $filters = new RecordFilters();
        $filters->minMemoryUsage = 2 * 1024 * 1024; // 2 MB
        $filters->maxMemoryUsage = 50 * 1024 * 1024; // 50 MB

        $form = $this->factory->create(RecordFiltersType::class, $filters, [
            'environments' => ['dev'],
            'available_routes' => ['app_home'],
        ]);

        $this->assertEqualsWithDelta(2.0, $form->get('min_memory_mb')->getData(), 0.01);
        $this->assertEqualsWithDelta(50.0, $form->get('max_memory_mb')->getData(), 0.01);
    }
}
