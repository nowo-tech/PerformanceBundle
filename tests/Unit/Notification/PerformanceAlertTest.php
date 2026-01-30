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

    public function testGetContextValueWithZeroAndEmptyString(): void
    {
        $alert = new PerformanceAlert(
            PerformanceAlert::TYPE_QUERY_COUNT,
            PerformanceAlert::SEVERITY_WARNING,
            'Test',
            ['count' => 0, 'label' => '']
        );

        $this->assertSame(0, $alert->getContextValue('count'));
        $this->assertSame('', $alert->getContextValue('label'));
        $this->assertSame(99, $alert->getContextValue('missing', 99));
    }

    public function testGetContextValueWithNullInContext(): void
    {
        $alert = new PerformanceAlert(
            PerformanceAlert::TYPE_MEMORY_USAGE,
            PerformanceAlert::SEVERITY_WARNING,
            'Test',
            ['nullable_field' => null]
        );

        $this->assertNull($alert->getContextValue('nullable_field'));
        $this->assertSame('default', $alert->getContextValue('nullable_field', 'default'));
    }

    public function testSeverityConstants(): void
    {
        $this->assertSame('warning', PerformanceAlert::SEVERITY_WARNING);
        $this->assertSame('critical', PerformanceAlert::SEVERITY_CRITICAL);
    }

    public function testTypeConstants(): void
    {
        $this->assertSame('request_time', PerformanceAlert::TYPE_REQUEST_TIME);
        $this->assertSame('query_count', PerformanceAlert::TYPE_QUERY_COUNT);
        $this->assertSame('query_time', PerformanceAlert::TYPE_QUERY_TIME);
        $this->assertSame('memory_usage', PerformanceAlert::TYPE_MEMORY_USAGE);
        $this->assertSame('outlier', PerformanceAlert::TYPE_OUTLIER);
    }

    public function testGetMessageWithEmptyString(): void
    {
        $alert = new PerformanceAlert(
            PerformanceAlert::TYPE_OUTLIER,
            PerformanceAlert::SEVERITY_WARNING,
            ''
        );

        $this->assertSame('', $alert->getMessage());
    }

    public function testGetSeverityReturnsConstructorValue(): void
    {
        $alert = new PerformanceAlert(
            PerformanceAlert::TYPE_QUERY_TIME,
            PerformanceAlert::SEVERITY_CRITICAL,
            'Critical query time'
        );

        $this->assertSame(PerformanceAlert::SEVERITY_CRITICAL, $alert->getSeverity());
    }

    public function testGetContextWithMultipleKeys(): void
    {
        $context = [
            'value' => 1.5,
            'threshold' => 1.0,
            'route' => 'api_slow',
        ];
        $alert = new PerformanceAlert(
            PerformanceAlert::TYPE_REQUEST_TIME,
            PerformanceAlert::SEVERITY_CRITICAL,
            'Critical',
            $context
        );

        $this->assertSame($context, $alert->getContext());
        $this->assertSame(1.5, $alert->getContextValue('value'));
        $this->assertSame(1.0, $alert->getContextValue('threshold'));
        $this->assertSame('api_slow', $alert->getContextValue('route'));
    }

    public function testOutlierTypeWithContext(): void
    {
        $alert = new PerformanceAlert(
            PerformanceAlert::TYPE_OUTLIER,
            PerformanceAlert::SEVERITY_WARNING,
            'Outlier detected',
            ['route' => 'api_slow', 'value' => 2.5]
        );

        $this->assertSame(PerformanceAlert::TYPE_OUTLIER, $alert->getType());
        $this->assertSame('api_slow', $alert->getContextValue('route'));
        $this->assertSame(2.5, $alert->getContextValue('value'));
    }
}
