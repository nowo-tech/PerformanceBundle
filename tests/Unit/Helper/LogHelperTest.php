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
        // Note: This test may actually log to error_log, but that's acceptable for testing
        $result = LogHelper::log('Test message', true);
        // If error_log function exists, it should return true
        if (\function_exists('error_log')) {
            $this->assertTrue($result);
        } else {
            $this->assertFalse($result);
        }
    }

    public function testLogReturnsTrueWhenLoggingNull(): void
    {
        // Default behavior: true for backward compatibility
        $result = LogHelper::log('Test message', null);
        if (\function_exists('error_log')) {
            $this->assertTrue($result);
        } else {
            $this->assertFalse($result);
        }
    }

    public function testLogfReturnsFalseWhenLoggingDisabled(): void
    {
        $result = LogHelper::logf('Test message: %s', false, 'value');
        $this->assertFalse($result);
    }

    public function testLogfReturnsTrueWhenLoggingEnabled(): void
    {
        $result = LogHelper::logf('Test message: %s', true, 'value');
        if (\function_exists('error_log')) {
            $this->assertTrue($result);
        } else {
            $this->assertFalse($result);
        }
    }

    public function testLogfFormatsMessageCorrectly(): void
    {
        // This test verifies that logf uses sprintf correctly
        // We can't easily test the actual output, but we can verify it doesn't throw
        $result = LogHelper::logf('Test: %s, %d', true, 'string', 123);
        if (\function_exists('error_log')) {
            $this->assertTrue($result);
        } else {
            $this->assertFalse($result);
        }
    }

    public function testLogfWithMultipleArguments(): void
    {
        $result = LogHelper::logf('Test: %s, %d, %s', true, 'arg1', 42, 'arg3');
        if (\function_exists('error_log')) {
            $this->assertTrue($result);
        } else {
            $this->assertFalse($result);
        }
    }

    public function testLogfReturnsTrueWhenLoggingNull(): void
    {
        // Default behavior: true for backward compatibility
        $result = LogHelper::logf('Test message: %s', null, 'value');
        if (\function_exists('error_log')) {
            $this->assertTrue($result);
        } else {
            $this->assertFalse($result);
        }
    }

    public function testLogWithEmptyString(): void
    {
        $result = LogHelper::log('', true);
        if (\function_exists('error_log')) {
            $this->assertTrue($result);
        } else {
            $this->assertFalse($result);
        }
    }

    public function testLogfWithEmptyFormat(): void
    {
        $result = LogHelper::logf('', true);
        if (\function_exists('error_log')) {
            $this->assertTrue($result);
        } else {
            $this->assertFalse($result);
        }
    }

    public function testLogfWithSpecialCharacters(): void
    {
        $result = LogHelper::logf('Test: %s with special chars: <>&"\'', true, 'value');
        if (\function_exists('error_log')) {
            $this->assertTrue($result);
        } else {
            $this->assertFalse($result);
        }
    }

    public function testLogfWithNumericValues(): void
    {
        $result = LogHelper::logf('Test: %d, %f, %s', true, 42, 3.14, 'string');
        if (\function_exists('error_log')) {
            $this->assertTrue($result);
        } else {
            $this->assertFalse($result);
        }
    }

    public function testLogfWithNoArguments(): void
    {
        $result = LogHelper::logf('Test message without placeholders', true);
        if (\function_exists('error_log')) {
            $this->assertTrue($result);
        } else {
            $this->assertFalse($result);
        }
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
