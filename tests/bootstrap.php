<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap for Performance Bundle tests.
 *
 * Suppresses [PerformanceBundle] logs (error_log) during test runs to reduce noise.
 * LogHelper checks NOWO_PERFORMANCE_SUPPRESS_LOGS_IN_TESTS and skips error_log when set.
 */
if (!defined('NOWO_PERFORMANCE_SUPPRESS_LOGS_IN_TESTS')) {
    define('NOWO_PERFORMANCE_SUPPRESS_LOGS_IN_TESTS', true);
}

require dirname(__DIR__) . '/vendor/autoload.php';
