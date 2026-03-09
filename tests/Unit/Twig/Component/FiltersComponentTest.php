<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Twig\Component;

use Nowo\PerformanceBundle\Twig\Component\FiltersComponent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormView;

final class FiltersComponentTest extends TestCase
{
    public function testComponentHasDefaultValues(): void
    {
        $component = new FiltersComponent();

        $this->assertSame('bootstrap', $component->template);
    }

    public function testComponentCanSetProperties(): void
    {
        $component = new FiltersComponent();
        $formView  = $this->createMock(FormView::class);

        $component->form     = $formView;
        $component->template = 'tailwind';

        $this->assertSame($formView, $component->form);
        $this->assertSame('tailwind', $component->template);
    }

    public function testTemplateDefaultIsBootstrap(): void
    {
        $component       = new FiltersComponent();
        $component->form = $this->createMock(FormView::class);

        $this->assertSame('bootstrap', $component->template);
    }

    public function testFormPropertyAcceptsMockFormView(): void
    {
        $component       = new FiltersComponent();
        $formView        = $this->createMock(FormView::class);
        $component->form = $formView;

        $this->assertSame($formView, $component->form);
    }
}
