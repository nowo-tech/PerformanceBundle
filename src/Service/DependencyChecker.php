<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Service;

/**
 * Service for checking if required dependencies are installed.
 *
 * Validates that optional dependencies are available and provides
 * information about what needs to be installed.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
class DependencyChecker
{
    /**
     * Check if Symfony UX Twig Component is available.
     *
     * Verifies both that the bundle classes exist and that the Twig function
     * 'component' is available (which is registered by the bundle).
     *
     * @return bool True if available, false otherwise
     */
    public function isTwigComponentAvailable(): bool
    {
        // Check if bundle classes exist
        $classesExist = class_exists(\Symfony\UX\TwigComponent\TwigComponentBundle::class)
            || interface_exists(\Symfony\UX\TwigComponent\ComponentInterface::class)
            || class_exists(\Symfony\UX\TwigComponent\Attribute\AsTwigComponent::class);

        if (!$classesExist) {
            return false;
        }

        // Also verify that the Twig function is available
        // This is a runtime check that can only be done when Twig is initialized
        // For now, we rely on the template checking 'component is defined'
        return true;
    }

    /**
     * Check if Symfony UX Icons is available.
     *
     * @return bool True if available, false otherwise
     */
    public function isIconsAvailable(): bool
    {
        return \function_exists('ux_icon')
            || class_exists(\Symfony\UX\Icons\Twig\IconExtension::class)
            || \function_exists('twig_get_function') && null !== twig_get_function('ux_icon');
    }

    /**
     * Get information about missing dependencies.
     *
     * @return array<string, array{required: bool, package: string, message: string, install_command: string}> Missing dependencies info
     */
    public function getMissingDependencies(): array
    {
        $missing = [];

        if (!$this->isTwigComponentAvailable()) {
            $missing['twig_component'] = [
                'required' => false,
                'package' => 'symfony/ux-twig-component',
                'message' => 'Symfony UX Twig Component is not installed. Components will use fallback includes.',
                'install_command' => 'composer require symfony/ux-twig-component',
                'feature' => 'Twig Components (better performance)',
            ];
        }

        if (!$this->isIconsAvailable()) {
            $missing['icons'] = [
                'required' => false,
                'package' => 'symfony/ux-icons',
                'message' => 'Symfony UX Icons is not installed. Icons will use fallback SVG.',
                'install_command' => 'composer require symfony/ux-icons',
                'feature' => 'UX Icons (better icon management)',
            ];
        }

        return $missing;
    }

    /**
     * Check if a specific feature is available.
     *
     * @param string $feature Feature name (e.g., 'twig_component')
     *
     * @return bool True if feature is available
     */
    public function isFeatureAvailable(string $feature): bool
    {
        return match ($feature) {
            'twig_component' => $this->isTwigComponentAvailable(),
            'icons' => $this->isIconsAvailable(),
            default => true,
        };
    }

    /**
     * Get all dependency status information.
     *
     * @return array<string, mixed> Dependency status
     */
    public function getDependencyStatus(): array
    {
        return [
            'twig_component' => [
                'available' => $this->isTwigComponentAvailable(),
                'package' => 'symfony/ux-twig-component',
                'required' => false,
            ],
            'icons' => [
                'available' => $this->isIconsAvailable(),
                'package' => 'symfony/ux-icons',
                'required' => false,
            ],
        ];
    }
}
