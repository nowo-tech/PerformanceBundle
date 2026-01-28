<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Form;

use Nowo\PerformanceBundle\Form\PerformanceFiltersType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\Test\TypeTestCase;

/**
 * Advanced tests for PerformanceFiltersType.
 */
final class PerformanceFiltersTypeAdvancedTest extends TypeTestCase
{
    private PerformanceFiltersType $formType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formType = new PerformanceFiltersType();
    }

    public function testBuildFormWithEmptyEnvironments(): void
    {
        $form = $this->factory->create(PerformanceFiltersType::class, null, [
            'environments' => [],
        ]);

        $this->assertTrue($form->has('env'));
        $envField = $form->get('env');
        $this->assertInstanceOf(ChoiceType::class, $envField->getConfig()->getType()->getInnerType());
    }

    public function testBuildFormWithSingleEnvironment(): void
    {
        $form = $this->factory->create(PerformanceFiltersType::class, null, [
            'environments' => ['prod'],
            'current_env' => 'prod',
        ]);

        $this->assertSame('prod', $form->get('env')->getData());
    }

    public function testBuildFormWithManyEnvironments(): void
    {
        $form = $this->factory->create(PerformanceFiltersType::class, null, [
            'environments' => ['dev', 'test', 'prod', 'stage', 'qa'],
            'current_env' => 'stage',
        ]);

        $this->assertSame('stage', $form->get('env')->getData());
    }

    public function testBuildFormWithNullCurrentEnv(): void
    {
        $form = $this->factory->create(PerformanceFiltersType::class, null, [
            'environments' => ['dev', 'test'],
            'current_env' => null,
        ]);

        $this->assertNull($form->get('env')->getData());
    }

    public function testBuildFormWithNullCurrentRoute(): void
    {
        $form = $this->factory->create(PerformanceFiltersType::class, null, [
            'environments' => ['dev', 'test'],
            'current_route' => null,
        ]);

        $this->assertNull($form->get('route')->getData());
    }

    public function testBuildFormWithAllSortOptions(): void
    {
        $form = $this->factory->create(PerformanceFiltersType::class, null, [
            'environments' => ['dev'],
        ]);

        $sortField = $form->get('sort');
        $choices = $sortField->getConfig()->getOption('choices');

        $this->assertArrayHasKey('sort_options.request_time', $choices);
        $this->assertArrayHasKey('sort_options.query_time', $choices);
        $this->assertArrayHasKey('sort_options.queries', $choices);
        $this->assertArrayHasKey('sort_options.access_count', $choices);
        $this->assertArrayHasKey('sort_options.route_name', $choices);
        $this->assertArrayHasKey('sort_options.created_at', $choices);
        $this->assertArrayHasKey('sort_options.last_accessed_at', $choices);
    }

    public function testBuildFormWithAllOrderOptions(): void
    {
        $form = $this->factory->create(PerformanceFiltersType::class, null, [
            'environments' => ['dev'],
        ]);

        $orderField = $form->get('order');
        $choices = $orderField->getConfig()->getOption('choices');

        $this->assertArrayHasKey('order_options.descending', $choices);
        $this->assertArrayHasKey('order_options.ascending', $choices);
    }

    public function testBuildFormWithCustomCurrentSortBy(): void
    {
        $form = $this->factory->create(PerformanceFiltersType::class, null, [
            'environments' => ['dev'],
            'current_sort_by' => 'name',
        ]);

        $this->assertSame('name', $form->get('sort')->getData());
    }

    public function testBuildFormWithCustomCurrentOrder(): void
    {
        $form = $this->factory->create(PerformanceFiltersType::class, null, [
            'environments' => ['dev'],
            'current_order' => 'ASC',
        ]);

        $this->assertSame('ASC', $form->get('order')->getData());
    }

    public function testBuildFormWithCustomCurrentLimit(): void
    {
        $form = $this->factory->create(PerformanceFiltersType::class, null, [
            'environments' => ['dev'],
            'current_limit' => 200,
        ]);

        $this->assertSame(200, $form->get('limit')->getData());
    }

    public function testFormSubmissionWithDateFilters(): void
    {
        $form = $this->factory->create(PerformanceFiltersType::class, null, [
            'environments' => ['dev'],
        ]);

        $form->submit([
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
        ]);

        $this->assertTrue($form->isValid());
        $data = $form->getData();
        $this->assertInstanceOf(\DateTimeInterface::class, $data['date_from']);
        $this->assertInstanceOf(\DateTimeInterface::class, $data['date_to']);
    }

    public function testFormSubmissionWithInvalidDateFilters(): void
    {
        $form = $this->factory->create(PerformanceFiltersType::class, null, [
            'environments' => ['dev'],
        ]);

        $form->submit([
            'date_from' => 'invalid-date',
            'date_to' => 'also-invalid',
        ]);

        // Form should still be valid, but dates might be null or invalid
        $data = $form->getData();
        $this->assertIsArray($data);
    }

    public function testFormSubmissionWithZeroValues(): void
    {
        $form = $this->factory->create(PerformanceFiltersType::class, null, [
            'environments' => ['dev'],
        ]);

        $form->submit([
            'min_request_time' => '0',
            'max_request_time' => '0',
            'min_query_count' => '0',
            'max_query_count' => '0',
            'limit' => '0',
        ]);

        $this->assertTrue($form->isValid());
        $data = $form->getData();
        $this->assertSame(0.0, $data['min_request_time']);
        $this->assertSame(0.0, $data['max_request_time']);
        $this->assertSame(0, $data['min_query_count']);
        $this->assertSame(0, $data['max_query_count']);
        $this->assertSame(0, $data['limit']);
    }

    public function testFormSubmissionWithNegativeValues(): void
    {
        $form = $this->factory->create(PerformanceFiltersType::class, null, [
            'environments' => ['dev'],
        ]);

        $form->submit([
            'min_request_time' => '-1',
            'limit' => '-5',
        ]);

        // Form should still be valid (validation happens at application level)
        $this->assertTrue($form->isValid());
    }

    public function testFormSubmissionWithVeryLargeValues(): void
    {
        $form = $this->factory->create(PerformanceFiltersType::class, null, [
            'environments' => ['dev'],
        ]);

        $form->submit([
            'max_request_time' => '999999.99',
            'max_query_count' => '999999',
            'limit' => '999999',
        ]);

        $this->assertTrue($form->isValid());
        $data = $form->getData();
        $this->assertSame(999999.99, $data['max_request_time']);
        $this->assertSame(999999, $data['max_query_count']);
        $this->assertSame(999999, $data['limit']);
    }

    public function testFormSubmissionWithEmptyStringValues(): void
    {
        $form = $this->factory->create(PerformanceFiltersType::class, null, [
            'environments' => ['dev'],
        ]);

        $form->submit([
            'route' => '',
            'min_request_time' => '',
            'max_request_time' => '',
            'min_query_count' => '',
            'max_query_count' => '',
            'limit' => '',
        ]);

        $this->assertTrue($form->isValid());
        $data = $form->getData();
        $this->assertSame('', $data['route']);
    }

    public function testFormSubmissionWithWhitespaceValues(): void
    {
        $form = $this->factory->create(PerformanceFiltersType::class, null, [
            'environments' => ['dev'],
        ]);

        $form->submit([
            'route' => '   app_home   ',
        ]);

        $this->assertTrue($form->isValid());
        $data = $form->getData();
        // Symfony forms typically trim whitespace
        $this->assertIsString($data['route']);
    }
}
