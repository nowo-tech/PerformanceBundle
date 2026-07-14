<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\MessageHandler;

use Nowo\PerformanceBundle\Message\RecordMetricsMessage;
use Nowo\PerformanceBundle\MessageHandler\RecordMetricsMessageHandler;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RecordMetricsMessageHandlerTest extends TestCase
{
    private MockObject $metricsService;

    protected function setUp(): void
    {
        $this->metricsService = $this->createMock(PerformanceMetricsService::class);
    }

    public function testInvokeCallsRecordMetricsSyncWithAllParameters(): void
    {
        $message = new RecordMetricsMessage(
            'app_home',
            'dev',
            0.5,
            10,
            0.2,
            ['id' => 123],
            1048576,
            'GET',
            200,
        );

        $this->metricsService->expects($this->once())
            ->method('recordMetricsSync')
            ->with(
                'app_home',
                'dev',
                0.5,
                10,
                0.2,
                ['id' => 123],
                1048576,
                'GET',
                200,
                null,
                null,
                null,
                null,
                null,
            );

        $handler = new RecordMetricsMessageHandler($this->metricsService);
        $handler($message);
    }

    public function testInvokeHandlesNullValues(): void
    {
        $message = new RecordMetricsMessage(
            'app_home',
            'dev',
        );

        $this->metricsService->expects($this->once())
            ->method('recordMetricsSync')
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
                null,
                null,
                null,
                null,
                null,
            );

        $handler = new RecordMetricsMessageHandler($this->metricsService);
        $handler($message);
    }

    public function testInvokePassesRequestIdToRecordMetricsSync(): void
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
            404,
            'req-abc123',
        );

        $this->metricsService->expects($this->once())
            ->method('recordMetricsSync')
            ->with(
                'app_home',
                'dev',
                0.5,
                10,
                0.2,
                null,
                null,
                'GET',
                404,
                'req-abc123',
                null,
                null,
                null,
                null,
            );

        $handler = new RecordMetricsMessageHandler($this->metricsService);
        $handler($message);
    }

    public function testInvokePassesRefererToRecordMetricsSync(): void
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
            null,
            'https://referer.example/',
        );

        $this->metricsService->expects($this->once())
            ->method('recordMetricsSync')
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
                null,
                'https://referer.example/',
                null,
                null,
                null,
            );

        $handler = new RecordMetricsMessageHandler($this->metricsService);
        $handler($message);
    }

    public function testInvokePassesNullRequestIdWhenMessageHasNoRequestId(): void
    {
        $message = new RecordMetricsMessage('app_home', 'dev', 0.5, 5);

        $this->metricsService->expects($this->once())
            ->method('recordMetricsSync')
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
                null,
                null,
                null,
                null,
                null,
            );

        $handler = new RecordMetricsMessageHandler($this->metricsService);
        $handler($message);
    }

    public function testInvokePassesUserIdentifierAndUserIdToRecordMetricsSync(): void
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
            201,
            null,
            null,
            'user@example.com',
            '42',
        );

        $this->metricsService->expects($this->once())
            ->method('recordMetricsSync')
            ->with(
                'api_foo',
                'prod',
                0.1,
                3,
                0.05,
                null,
                null,
                'POST',
                201,
                null,
                null,
                'user@example.com',
                '42',
                null,
            );

        $handler = new RecordMetricsMessageHandler($this->metricsService);
        $handler($message);
    }

    public function testInvokePassesEmptyParamsArrayToRecordMetricsSync(): void
    {
        $message = new RecordMetricsMessage(
            'app_home',
            'dev',
            0.2,
            5,
            0.05,
            [],
            null,
            'GET',
        );

        $this->metricsService->expects($this->once())
            ->method('recordMetricsSync')
            ->with(
                'app_home',
                'dev',
                0.2,
                5,
                0.05,
                [],
                null,
                'GET',
                null,
                null,
                null,
                null,
                null,
                null,
            );

        $handler = new RecordMetricsMessageHandler($this->metricsService);
        $handler($message);
    }
}
