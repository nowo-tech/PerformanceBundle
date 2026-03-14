<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Helper;

use function constant;
use function defined;
use function function_exists;
use function sprintf;

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
        if ($enableLogging !== null) {
            return $enableLogging;
        }

        // Default to true for backward compatibility when parameter is not passed
        return true;
    }

    /**
     * Log a message if logging is enabled.
     *
     * @param string $message The message to log
     * @param bool|null $enableLogging The logging configuration value (from container parameter)
     *
     * @return bool True if the message was logged, false otherwise
     */
    public static function log(string $message, ?bool $enableLogging = null): bool
    {
        if (!self::isLoggingEnabled($enableLogging)) {
            return false;
        }

        $suppress = defined('NOWO_PERFORMANCE_SUPPRESS_LOGS_IN_TESTS') && constant('NOWO_PERFORMANCE_SUPPRESS_LOGS_IN_TESTS');
        if ($suppress) {
            return true;
        }

        if (function_exists('error_log')) {
            error_log($message);

            return true;
        }

        return false;
    }

    /**
     * Log a formatted message if logging is enabled.
     *
     * @param string $format The format string (sprintf format)
     * @param bool|null $enableLogging The logging configuration value (from container parameter)
     * @param mixed ...$args Arguments for the format string
     *
     * @return bool True if the message was logged, false otherwise
     */
    public static function logf(string $format, ?bool $enableLogging = null, ...$args): bool
    {
        if (!self::isLoggingEnabled($enableLogging)) {
            return false;
        }

        $suppress = defined('NOWO_PERFORMANCE_SUPPRESS_LOGS_IN_TESTS') && constant('NOWO_PERFORMANCE_SUPPRESS_LOGS_IN_TESTS');
        if ($suppress) {
            return true;
        }

        if (function_exists('error_log')) {
            error_log(sprintf($format, ...$args));

            return true;
        }

        return false;
    }
}
