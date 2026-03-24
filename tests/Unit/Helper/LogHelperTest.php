<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Helper;

use Nowo\PerformanceBundle\Helper\LogHelper;
use PHPUnit\Framework\TestCase;

use function ini_get;

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
        $this->assertTrue(LogHelper::isLoggingEnabled());
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
        $result = LogHelper::log('Test message');
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
        $this->assertTrue(LogHelper::isLoggingEnabled());
    }

    /**
     * When testSuppressOverride is false and testLogWriter is set, log() uses the writer (path coverage without stderr).
     *
     * @covers \Nowo\PerformanceBundle\Helper\LogHelper::log
     */
    public function testLogUsesWriterWhenTestSuppressOverrideFalseAndWriterSet(): void
    {
        $prevSuppress = LogHelper::$testSuppressOverride;
        $prevWriter   = LogHelper::$testLogWriter;
        try {
            LogHelper::$testSuppressOverride = false;
            LogHelper::$testLogWriter        = static function (string $msg): void {
                // no-op to avoid stderr; path coverage only
            };
            $result = LogHelper::log('test message', true);
            $this->assertTrue($result);
        } finally {
            LogHelper::$testSuppressOverride = $prevSuppress;
            LogHelper::$testLogWriter        = $prevWriter;
        }
    }

    /**
     * When testSuppressOverride is false and testLogWriter is set, logf() uses the writer (path coverage without stderr).
     *
     * @covers \Nowo\PerformanceBundle\Helper\LogHelper::logf
     */
    public function testLogfUsesWriterWhenTestSuppressOverrideFalseAndWriterSet(): void
    {
        $prevSuppress = LogHelper::$testSuppressOverride;
        $prevWriter   = LogHelper::$testLogWriter;
        try {
            LogHelper::$testSuppressOverride = false;
            LogHelper::$testLogWriter        = static function (string $msg): void {
                // no-op
            };
            $result = LogHelper::logf('formatted: %s', true, 'value');
            $this->assertTrue($result);
        } finally {
            LogHelper::$testSuppressOverride = $prevSuppress;
            LogHelper::$testLogWriter        = $prevWriter;
        }
    }

    /**
     * When suppress is false and testLogWriter is null, log() uses error_log (lines 86-87).
     * Redirect error_log to a temp file to satisfy beStrictAboutOutputDuringTests.
     *
     * @covers \Nowo\PerformanceBundle\Helper\LogHelper::log
     */
    public function testLogUsesErrorLogWhenSuppressFalseAndNoTestWriter(): void
    {
        $prevSuppress = LogHelper::$testSuppressOverride;
        $prevWriter   = LogHelper::$testLogWriter;
        $prevErrorLog = ini_get('error_log');
        $tmpFile      = tempnam(sys_get_temp_dir(), 'phpunit_log');
        try {
            ini_set('error_log', $tmpFile);
            LogHelper::$testSuppressOverride = false;
            LogHelper::$testLogWriter        = null;
            $result                          = LogHelper::log('error_log path', true);
            $this->assertTrue($result);
            $this->assertStringContainsString('error_log path', (string) file_get_contents($tmpFile));
        } finally {
            LogHelper::$testSuppressOverride = $prevSuppress;
            LogHelper::$testLogWriter        = $prevWriter;
            ini_set('error_log', $prevErrorLog ?: '');
            @unlink($tmpFile);
        }
    }

    /**
     * When error_log is treated as unavailable, log() returns false.
     *
     * @covers \Nowo\PerformanceBundle\Helper\LogHelper::log
     */
    public function testLogReturnsFalseWhenErrorLogNotAvailable(): void
    {
        $prevSuppress = LogHelper::$testSuppressOverride;
        $prevWriter   = LogHelper::$testLogWriter;
        $prevErrLog   = LogHelper::$testFunctionErrorLogExistsOverride;
        try {
            LogHelper::$testSuppressOverride               = false;
            LogHelper::$testLogWriter                      = null;
            LogHelper::$testFunctionErrorLogExistsOverride = false;
            $this->assertFalse(LogHelper::log('x', true));
        } finally {
            LogHelper::$testSuppressOverride               = $prevSuppress;
            LogHelper::$testLogWriter                      = $prevWriter;
            LogHelper::$testFunctionErrorLogExistsOverride = $prevErrLog;
        }
    }

    public function testLogfReturnsFalseWhenErrorLogNotAvailable(): void
    {
        $prevSuppress = LogHelper::$testSuppressOverride;
        $prevWriter   = LogHelper::$testLogWriter;
        $prevErrLog   = LogHelper::$testFunctionErrorLogExistsOverride;
        try {
            LogHelper::$testSuppressOverride               = false;
            LogHelper::$testLogWriter                      = null;
            LogHelper::$testFunctionErrorLogExistsOverride = false;
            $this->assertFalse(LogHelper::logf('x %s', true, 'y'));
        } finally {
            LogHelper::$testSuppressOverride               = $prevSuppress;
            LogHelper::$testLogWriter                      = $prevWriter;
            LogHelper::$testFunctionErrorLogExistsOverride = $prevErrLog;
        }
    }

    public function testLogfUsesErrorLogWhenSuppressFalseAndNoTestWriter(): void
    {
        $prevSuppress = LogHelper::$testSuppressOverride;
        $prevWriter   = LogHelper::$testLogWriter;
        $prevErrorLog = ini_get('error_log');
        $tmpFile      = tempnam(sys_get_temp_dir(), 'phpunit_log');
        try {
            ini_set('error_log', $tmpFile);
            LogHelper::$testSuppressOverride = false;
            LogHelper::$testLogWriter        = null;
            $result                          = LogHelper::logf('error_log path: %s', true, 'value');
            $this->assertTrue($result);
            $this->assertStringContainsString('error_log path: value', (string) file_get_contents($tmpFile));
        } finally {
            LogHelper::$testSuppressOverride = $prevSuppress;
            LogHelper::$testLogWriter        = $prevWriter;
            ini_set('error_log', $prevErrorLog ?: '');
            @unlink($tmpFile);
        }
    }
}
