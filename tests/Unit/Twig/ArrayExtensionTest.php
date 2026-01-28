<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Twig;

use Nowo\PerformanceBundle\Twig\ArrayExtension;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ArrayExtension.
 */
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

        $this->assertCount(1, $filters);
        $this->assertSame('sum', $filters[0]->getName());
    }

    public function testSumWithEmptyArray(): void
    {
        $result = $this->extension->sum([]);

        $this->assertSame(0, $result);
    }

    public function testSumWithIntegers(): void
    {
        $result = $this->extension->sum([1, 2, 3, 4, 5]);

        $this->assertSame(15, $result);
    }

    public function testSumWithFloats(): void
    {
        $result = $this->extension->sum([1.5, 2.5, 3.5]);

        $this->assertSame(7.5, $result);
    }

    public function testSumWithMixedIntegersAndFloats(): void
    {
        $result = $this->extension->sum([1, 2.5, 3, 4.5]);

        $this->assertSame(11.0, $result);
    }

    public function testSumWithNegativeNumbers(): void
    {
        $result = $this->extension->sum([-1, -2, -3]);

        $this->assertSame(-6, $result);
    }

    public function testSumWithMixedPositiveAndNegative(): void
    {
        $result = $this->extension->sum([1, -2, 3, -4, 5]);

        $this->assertSame(3, $result);
    }

    public function testSumWithZeros(): void
    {
        $result = $this->extension->sum([0, 0, 0]);

        $this->assertSame(0, $result);
    }

    public function testSumWithSingleValue(): void
    {
        $result = $this->extension->sum([42]);

        $this->assertSame(42, $result);
    }

    public function testSumWithLargeNumbers(): void
    {
        $result = $this->extension->sum([1000000, 2000000, 3000000]);

        $this->assertSame(6000000, $result);
    }

    public function testSumWithDecimalPrecision(): void
    {
        $result = $this->extension->sum([0.1, 0.2, 0.3]);

        // Should be approximately 0.6 (may have floating point precision issues)
        $this->assertGreaterThanOrEqual(0.59, $result);
        $this->assertLessThanOrEqual(0.61, $result);
    }

    public function testSumWithManyValues(): void
    {
        $values = range(1, 100);
        $result = $this->extension->sum($values);

        // Sum of 1 to 100 = 5050
        $this->assertSame(5050, $result);
    }
}
