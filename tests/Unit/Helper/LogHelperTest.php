<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Helper;

use Nowo\PerformanceBundle\Helper\LogHelper;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

use function define;

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

    public function testLogWritesToTheErrorLogWhenNotSuppressed(): void
    {
        $file     = (string) tempnam(sys_get_temp_dir(), 'nowo-perf-log');
        $previous = ini_set('error_log', $file);

        try {
            self::assertTrue(LogHelper::log('plain message', true));
            self::assertTrue(LogHelper::logf('hello %s', null, 'world'));
            $written = (string) file_get_contents($file);
        } finally {
            ini_set('error_log', (string) $previous);
            unlink($file);
        }

        self::assertStringContainsString('plain message', $written);
        self::assertStringContainsString('hello world', $written);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testLogSkipsTheErrorLogUnderTheSuppressConstant(): void
    {
        define('NOWO_PERFORMANCE_SUPPRESS_LOGS_IN_TESTS', true);
        $file     = (string) tempnam(sys_get_temp_dir(), 'nowo-perf-log');
        $previous = ini_set('error_log', $file);

        try {
            self::assertTrue(LogHelper::log('msg', true));
            self::assertTrue(LogHelper::logf('hello %s', true, 'world'));
            $written = (string) file_get_contents($file);
        } finally {
            ini_set('error_log', (string) $previous);
            unlink($file);
        }

        self::assertSame('', $written);
    }
}
