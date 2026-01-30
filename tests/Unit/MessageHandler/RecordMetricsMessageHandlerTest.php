<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\MessageHandler;

use Nowo\PerformanceBundle\Message\RecordMetricsMessage;
use Nowo\PerformanceBundle\MessageHandler\RecordMetricsMessageHandler;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

final class RecordMetricsMessageHandlerTest extends TestCase
{
    private PerformanceMetricsService|MockObject $metricsService;

    protected function setUp(): void
    {
        $this->metricsService = $this->createMock(PerformanceMetricsService::class);
    }

    public function testInvokeCallsRecordMetricsWithAllParameters(): void
    {
        $message = new RecordMetricsMessage(
            'app_home',
            'dev',
            0.5,
            10,
            0.2,
            ['id' => 123],
            1048576,
            'GET'
        );

        $this->metricsService->expects($this->once())
            ->method('recordMetrics')
            ->with(
                'app_home',
                'dev',
                0.5,
                10,
                0.2,
                ['id' => 123],
                1048576,
                'GET',
                null,
                [],
                null,
                null,
                null,
                null
            );

        $handler = new RecordMetricsMessageHandler($this->metricsService);
        $handler($message);
    }

    public function testInvokeHandlesNullValues(): void
    {
        $message = new RecordMetricsMessage(
            'app_home',
            'dev',
            null,
            null,
            null,
            null,
            null,
            null
        );

        $this->metricsService->expects($this->once())
            ->method('recordMetrics')
            ->with(
                'app_home',
                'dev',
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                [],
                null,
                null,
                null,
                null
            );

        $handler = new RecordMetricsMessageHandler($this->metricsService);
        $handler($message);
    }

    public function testInvokePassesRequestIdToRecordMetrics(): void
    {
        $message = new RecordMetricsMessage(
            'app_home',
            'dev',
            0.5,
            10,
            0.2,
            null,
            null,
            'GET',
            'req-abc123'
        );

        $this->metricsService->expects($this->once())
            ->method('recordMetrics')
            ->with(
                'app_home',
                'dev',
                0.5,
                10,
                0.2,
                null,
                null,
                'GET',
                null,
                [],
                'req-abc123',
                null,
                null,
                null
            );

        $handler = new RecordMetricsMessageHandler($this->metricsService);
        $handler($message);
    }

    public function testInvokePassesRefererToRecordMetrics(): void
    {
        $message = new RecordMetricsMessage(
            'app_home',
            'dev',
            0.5,
            10,
            0.2,
            null,
            null,
            'GET',
            null,
            'https://referer.example/'
        );

        $this->metricsService->expects($this->once())
            ->method('recordMetrics')
            ->with(
                'app_home',
                'dev',
                0.5,
                10,
                0.2,
                null,
                null,
                'GET',
                null,
                [],
                null,
                'https://referer.example/',
                null,
                null
            );

        $handler = new RecordMetricsMessageHandler($this->metricsService);
        $handler($message);
    }

    public function testInvokePassesNullRequestIdWhenMessageHasNoRequestId(): void
    {
        $message = new RecordMetricsMessage('app_home', 'dev', 0.5, 5);

        $this->metricsService->expects($this->once())
            ->method('recordMetrics')
            ->with(
                'app_home',
                'dev',
                0.5,
                5,
                null,
                null,
                null,
                null,
                null,
                [],
                null,
                null,
                null,
                null
            );

        $handler = new RecordMetricsMessageHandler($this->metricsService);
        $handler($message);
    }

    public function testInvokePassesUserIdentifierAndUserIdToRecordMetrics(): void
    {
        $message = new RecordMetricsMessage(
            'api_foo',
            'prod',
            0.1,
            3,
            0.05,
            null,
            null,
            'POST',
            null,
            null,
            'user@example.com',
            '42'
        );

        $this->metricsService->expects($this->once())
            ->method('recordMetrics')
            ->with(
                'api_foo',
                'prod',
                0.1,
                3,
                0.05,
                null,
                null,
                'POST',
                null,
                [],
                null,
                null,
                'user@example.com',
                '42'
            );

        $handler = new RecordMetricsMessageHandler($this->metricsService);
        $handler($message);
    }
}
