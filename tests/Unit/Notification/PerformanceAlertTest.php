<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Notification;

use Nowo\PerformanceBundle\Notification\PerformanceAlert;
use PHPUnit\Framework\TestCase;

final class PerformanceAlertTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $alert = new PerformanceAlert(
            PerformanceAlert::TYPE_REQUEST_TIME,
            PerformanceAlert::SEVERITY_CRITICAL,
            'Test message',
            ['value' => 1.5, 'threshold' => 1.0]
        );

        $this->assertSame(PerformanceAlert::TYPE_REQUEST_TIME, $alert->getType());
        $this->assertSame(PerformanceAlert::SEVERITY_CRITICAL, $alert->getSeverity());
        $this->assertSame('Test message', $alert->getMessage());
        $this->assertSame(['value' => 1.5, 'threshold' => 1.0], $alert->getContext());
    }

    public function testIsCritical(): void
    {
        $criticalAlert = new PerformanceAlert(
            PerformanceAlert::TYPE_REQUEST_TIME,
            PerformanceAlert::SEVERITY_CRITICAL,
            'Critical alert'
        );

        $warningAlert = new PerformanceAlert(
            PerformanceAlert::TYPE_REQUEST_TIME,
            PerformanceAlert::SEVERITY_WARNING,
            'Warning alert'
        );

        $this->assertTrue($criticalAlert->isCritical());
        $this->assertFalse($warningAlert->isCritical());
    }

    public function testIsWarning(): void
    {
        $criticalAlert = new PerformanceAlert(
            PerformanceAlert::TYPE_REQUEST_TIME,
            PerformanceAlert::SEVERITY_CRITICAL,
            'Critical alert'
        );

        $warningAlert = new PerformanceAlert(
            PerformanceAlert::TYPE_REQUEST_TIME,
            PerformanceAlert::SEVERITY_WARNING,
            'Warning alert'
        );

        $this->assertFalse($criticalAlert->isWarning());
        $this->assertTrue($warningAlert->isWarning());
    }

    public function testGetContextValue(): void
    {
        $alert = new PerformanceAlert(
            PerformanceAlert::TYPE_REQUEST_TIME,
            PerformanceAlert::SEVERITY_WARNING,
            'Test',
            ['key1' => 'value1', 'key2' => 123]
        );

        $this->assertSame('value1', $alert->getContextValue('key1'));
        $this->assertSame(123, $alert->getContextValue('key2'));
        $this->assertNull($alert->getContextValue('nonexistent'));
        $this->assertSame('default', $alert->getContextValue('nonexistent', 'default'));
    }

    public function testGetContextDefaultsToEmptyArray(): void
    {
        $alert = new PerformanceAlert(
            PerformanceAlert::TYPE_QUERY_COUNT,
            PerformanceAlert::SEVERITY_WARNING,
            'No context passed'
        );

        $this->assertSame([], $alert->getContext());
        $this->assertNull($alert->getContextValue('any'));
        $this->assertSame('fallback', $alert->getContextValue('any', 'fallback'));
    }

    public function testAlertTypes(): void
    {
        $types = [
            PerformanceAlert::TYPE_REQUEST_TIME,
            PerformanceAlert::TYPE_QUERY_COUNT,
            PerformanceAlert::TYPE_QUERY_TIME,
            PerformanceAlert::TYPE_MEMORY_USAGE,
            PerformanceAlert::TYPE_OUTLIER,
        ];

        foreach ($types as $type) {
            $alert = new PerformanceAlert($type, PerformanceAlert::SEVERITY_WARNING, 'Test');
            $this->assertSame($type, $alert->getType());
        }
    }
}
