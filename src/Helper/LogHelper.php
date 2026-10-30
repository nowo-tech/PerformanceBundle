<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Helper;

use function constant;
use function defined;
use function error_log;
use function sprintf;

/**
 * Helper class for logging in the Performance Bundle.
 *
 * @deprecated since 3.8, the bundle services log through an injected Psr\Log\LoggerInterface (REQ-OBS-001); this
 *             error_log() helper is no longer used internally and will be removed in 4.0.
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
     * Log a message if logging is enabled (skipped, but reported as logged, when the host defines
     * NOWO_PERFORMANCE_SUPPRESS_LOGS_IN_TESTS=true).
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

        if (!defined('NOWO_PERFORMANCE_SUPPRESS_LOGS_IN_TESTS') || !constant('NOWO_PERFORMANCE_SUPPRESS_LOGS_IN_TESTS')) {
            error_log($message);
        }

        return true;
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

        return self::log(sprintf($format, ...$args), true);
    }
}
