<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Form;

use Nowo\PerformanceBundle\Form\ReviewRouteDataType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Test\TypeTestCase;

/**
 * Advanced tests for ReviewRouteDataType.
 */
final class ReviewRouteDataTypeAdvancedTest extends TypeTestCase
{
    private ReviewRouteDataType $formType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formType = new ReviewRouteDataType();
    }

    public function testBuildFormChoicesStructure(): void
    {
        $form = $this->factory->create(ReviewRouteDataType::class);

        $queriesField = $form->get('queries_improved');
        $timeField = $form->get('time_improved');

        $queriesChoices = $queriesField->getConfig()->getOption('choices');
        $timeChoices = $timeField->getConfig()->getOption('choices');

        $this->assertArrayHasKey('review.not_specified', $queriesChoices);
        $this->assertArrayHasKey('review.yes', $queriesChoices);
        $this->assertArrayHasKey('review.no', $queriesChoices);

        $this->assertArrayHasKey('review.not_specified', $timeChoices);
        $this->assertArrayHasKey('review.yes', $timeChoices);
        $this->assertArrayHasKey('review.no', $timeChoices);
    }

    public function testBuildFormChoicesValues(): void
    {
        $form = $this->factory->create(ReviewRouteDataType::class);

        $queriesField = $form->get('queries_improved');
        $timeField = $form->get('time_improved');

        $queriesChoices = $queriesField->getConfig()->getOption('choices');
        $timeChoices = $timeField->getConfig()->getOption('choices');

        $this->assertSame('', $queriesChoices['review.not_specified']);
        $this->assertSame('1', $queriesChoices['review.yes']);
        $this->assertSame('0', $queriesChoices['review.no']);

        $this->assertSame('', $timeChoices['review.not_specified']);
        $this->assertSame('1', $timeChoices['review.yes']);
        $this->assertSame('0', $timeChoices['review.no']);
    }

    public function testFormSubmissionWithQueriesImprovedYes(): void
    {
        $form = $this->factory->create(ReviewRouteDataType::class);

        $form->submit([
            'queries_improved' => '1',
            'time_improved' => '',
        ]);

        $this->assertTrue($form->isValid());
        $data = $form->getData();
        $this->assertSame('1', $data['queries_improved']);
        $this->assertSame('', $data['time_improved']);
    }

    public function testFormSubmissionWithQueriesImprovedNo(): void
    {
        $form = $this->factory->create(ReviewRouteDataType::class);

        $form->submit([
            'queries_improved' => '0',
            'time_improved' => '',
        ]);

        $this->assertTrue($form->isValid());
        $data = $form->getData();
        $this->assertSame('0', $data['queries_improved']);
    }

    public function testFormSubmissionWithTimeImprovedYes(): void
    {
        $form = $this->factory->create(ReviewRouteDataType::class);

        $form->submit([
            'queries_improved' => '',
            'time_improved' => '1',
        ]);

        $this->assertTrue($form->isValid());
        $data = $form->getData();
        $this->assertSame('1', $data['time_improved']);
    }

    public function testFormSubmissionWithTimeImprovedNo(): void
    {
        $form = $this->factory->create(ReviewRouteDataType::class);

        $form->submit([
            'queries_improved' => '',
            'time_improved' => '0',
        ]);

        $this->assertTrue($form->isValid());
        $data = $form->getData();
        $this->assertSame('0', $data['time_improved']);
    }

    public function testFormSubmissionWithBothImproved(): void
    {
        $form = $this->factory->create(ReviewRouteDataType::class);

        $form->submit([
            'queries_improved' => '1',
            'time_improved' => '1',
        ]);

        $this->assertTrue($form->isValid());
        $data = $form->getData();
        $this->assertSame('1', $data['queries_improved']);
        $this->assertSame('1', $data['time_improved']);
    }

    public function testFormSubmissionWithBothNotImproved(): void
    {
        $form = $this->factory->create(ReviewRouteDataType::class);

        $form->submit([
            'queries_improved' => '0',
            'time_improved' => '0',
        ]);

        $this->assertTrue($form->isValid());
        $data = $form->getData();
        $this->assertSame('0', $data['queries_improved']);
        $this->assertSame('0', $data['time_improved']);
    }

    public function testFormSubmissionWithMixedValues(): void
    {
        $form = $this->factory->create(ReviewRouteDataType::class);

        $form->submit([
            'queries_improved' => '1',
            'time_improved' => '0',
        ]);

        $this->assertTrue($form->isValid());
        $data = $form->getData();
        $this->assertSame('1', $data['queries_improved']);
        $this->assertSame('0', $data['time_improved']);
    }

    public function testConfigureOptionsCsrfSettings(): void
    {
        $resolver = $this->createMock(\Symfony\Component\OptionsResolver\OptionsResolver::class);
        $resolver->expects($this->once())
            ->method('setDefaults')
            ->with($this->callback(function ($defaults) {
                return isset($defaults['csrf_protection'])
                    && $defaults['csrf_protection'] === true
                    && isset($defaults['csrf_field_name'])
                    && $defaults['csrf_field_name'] === '_token'
                    && isset($defaults['csrf_token_id'])
                    && $defaults['csrf_token_id'] === 'review_performance_record';
            }));

        $this->formType->configureOptions($resolver);
    }

    public function testConfigureOptionsMethod(): void
    {
        $resolver = $this->createMock(\Symfony\Component\OptionsResolver\OptionsResolver::class);
        $resolver->expects($this->once())
            ->method('setDefaults')
            ->with($this->callback(function ($defaults) {
                return isset($defaults['method']) && $defaults['method'] === 'POST';
            }));

        $this->formType->configureOptions($resolver);
    }
}
