<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Helper;

use function constant;
use function defined;
use function function_exists;
use function is_callable;
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
     * Test-only: when set to false, act as if NOWO_PERFORMANCE_SUPPRESS_LOGS_IN_TESTS is not set.
     * Leave null in production.
     */
    public static ?bool $testSuppressOverride = null;

    /**
     * Test-only: when set, log/logf call this instead of error_log (avoids stderr in PHPUnit).
     * Leave null in production.
     *
     * @var callable(string): void|null
     */
    public static $testLogWriter;

    /**
     * Test-only: when set to false, log/logf behave as if error_log() was not defined (return false). Leave null in production.
     */
    public static ?bool $testFunctionErrorLogExistsOverride = null;

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

        $suppress = self::$testSuppressOverride ?? defined('NOWO_PERFORMANCE_SUPPRESS_LOGS_IN_TESTS') && constant('NOWO_PERFORMANCE_SUPPRESS_LOGS_IN_TESTS');
        if ($suppress) {
            return true;
        }

        if (self::$testLogWriter !== null && is_callable(self::$testLogWriter)) {
            (self::$testLogWriter)($message);

            return true;
        }

        $override       = self::$testFunctionErrorLogExistsOverride;
        $errorLogExists = $override ?? function_exists('error_log');
        if (!$errorLogExists) {
            return false;
        }
        error_log($message);

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

        $suppress = self::$testSuppressOverride ?? defined('NOWO_PERFORMANCE_SUPPRESS_LOGS_IN_TESTS') && constant('NOWO_PERFORMANCE_SUPPRESS_LOGS_IN_TESTS');
        if ($suppress) {
            return true;
        }

        $message = sprintf($format, ...$args);
        if (self::$testLogWriter !== null && is_callable(self::$testLogWriter)) {
            (self::$testLogWriter)($message);

            return true;
        }

        $override       = self::$testFunctionErrorLogExistsOverride;
        $errorLogExists = $override ?? function_exists('error_log');
        if (!$errorLogExists) {
            return false;
        }
        error_log($message);

        return true;
    }
}
