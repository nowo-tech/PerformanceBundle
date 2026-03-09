<?php

declare(strict_types=1);

/**
 * Stub for ux_icon() when symfony/ux-icons is not installed.
 * Used by IconExtensionTest to cover the branch where ux_icon exists.
 */
if (!function_exists('ux_icon')) {
    function ux_icon(string $name, array $options = []): string
    {
        $attrs = '';
        foreach ($options as $key => $value) {
            if (is_scalar($value)) {
                $attrs .= ' ' . htmlspecialchars((string) $key) . '="' . htmlspecialchars((string) $value) . '"';
            }
        }

        return '<svg data-icon="' . htmlspecialchars($name) . '"' . $attrs . '></svg>';
    }
}
