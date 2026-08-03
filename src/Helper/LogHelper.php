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
     * @param bool|null $enableLogging The logging configuration value (from container parameter)
     *
     * @return bool True if logging is enabled, false otherwise
     */
    public static function isLoggingEnabled(?bool $enableLogging = null): bool
    {
        if ($enableLogging !== null) {
            return $enableLogging;
        }

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

        if (defined('NOWO_PERFORMANCE_SUPPRESS_LOGS_IN_TESTS') && constant('NOWO_PERFORMANCE_SUPPRESS_LOGS_IN_TESTS')) {
            return true;
        }

        // @codeCoverageIgnoreStart
        // error_log() always exists in PHP; this guard is defensive dead code.
        if (!function_exists('error_log')) {
            return false;
        }

        // The lines below are only reachable in production (outside the test suite).
        // Tests/bootstrap.php defines NOWO_PERFORMANCE_SUPPRESS_LOGS_IN_TESTS=true,
        // so the early-return above always fires during test runs.
        error_log($message);

        return true;
        // @codeCoverageIgnoreEnd
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

        if (defined('NOWO_PERFORMANCE_SUPPRESS_LOGS_IN_TESTS') && constant('NOWO_PERFORMANCE_SUPPRESS_LOGS_IN_TESTS')) {
            return true;
        }

        $message = sprintf($format, ...$args);
        // @codeCoverageIgnoreStart
        // Same reasoning as log(): error_log() is always defined; actual call is masked by the
        // test-bootstrap constant above in every test process.
        if (!function_exists('error_log')) {
            return false;
        }

        error_log($message);

        return true;
        // @codeCoverageIgnoreEnd
    }
}
