<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Form;

use Nowo\PerformanceBundle\Form\ChartFiltersType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Forms;

final class ChartFiltersTypeTest extends TestCase
{
    public function testKeepsIdsAndDefaultsReadByChartScript(): void
    {
        $form = Forms::createFormFactory()->createNamed('', ChartFiltersType::class);
        $view = $form->createView();

        $this->assertSame('', (new ChartFiltersType())->getBlockPrefix());
        $this->assertSame('chart-metric', $view->children['metric']->vars['id']);
        $this->assertSame('chart-days', $view->children['days']->vars['id']);
        $this->assertSame('requestTime', $view->children['metric']->vars['value']);
        $this->assertSame('7', $view->children['days']->vars['value']);
        $this->assertCount(4, $view->children['metric']->vars['choices']);
        $this->assertCount(3, $view->children['days']->vars['choices']);
        $this->assertSame('GET', $view->vars['method']);
        $this->assertSame('return false;', $view->vars['attr']['onsubmit']);
        $this->assertFalse($form->get('metric')->getConfig()->getMapped());
        $this->assertFalse($form->getConfig()->getOption('csrf_protection'));
    }
}
