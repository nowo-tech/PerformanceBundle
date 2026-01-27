<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Twig extension for array operations.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
class ArrayExtension extends AbstractExtension
{
    /**
     * Get Twig filters.
     *
     * @return array<TwigFilter>
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('sum', [$this, 'sum']),
        ];
    }

    /**
     * Sum all values in an array.
     *
     * @param array<int|float> $array The array to sum
     * @return int|float The sum of all values
     */
    public function sum(array $array): int|float
    {
        return array_sum($array);
    }
}
