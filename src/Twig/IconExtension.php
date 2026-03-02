<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

use function function_exists;

/**
 * Twig extension for icon rendering using Symfony UX Icons.
 *
 * Provides a thin wrapper around ux_icon() for backwards compatibility.
 * Icons are rendered via symfony/ux-icons (required dependency).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class IconExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('performance_icon', [$this, 'renderIcon'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * Render an icon using UX Icons (ux_icon).
     *
     * @param string $name Icon name with optional prefix (e.g. "bi:gear", "heroicons:check")
     * @param array<string, mixed> $options Attributes for the SVG (class, style, etc.)
     *
     * @return string Rendered icon HTML
     */
    public function renderIcon(string $name, array $options = []): string
    {
        if (!function_exists('ux_icon')) {
            return '';
        }

        /** @var callable $uxIcon */
        $uxIcon = 'ux_icon';

        return (string) $uxIcon($name, $options);
    }
}
