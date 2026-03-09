<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Integration\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

use function is_scalar;

/**
 * Stub Twig extension for integration tests when Symfony UX packages are not installed.
 * Provides ux_icon() (symfony/ux-icons) and component() (symfony/ux-twig-component) so
 * templates can render without the real UX packages.
 */
final class UxIconStubExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('ux_icon', $this->renderIcon(...), ['is_safe' => ['html']]),
            new TwigFunction('component', $this->renderComponent(...), ['is_safe' => ['html']]),
        ];
    }

    /**
     * @param array<string, mixed> $options
     */
    public function renderIcon(string $name, array $options = []): string
    {
        $attrs = '';
        foreach ($options as $key => $value) {
            if (is_scalar($value)) {
                $attrs .= ' ' . htmlspecialchars((string) $key) . '="' . htmlspecialchars((string) $value) . '"';
            }
        }

        return '<svg data-icon="' . htmlspecialchars($name) . '"' . $attrs . '></svg>';
    }

    /**
     * Stub for component() - returns empty string so integration tests can render the page.
     *
     * @param array<string, mixed> $props
     */
    public function renderComponent(string $name, array $props = []): string
    {
        return '<!-- component stub: ' . htmlspecialchars($name) . ' -->';
    }
}
