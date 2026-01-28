<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Helper;

use Nowo\PerformanceBundle\Helper\LogHelper;
use PHPUnit\Framework\TestCase;

/**
 * Tests for LogHelper edge cases and advanced scenarios.
 */
final class LogHelperEdgeCasesTest extends TestCase
{
    public function testIsLoggingEnabledWithContainerInterfaceClassExists(): void
    {
        // Test that isLoggingEnabled handles the case where ContainerInterface class exists
        // This tests the fallback logic in the method
        $result = LogHelper::isLoggingEnabled(null);

        // Should default to true for backward compatibility
        $this->assertTrue($result);
    }

    public function testLogWithVeryLongMessage(): void
    {
        $longMessage = str_repeat('A', 10000);
        $result = LogHelper::log($longMessage, true);

        if (\function_exists('error_log')) {
            $this->assertTrue($result);
        } else {
            $this->assertFalse($result);
        }
    }

    public function testLogWithNewlines(): void
    {
        $messageWithNewlines = "Line 1\nLine 2\nLine 3";
        $result = LogHelper::log($messageWithNewlines, true);

        if (\function_exists('error_log')) {
            $this->assertTrue($result);
        } else {
            $this->assertFalse($result);
        }
    }

    public function testLogWithSpecialCharacters(): void
    {
        $specialChars = "Test: <>&\"'`\x00\x01\x02";
        $result = LogHelper::log($specialChars, true);

        if (\function_exists('error_log')) {
            $this->assertTrue($result);
        } else {
            $this->assertFalse($result);
        }
    }

    public function testLogfWithManyArguments(): void
    {
        $result = LogHelper::logf(
            'Test: %s, %d, %f, %s, %d, %f, %s',
            true,
            'arg1',
            1,
            1.1,
            'arg2',
            2,
            2.2,
            'arg3'
        );

        if (\function_exists('error_log')) {
            $this->assertTrue($result);
        } else {
            $this->assertFalse($result);
        }
    }

    public function testLogfWithUnicodeCharacters(): void
    {
        $result = LogHelper::logf('Test: %s', true, 'Test with émojis: 🚀 📊 ⚡');

        if (\function_exists('error_log')) {
            $this->assertTrue($result);
        } else {
            $this->assertFalse($result);
        }
    }

    public function testLogfWithPercentSign(): void
    {
        $result = LogHelper::logf('Test: %s%%', true, '50');

        if (\function_exists('error_log')) {
            $this->assertTrue($result);
        } else {
            $this->assertFalse($result);
        }
    }

    public function testLogfWithComplexFormatting(): void
    {
        $result = LogHelper::logf(
            'Route: %s, Time: %.4f, Queries: %d, Memory: %.2f MB',
            true,
            'app_home',
            0.123456789,
            42,
            10.5
        );

        if (\function_exists('error_log')) {
            $this->assertTrue($result);
        } else {
            $this->assertFalse($result);
        }
    }

    public function testLogfWithNullArguments(): void
    {
        $result = LogHelper::logf('Test: %s', true, null);

        if (\function_exists('error_log')) {
            $this->assertTrue($result);
        } else {
            $this->assertFalse($result);
        }
    }

    public function testLogfWithBooleanArguments(): void
    {
        $result = LogHelper::logf('Test: %s, %s', true, true, false);

        if (\function_exists('error_log')) {
            $this->assertTrue($result);
        } else {
            $this->assertFalse($result);
        }
    }

    public function testLogfWithArrayArguments(): void
    {
        $result = LogHelper::logf('Test: %s', true, ['key' => 'value']);

        if (\function_exists('error_log')) {
            $this->assertTrue($result);
        } else {
            $this->assertFalse($result);
        }
    }

    public function testLogfWithObjectArguments(): void
    {
        $object = new \stdClass();
        $object->property = 'value';
        $result = LogHelper::logf('Test: %s', true, $object);

        if (\function_exists('error_log')) {
            $this->assertTrue($result);
        } else {
            $this->assertFalse($result);
        }
    }

    public function testLogfWithMismatchedFormatSpecifiers(): void
    {
        // More format specifiers than arguments
        $result = LogHelper::logf('Test: %s, %d, %f', true, 'arg1');

        if (\function_exists('error_log')) {
            // Should still log, but with warnings/errors
            $this->assertTrue($result);
        } else {
            $this->assertFalse($result);
        }
    }

    public function testLogfWithMoreArgumentsThanSpecifiers(): void
    {
        // More arguments than format specifiers
        $result = LogHelper::logf('Test: %s', true, 'arg1', 'arg2', 'arg3');

        if (\function_exists('error_log')) {
            $this->assertTrue($result);
        } else {
            $this->assertFalse($result);
        }
    }

    public function testIsLoggingEnabledConsistency(): void
    {
        // Test that isLoggingEnabled returns consistent results
        $result1 = LogHelper::isLoggingEnabled(true);
        $result2 = LogHelper::isLoggingEnabled(true);
        $result3 = LogHelper::isLoggingEnabled(false);
        $result4 = LogHelper::isLoggingEnabled(false);

        $this->assertTrue($result1);
        $this->assertTrue($result2);
        $this->assertFalse($result3);
        $this->assertFalse($result4);
    }

    public function testLogReturnsFalseWhenDisabled(): void
    {
        $result = LogHelper::log('Test message', false);
        $this->assertFalse($result);
    }

    public function testLogfReturnsFalseWhenDisabled(): void
    {
        $result = LogHelper::logf('Test: %s', false, 'value');
        $this->assertFalse($result);
    }
}
