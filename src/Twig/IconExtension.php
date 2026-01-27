<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Twig;

use Nowo\PerformanceBundle\Service\DependencyChecker;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension for icon rendering with fallback support.
 *
 * Provides a helper function to render icons using Symfony UX Icons
 * with automatic fallback to SVG if UX Icons is not available.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
class IconExtension extends AbstractExtension
{
    public function __construct(
        private readonly DependencyChecker $dependencyChecker
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('performance_icon', [$this, 'renderIcon'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * Render an icon using UX Icons or fallback SVG.
     *
     * @param string $name Icon name (for UX Icons) or 'svg' for custom SVG
     * @param array<string, mixed> $options Options for the icon (class, size, etc.)
     * @param string|null $fallbackSvg Fallback SVG code if UX Icons is not available
     * @return string Rendered icon HTML
     */
    public function renderIcon(string $name, array $options = [], ?string $fallbackSvg = null): string
    {
        // If UX Icons is available, use it
        if ($this->dependencyChecker->isIconsAvailable() && function_exists('ux_icon')) {
            try {
                // ux_icon is a global function provided by Symfony UX Icons
                /** @var callable $uxIconFunction */
                $uxIconFunction = 'ux_icon';
                return (string) $uxIconFunction($name, $options);
            } catch (\Throwable $e) {
                // If icon doesn't exist in UX Icons, fall back to SVG
            }
        }

        // Otherwise, use fallback SVG if provided
        if ($fallbackSvg !== null) {
            // If fallback SVG already contains the SVG tag, return it directly
            if (str_contains($fallbackSvg, '<svg')) {
                return $fallbackSvg;
            }
            
            // Otherwise, wrap it in a span
            $class = $options['class'] ?? '';
            $style = $options['style'] ?? '';
            
            return sprintf(
                '<span class="%s" style="%s">%s</span>',
                $class,
                $style,
                $fallbackSvg
            );
        }

        // No icon available
        return '';
    }
}
