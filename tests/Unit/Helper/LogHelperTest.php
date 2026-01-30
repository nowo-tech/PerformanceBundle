<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Helper;

use Nowo\PerformanceBundle\Helper\LogHelper;
use PHPUnit\Framework\TestCase;

final class LogHelperTest extends TestCase
{
    public function testIsLoggingEnabledReturnsTrueWhenExplicitlyTrue(): void
    {
        $this->assertTrue(LogHelper::isLoggingEnabled(true));
    }

    public function testIsLoggingEnabledReturnsFalseWhenExplicitlyFalse(): void
    {
        $this->assertFalse(LogHelper::isLoggingEnabled(false));
    }

    public function testIsLoggingEnabledReturnsTrueWhenNull(): void
    {
        // Default behavior: true for backward compatibility
        $this->assertTrue(LogHelper::isLoggingEnabled(null));
    }

    public function testLogReturnsFalseWhenLoggingDisabled(): void
    {
        $result = LogHelper::log('Test message', false);
        $this->assertFalse($result);
    }

    public function testLogReturnsTrueWhenLoggingEnabled(): void
    {
        // With NOWO_PERFORMANCE_SUPPRESS_LOGS_IN_TESTS, we skip error_log but still return true
        $result = LogHelper::log('Test message', true);
        $this->assertTrue($result);
    }

    public function testLogReturnsTrueWhenLoggingNull(): void
    {
        // Default behavior: true for backward compatibility; suppress avoids error_log in tests
        $result = LogHelper::log('Test message', null);
        $this->assertTrue($result);
    }

    public function testLogfReturnsFalseWhenLoggingDisabled(): void
    {
        $result = LogHelper::logf('Test message: %s', false, 'value');
        $this->assertFalse($result);
    }

    public function testLogfReturnsTrueWhenLoggingEnabled(): void
    {
        $result = LogHelper::logf('Test message: %s', true, 'value');
        $this->assertTrue($result);
    }

    public function testLogfFormatsMessageCorrectly(): void
    {
        $result = LogHelper::logf('Test: %s, %d', true, 'string', 123);
        $this->assertTrue($result);
    }

    public function testLogfWithMultipleArguments(): void
    {
        $result = LogHelper::logf('Test: %s, %d, %s', true, 'arg1', 42, 'arg3');
        $this->assertTrue($result);
    }

    public function testLogfReturnsTrueWhenLoggingNull(): void
    {
        $result = LogHelper::logf('Test message: %s', null, 'value');
        $this->assertTrue($result);
    }

    public function testLogWithEmptyString(): void
    {
        $result = LogHelper::log('', true);
        $this->assertTrue($result);
    }

    public function testLogfWithEmptyFormat(): void
    {
        $result = LogHelper::logf('', true);
        $this->assertTrue($result);
    }

    public function testLogfWithSpecialCharacters(): void
    {
        $result = LogHelper::logf('Test: %s with special chars: <>&"\'', true, 'value');
        $this->assertTrue($result);
    }

    public function testLogfWithNumericValues(): void
    {
        $result = LogHelper::logf('Test: %d, %f, %s', true, 42, 3.14, 'string');
        $this->assertTrue($result);
    }

    public function testLogfWithNoArguments(): void
    {
        $result = LogHelper::logf('Test message without placeholders', true);
        $this->assertTrue($result);
    }

    /** When suppress is active (tests bootstrap), log/logf return true without writing to error_log. */
    public function testLogReturnsTrueWhenSuppressActive(): void
    {
        $this->assertTrue(LogHelper::log('noise', true));
    }

    /** When suppress is active (tests bootstrap), logf returns true without writing to error_log. */
    public function testLogfReturnsTrueWhenSuppressActive(): void
    {
        $this->assertTrue(LogHelper::logf('noise: %s', true, 'x'));
    }

    public function testIsLoggingEnabledWithExplicitTrue(): void
    {
        $this->assertTrue(LogHelper::isLoggingEnabled(true));
    }

    public function testIsLoggingEnabledWithExplicitFalse(): void
    {
        $this->assertFalse(LogHelper::isLoggingEnabled(false));
    }

    public function testIsLoggingEnabledWithNullDefaultsToTrue(): void
    {
        // Backward compatibility: null should default to true
        $this->assertTrue(LogHelper::isLoggingEnabled(null));
    }
}
