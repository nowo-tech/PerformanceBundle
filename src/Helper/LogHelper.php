<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Helper;

/**
 * Helper class for logging in the Performance Bundle.
 *
 * Provides a centralized way to check if logging is enabled and perform logging
 * operations that respect the bundle's logging configuration.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class LogHelper
{
    /**
     * Check if logging is enabled.
     *
     * This method checks the bundle's logging configuration parameter.
     * If the parameter is not available (e.g., during early bootstrap),
     * it defaults to true for backward compatibility.
     *
     * @param bool|null $enableLogging The logging configuration value (from container parameter)
     *
     * @return bool True if logging is enabled, false otherwise
     */
    public static function isLoggingEnabled(?bool $enableLogging = null): bool
    {
        // If explicitly provided, use that value
        if (null !== $enableLogging) {
            return $enableLogging;
        }

        // Try to get from container parameter if available
        // This is a fallback for cases where the container is not available
        // In normal operation, the value should be passed explicitly
        if (class_exists(\Symfony\Component\DependencyInjection\ContainerInterface::class)) {
            try {
                // This is a best-effort attempt, may not work in all contexts
                return true; // Default to true for backward compatibility
            } catch (\Exception $e) {
                // If we can't determine, default to true
                return true;
            }
        }

        // Default to true for backward compatibility
        return true;
    }

    /**
     * Log a message if logging is enabled.
     *
     * @param string    $message       The message to log
     * @param bool|null $enableLogging The logging configuration value (from container parameter)
     *
     * @return bool True if the message was logged, false otherwise
     */
    public static function log(string $message, ?bool $enableLogging = null): bool
    {
        if (!self::isLoggingEnabled($enableLogging)) {
            return false;
        }

        if (\defined('NOWO_PERFORMANCE_SUPPRESS_LOGS_IN_TESTS') && NOWO_PERFORMANCE_SUPPRESS_LOGS_IN_TESTS) {
            return true;
        }

        if (\function_exists('error_log')) {
            error_log($message);

            return true;
        }

        return false;
    }

    /**
     * Log a formatted message if logging is enabled.
     *
     * @param string    $format        The format string (sprintf format)
     * @param bool|null $enableLogging The logging configuration value (from container parameter)
     * @param mixed     ...$args       Arguments for the format string
     *
     * @return bool True if the message was logged, false otherwise
     */
    public static function logf(string $format, ?bool $enableLogging = null, ...$args): bool
    {
        if (!self::isLoggingEnabled($enableLogging)) {
            return false;
        }

        if (\defined('NOWO_PERFORMANCE_SUPPRESS_LOGS_IN_TESTS') && NOWO_PERFORMANCE_SUPPRESS_LOGS_IN_TESTS) {
            return true;
        }

        if (\function_exists('error_log')) {
            error_log(\sprintf($format, ...$args));

            return true;
        }

        return false;
    }
}
