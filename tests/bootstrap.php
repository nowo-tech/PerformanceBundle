<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap for Performance Bundle tests.
 *
 * The deprecated LogHelper (unused by the bundle services) skips error_log() when the host defines
 * NOWO_PERFORMANCE_SUPPRESS_LOGS_IN_TESTS=true; LogHelperTest covers both cases, so the constant is no longer defined
 * here (it would make the error_log() path unreachable in every test process).
 */
require __DIR__ . '/../vendor/autoload.php';
