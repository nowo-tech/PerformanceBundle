<?php

declare(strict_types=1);

/**
 * Polyfill for Symfony Messenger AsMessageHandler attribute.
 *
 * This polyfill provides a no-op implementation of AsMessageHandler
 * when Symfony Messenger is not installed, allowing the bundle to work
 * without Messenger as an optional dependency.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */

// Only define the polyfill if Messenger is not available
if (!class_exists('Symfony\Component\Messenger\Attribute\AsMessageHandler', false)) {
    // Use eval to define the namespace and class
    // The polyfill accepts the same parameters as the real AsMessageHandler
    eval('
        namespace Symfony\Component\Messenger\Attribute {
            #[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
            class AsMessageHandler
            {
                public function __construct(
                    public ?string $fromTransport = null,
                    public ?string $handles = null,
                    public ?int $priority = null,
                    public ?string $method = null
                ) {
                }
            }
        }
    ');
}
