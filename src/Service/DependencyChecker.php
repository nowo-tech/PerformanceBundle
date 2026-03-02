<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Service;

use function function_exists;

/**
 * Service for checking if required dependencies are installed.
 *
 * Validates that optional dependencies are available and provides
 * information about what needs to be installed.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
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

        return !(!$classesExist)

        // Also verify that the Twig function is available
        // This is a runtime check that can only be done when Twig is initialized
        // For now, we rely on the template checking 'component is defined'
        ;
    }

    /**
     * Check if Symfony UX Icons is available.
     *
     * @return bool True if available, false otherwise
     */
    public function isIconsAvailable(): bool
    {
        return function_exists('ux_icon')
            || class_exists(\Symfony\UX\Icons\Twig\IconExtension::class)
            || function_exists('twig_get_function') && twig_get_function('ux_icon') !== null;
    }

    /**
     * Check if Symfony Messenger is available.
     *
     * @return bool True if available, false otherwise
     */
    public function isMessengerAvailable(): bool
    {
        return interface_exists(\Symfony\Component\Messenger\MessageBusInterface::class)
            || class_exists(\Symfony\Component\Messenger\MessageBusInterface::class);
    }

    /**
     * Check if Symfony Mailer is available.
     *
     * @return bool True if available, false otherwise
     */
    public function isMailerAvailable(): bool
    {
        return interface_exists(\Symfony\Component\Mailer\MailerInterface::class)
            || class_exists(\Symfony\Component\Mailer\MailerInterface::class);
    }

    /**
     * Check if Symfony HttpClient is available.
     *
     * @return bool True if available, false otherwise
     */
    public function isHttpClientAvailable(): bool
    {
        return interface_exists(\Symfony\Contracts\HttpClient\HttpClientInterface::class)
            || class_exists(\Symfony\Contracts\HttpClient\HttpClientInterface::class);
    }

    /**
     * Get information about missing dependencies.
     *
     * @return array<string, array{required: bool, package: string, message: string, install_command: string, feature: string}> Missing dependencies info
     */
    public function getMissingDependencies(): array
    {
        $missing = [];

        if (!$this->isTwigComponentAvailable()) {
            $missing['twig_component'] = [
                'required'        => false,
                'package'         => 'symfony/ux-twig-component',
                'message'         => 'Symfony UX Twig Component is not installed. Components will use fallback includes.',
                'install_command' => 'composer require symfony/ux-twig-component',
                'feature'         => 'Twig Components (better performance)',
            ];
        }

        if (!$this->isIconsAvailable()) {
            $missing['icons'] = [
                'required'        => true,
                'package'         => 'symfony/ux-icons',
                'message'         => 'Symfony UX Icons is required. Install it to render icons in the Performance bundle.',
                'install_command' => 'composer require symfony/ux-icons',
                'feature'         => 'UX Icons (required for icon rendering)',
            ];
        }

        if (!$this->isMessengerAvailable()) {
            $missing['messenger'] = [
                'required'        => false,
                'package'         => 'symfony/messenger',
                'message'         => 'Symfony Messenger is not installed. Async metrics recording is not available.',
                'install_command' => 'composer require symfony/messenger',
                'feature'         => 'Async metrics recording',
            ];
        }

        if (!$this->isMailerAvailable()) {
            $missing['mailer'] = [
                'required'        => false,
                'package'         => 'symfony/mailer',
                'message'         => 'Symfony Mailer is not installed. Email notifications are not available.',
                'install_command' => 'composer require symfony/mailer',
                'feature'         => 'Email notifications',
            ];
        }

        if (!$this->isHttpClientAvailable()) {
            $missing['http_client'] = [
                'required'        => false,
                'package'         => 'symfony/http-client',
                'message'         => 'Symfony HttpClient is not installed. Slack, Teams, and webhook notifications are not available.',
                'install_command' => 'composer require symfony/http-client',
                'feature'         => 'Slack, Teams, and webhook notifications',
            ];
        }

        return $missing;
    }

    /**
     * Check if a specific feature is available.
     *
     * @param string $feature Feature name (e.g., 'twig_component', 'messenger', 'mailer', 'http_client')
     *
     * @return bool True if feature is available
     */
    public function isFeatureAvailable(string $feature): bool
    {
        return match ($feature) {
            'twig_component' => $this->isTwigComponentAvailable(),
            'icons'          => $this->isIconsAvailable(),
            'messenger'      => $this->isMessengerAvailable(),
            'mailer'         => $this->isMailerAvailable(),
            'http_client'    => $this->isHttpClientAvailable(),
            default          => true,
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
                'package'   => 'symfony/ux-twig-component',
                'required'  => false,
            ],
            'icons' => [
                'available' => $this->isIconsAvailable(),
                'package'   => 'symfony/ux-icons',
                'required'  => true,
            ],
            'messenger' => [
                'available' => $this->isMessengerAvailable(),
                'package'   => 'symfony/messenger',
                'required'  => false,
            ],
            'mailer' => [
                'available' => $this->isMailerAvailable(),
                'package'   => 'symfony/mailer',
                'required'  => false,
            ],
            'http_client' => [
                'available' => $this->isHttpClientAvailable(),
                'package'   => 'symfony/http-client',
                'required'  => false,
            ],
        ];
    }
}
