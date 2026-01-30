<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Form;

use Nowo\PerformanceBundle\Form\ReviewRouteDataType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Test\TypeTestCase;

final class ReviewRouteDataTypeTest extends TypeTestCase
{
    private ReviewRouteDataType $formType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formType = new ReviewRouteDataType();
    }

    public function testBuildFormCreatesAllFields(): void
    {
        $form = $this->factory->create(ReviewRouteDataType::class);

        $this->assertTrue($form->has('queries_improved'));
        $this->assertTrue($form->has('time_improved'));
        $this->assertTrue($form->has('submit'));
    }

    public function testBuildFormSetsDefaultValues(): void
    {
        $form = $this->factory->create(ReviewRouteDataType::class);

        $this->assertSame('', $form->get('queries_improved')->getData());
        $this->assertSame('', $form->get('time_improved')->getData());
    }

    public function testConfigureOptionsSetsDefaults(): void
    {
        $resolver = $this->createMock(\Symfony\Component\OptionsResolver\OptionsResolver::class);
        $resolver->expects($this->once())
            ->method('setDefaults')
            ->with($this->callback(function ($defaults) {
                return isset($defaults['method']) 
                    && $defaults['method'] === 'POST'
                    && isset($defaults['csrf_protection'])
                    && $defaults['csrf_protection'] === true;
            }));

        $this->formType->configureOptions($resolver);
    }

    public function testGetBlockPrefixReturnsReviewRouteData(): void
    {
        $this->assertSame('review_route_data', $this->formType->getBlockPrefix());
    }

    public function testFormSubmissionWithValues(): void
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

    public function testFormSubmissionWithEmptyValues(): void
    {
        $form = $this->factory->create(ReviewRouteDataType::class);

        $form->submit([
            'queries_improved' => '',
            'time_improved' => '',
        ]);

        $this->assertTrue($form->isValid());
        $data = $form->getData();
        $this->assertSame('', $data['queries_improved']);
        $this->assertSame('', $data['time_improved']);
    }

    public function testBuildFormWithEnableAccessRecordsAddsSaveAccessRecordsField(): void
    {
        $routeData = new \Nowo\PerformanceBundle\Entity\RouteData();
        $routeData->setSaveAccessRecords(true);

        $form = $this->factory->create(ReviewRouteDataType::class, null, [
            'route_data' => $routeData,
            'enable_access_records' => true,
        ]);

        $this->assertTrue($form->has('save_access_records'));
        $this->assertTrue($form->get('save_access_records')->getData());
    }

    public function testBuildFormWithEnableAccessRecordsAndRouteSaveAccessRecordsFalse(): void
    {
        $routeData = new \Nowo\PerformanceBundle\Entity\RouteData();
        $routeData->setSaveAccessRecords(false);

        $form = $this->factory->create(ReviewRouteDataType::class, null, [
            'route_data' => $routeData,
            'enable_access_records' => true,
        ]);

        $this->assertTrue($form->has('save_access_records'));
        $this->assertFalse($form->get('save_access_records')->getData());
    }

    public function testBuildFormWithoutEnableAccessRecordsDoesNotAddSaveAccessRecordsField(): void
    {
        $form = $this->factory->create(ReviewRouteDataType::class, null, [
            'enable_access_records' => false,
        ]);

        $this->assertFalse($form->has('save_access_records'));
    }

    public function testFormSubmissionWithSaveAccessRecordsWhenEnabled(): void
    {
        $form = $this->factory->create(ReviewRouteDataType::class, null, [
            'enable_access_records' => true,
        ]);

        $form->submit([
            'queries_improved' => '',
            'time_improved' => '',
            'save_access_records' => false,
        ]);

        $this->assertTrue($form->isValid());
        $data = $form->getData();
        $this->assertFalse($data['save_access_records']);
    }

    public function testBuildFormWithRouteDataNullAndEnableAccessRecordsDefaultsSaveAccessRecordsToTrue(): void
    {
        $form = $this->factory->create(ReviewRouteDataType::class, null, [
            'route_data' => null,
            'enable_access_records' => true,
        ]);

        $this->assertTrue($form->has('save_access_records'));
        $this->assertTrue($form->get('save_access_records')->getData());
    }

    public function testFormSubmissionWithQueriesImprovedTrueAndTimeImprovedTrue(): void
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

    public function testFormMethodIsPost(): void
    {
        $form = $this->factory->create(ReviewRouteDataType::class);

        $this->assertSame('POST', $form->getConfig()->getOption('method'));
    }
}
