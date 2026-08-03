<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Helper;

use Nowo\PerformanceBundle\Helper\LogHelper;
use PHPUnit\Framework\TestCase;

final class LogHelperTest extends TestCase
{
    public function testIsLoggingEnabledWithExplicitTrue(): void
    {
        $this->assertTrue(LogHelper::isLoggingEnabled(true));
    }

    public function testIsLoggingEnabledWithExplicitFalse(): void
    {
        $this->assertFalse(LogHelper::isLoggingEnabled(false));
    }

    public function testIsLoggingEnabledDefaultsToTrue(): void
    {
        $this->assertTrue(LogHelper::isLoggingEnabled());
    }

    public function testLogReturnsFalseWhenDisabled(): void
    {
        $this->assertFalse(LogHelper::log('msg', false));
    }

    public function testLogfReturnsFalseWhenDisabled(): void
    {
        $this->assertFalse(LogHelper::logf('%s', false, 'x'));
    }

    public function testLogSucceedsWhenEnabledUnderTestSuppressConstant(): void
    {
        // tests/bootstrap.php defines NOWO_PERFORMANCE_SUPPRESS_LOGS_IN_TESTS
        $this->assertTrue(LogHelper::log('msg', true));
        $this->assertTrue(LogHelper::logf('hello %s', true, 'world'));
    }
}
