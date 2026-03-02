<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Form;

use DateTimeImmutable;
use DateTimeInterface;
use Nowo\PerformanceBundle\Form\PerformanceFiltersType;
use Symfony\Component\Form\Test\TypeTestCase;

final class PerformanceFiltersTypeTest extends TypeTestCase
{
    private PerformanceFiltersType $formType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formType = new PerformanceFiltersType();
    }

    public function testBuildFormCreatesAllFields(): void
    {
        $form = $this->factory->create(PerformanceFiltersType::class, null, [
            'environments' => ['dev', 'test'],
            'current_env'  => 'dev',
        ]);

        $this->assertTrue($form->has('env'));
        $this->assertTrue($form->has('route'));
        $this->assertTrue($form->has('sort'));
        $this->assertTrue($form->has('order'));
        $this->assertTrue($form->has('limit'));
        $this->assertTrue($form->has('min_request_time'));
        $this->assertTrue($form->has('max_request_time'));
        $this->assertTrue($form->has('min_query_count'));
        $this->assertTrue($form->has('max_query_count'));
        $this->assertTrue($form->has('date_from'));
        $this->assertTrue($form->has('date_to'));
        $this->assertTrue($form->has('submit'));
    }

    public function testBuildFormSetsDefaultValues(): void
    {
        $form = $this->factory->create(PerformanceFiltersType::class, null, [
            'environments'    => ['dev', 'test'],
            'current_env'     => 'dev',
            'current_route'   => 'app_home',
            'current_sort_by' => 'requestTime',
            'current_order'   => 'DESC',
            'current_limit'   => 50,
        ]);

        $this->assertSame('dev', $form->get('env')->getData());
        $this->assertSame('app_home', $form->get('route')->getData());
        $this->assertSame('requestTime', $form->get('sort')->getData());
        $this->assertSame('DESC', $form->get('order')->getData());
        $this->assertSame(50, $form->get('limit')->getData());
    }

    public function testConfigureOptionsSetsDefaults(): void
    {
        $resolver = $this->createMock(\Symfony\Component\OptionsResolver\OptionsResolver::class);
        $resolver->expects($this->once())
            ->method('setDefaults')
            ->with($this->callback(static function ($defaults) {
                return isset($defaults['method'])
                    && $defaults['method'] === 'GET'
                    && isset($defaults['csrf_protection'])
                    && $defaults['csrf_protection'] === false;
            }));

        $resolver->expects($this->atLeastOnce())
            ->method('setAllowedTypes');

        $this->formType->configureOptions($resolver);
    }

    public function testGetBlockPrefixReturnsEmptyString(): void
    {
        $this->assertSame('', $this->formType->getBlockPrefix());
    }

    public function testFormSubmissionWithFilters(): void
    {
        $form = $this->factory->create(PerformanceFiltersType::class, null, [
            'environments' => ['dev', 'test'],
        ]);

        $form->submit([
            'env'              => 'dev',
            'route'            => 'app_home',
            'sort'             => 'requestTime',
            'order'            => 'DESC',
            'limit'            => 25,
            'min_request_time' => '0.1',
            'max_request_time' => '1.0',
            'min_query_count'  => '5',
            'max_query_count'  => '50',
        ]);

        $this->assertTrue($form->isValid());
        $data = $form->getData();
        $this->assertSame('dev', $data['env']);
        $this->assertSame('app_home', $data['route']);
        $this->assertSame('requestTime', $data['sort']);
        $this->assertSame('DESC', $data['order']);
        $this->assertSame(25, $data['limit']);
    }

    public function testFormBuildsWithCurrentDateFromAndDateTo(): void
    {
        $from = new DateTimeImmutable('2026-01-01');
        $to   = new DateTimeImmutable('2026-01-31');
        $form = $this->factory->create(PerformanceFiltersType::class, null, [
            'environments'      => ['dev'],
            'current_date_from' => $from,
            'current_date_to'   => $to,
        ]);

        $this->assertTrue($form->has('date_from'));
        $this->assertTrue($form->has('date_to'));
        $this->assertInstanceOf(DateTimeInterface::class, $form->get('date_from')->getData());
        $this->assertInstanceOf(DateTimeInterface::class, $form->get('date_to')->getData());
        $this->assertSame('2026-01-01', $form->get('date_from')->getData()->format('Y-m-d'));
        $this->assertSame('2026-01-31', $form->get('date_to')->getData()->format('Y-m-d'));
    }

    public function testFormSubmissionWithDateFromAndDateTo(): void
    {
        $form = $this->factory->create(PerformanceFiltersType::class, null, [
            'environments' => ['dev', 'prod'],
        ]);

        $form->submit([
            'env'       => 'prod',
            'route'     => '',
            'sort'      => 'name',
            'order'     => 'ASC',
            'limit'     => 50,
            'date_from' => '2026-02-01',
            'date_to'   => '2026-02-28',
        ]);

        $this->assertTrue($form->isValid());
        $data = $form->getData();
        $this->assertSame('prod', $data['env']);
        $this->assertInstanceOf(DateTimeInterface::class, $data['date_from']);
        $this->assertInstanceOf(DateTimeInterface::class, $data['date_to']);
        $this->assertSame('2026-02-01', $data['date_from']->format('Y-m-d'));
        $this->assertSame('2026-02-28', $data['date_to']->format('Y-m-d'));
    }

    public function testFormMethodIsGet(): void
    {
        $form = $this->factory->create(PerformanceFiltersType::class, null, [
            'environments' => ['dev'],
        ]);

        $this->assertSame('GET', $form->getConfig()->getOption('method'));
    }

    public function testFormBuildsWithStageEnvironment(): void
    {
        $form = $this->factory->create(PerformanceFiltersType::class, null, [
            'environments' => ['dev', 'stage', 'prod'],
            'current_env'  => 'stage',
        ]);

        $this->assertTrue($form->has('env'));
        $this->assertSame('stage', $form->get('env')->getData());
    }

    public function testFormSubmissionWithSortAccessCount(): void
    {
        $form = $this->factory->create(PerformanceFiltersType::class, null, [
            'environments' => ['dev', 'prod'],
        ]);

        $form->submit([
            'env'   => 'dev',
            'route' => '',
            'sort'  => 'accessCount',
            'order' => 'ASC',
            'limit' => 25,
        ]);

        $this->assertTrue($form->isValid());
        $data = $form->getData();
        $this->assertSame('accessCount', $data['sort']);
        $this->assertSame('ASC', $data['order']);
    }

    public function testFormSubmissionWithSortQueryTimeAndOrderAsc(): void
    {
        $form = $this->factory->create(PerformanceFiltersType::class, null, [
            'environments' => ['dev'],
        ]);

        $form->submit([
            'env'   => 'dev',
            'route' => '',
            'sort'  => 'queryTime',
            'order' => 'ASC',
            'limit' => 10,
        ]);

        $this->assertTrue($form->isValid());
        $data = $form->getData();
        $this->assertSame('queryTime', $data['sort']);
        $this->assertSame('ASC', $data['order']);
        $this->assertSame(10, $data['limit']);
    }

    public function testFormSubmissionWithSortCreatedAt(): void
    {
        $form = $this->factory->create(PerformanceFiltersType::class, null, [
            'environments' => ['dev'],
        ]);
        $form->submit([
            'env'   => 'dev',
            'route' => '',
            'sort'  => 'createdAt',
            'order' => 'DESC',
            'limit' => 100,
        ]);
        $this->assertTrue($form->isValid());
        $this->assertSame('createdAt', $form->getData()['sort']);
    }

    public function testFormSubmissionWithSortTotalQueries(): void
    {
        $form = $this->factory->create(PerformanceFiltersType::class, null, [
            'environments' => ['dev'],
        ]);
        $form->submit([
            'env'   => 'dev',
            'route' => '',
            'sort'  => 'totalQueries',
            'order' => 'DESC',
            'limit' => 50,
        ]);
        $this->assertTrue($form->isValid());
        $this->assertSame('totalQueries', $form->getData()['sort']);
    }

    public function testFormBuildsWithCurrentMinAndMaxRequestTime(): void
    {
        $form = $this->factory->create(PerformanceFiltersType::class, null, [
            'environments'             => ['dev'],
            'current_min_request_time' => 0.05,
            'current_max_request_time' => 2.0,
        ]);
        $this->assertSame(0.05, $form->get('min_request_time')->getData());
        $this->assertSame(2.0, $form->get('max_request_time')->getData());
    }

    public function testFormBuildsWithCurrentMinAndMaxQueryCount(): void
    {
        $form = $this->factory->create(PerformanceFiltersType::class, null, [
            'environments'            => ['dev'],
            'current_min_query_count' => 5,
            'current_max_query_count' => 100,
        ]);
        $this->assertSame(5, $form->get('min_query_count')->getData());
        $this->assertSame(100, $form->get('max_query_count')->getData());
    }

    public function testFormSubmissionWithSortLastAccessedAt(): void
    {
        $form = $this->factory->create(PerformanceFiltersType::class, null, [
            'environments' => ['dev'],
        ]);
        $form->submit([
            'env'   => 'dev',
            'route' => '',
            'sort'  => 'lastAccessedAt',
            'order' => 'ASC',
            'limit' => 25,
        ]);
        $this->assertTrue($form->isValid());
        $this->assertSame('lastAccessedAt', $form->getData()['sort']);
    }
}
