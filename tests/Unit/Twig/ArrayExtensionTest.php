<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Twig;

use Nowo\PerformanceBundle\Twig\ArrayExtension;
use PHPUnit\Framework\TestCase;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class ArrayExtensionTest extends TestCase
{
    private ArrayExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new ArrayExtension();
    }

    public function testGetFiltersReturnsSumFilter(): void
    {
        $filters = $this->extension->getFilters();

        $this->assertIsArray($filters);
        $this->assertCount(1, $filters);
        $this->assertInstanceOf(TwigFilter::class, $filters[0]);
        $this->assertSame('sum', $filters[0]->getName());
    }

    public function testSumWithIntegers(): void
    {
        $this->assertSame(10, $this->extension->sum([1, 2, 3, 4]));
        $this->assertSame(0, $this->extension->sum([]));
        $this->assertSame(5, $this->extension->sum([5]));
    }

    public function testSumWithFloats(): void
    {
        $this->assertEqualsWithDelta(6.6, $this->extension->sum([1.1, 2.2, 3.3]), 0.001);
        $this->assertEqualsWithDelta(0.0, $this->extension->sum([]), 0.001);
    }

    public function testSumWithMixedIntAndFloat(): void
    {
        $this->assertEqualsWithDelta(7.5, $this->extension->sum([1, 2.5, 4]), 0.001);
    }

    public function testSumWithAssociativeArray(): void
    {
        $this->assertSame(6, $this->extension->sum(['a' => 1, 'b' => 2, 'c' => 3]));
    }

    public function testExtendsAbstractExtension(): void
    {
        $this->assertInstanceOf(AbstractExtension::class, $this->extension);
    }

    public function testSumWithSingleElement(): void
    {
        $this->assertSame(42, $this->extension->sum([42]));
        $this->assertEqualsWithDelta(3.14, $this->extension->sum([3.14]), 0.001);
    }

    public function testSumWithTwoElements(): void
    {
        $this->assertSame(3, $this->extension->sum([1, 2]));
        $this->assertEqualsWithDelta(5.5, $this->extension->sum([2.5, 3.0]), 0.001);
    }

    public function testSumWithNegativeNumbers(): void
    {
        $this->assertSame(-6, $this->extension->sum([-1, -2, -3]));
        $this->assertSame(0, $this->extension->sum([5, -5]));
    }

    public function testSumWithAllZeros(): void
    {
        $this->assertSame(0, $this->extension->sum([0, 0, 0]));
    }

    public function testSumWithSingleZero(): void
    {
        $this->assertSame(0, $this->extension->sum([0]));
    }

    public function testSumWithManyElements(): void
    {
        $values = array_fill(0, 100, 1);
        $this->assertSame(100, $this->extension->sum($values));
    }
}
