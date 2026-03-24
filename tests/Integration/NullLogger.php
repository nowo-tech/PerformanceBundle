<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Integration;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

/**
 * Logger that discards all messages.
 * Used in integration tests to avoid "[error] Uncaught PHP Exception" output
 * when tests intentionally trigger 404 or other HTTP exceptions.
 */
final class NullLogger extends AbstractLogger implements LoggerInterface
{
    public function log($level, $message, array $context = []): void
    {
        // No-op: do not log to console during tests
    }
}
