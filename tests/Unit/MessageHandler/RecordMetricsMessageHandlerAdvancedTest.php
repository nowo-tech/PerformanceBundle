<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\MessageHandler;

use Nowo\PerformanceBundle\Message\RecordMetricsMessage;
use Nowo\PerformanceBundle\MessageHandler\RecordMetricsMessageHandler;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Advanced tests for RecordMetricsMessageHandler edge cases.
 */
final class RecordMetricsMessageHandlerAdvancedTest extends TestCase
{
    private PerformanceMetricsService|MockObject $metricsService;

    protected function setUp(): void
    {
        $this->metricsService = $this->createMock(PerformanceMetricsService::class);
    }

    public function testInvokeWithAllParameters(): void
    {
        $message = new RecordMetricsMessage(
            'app_home',
            'prod',
            0.5,
            10,
            0.2,
            ['id' => 123],
            1048576,
            'POST'
        );

        $this->metricsService->expects($this->once())
            ->method('recordMetrics')
            ->with(
                'app_home',
                'prod',
                0.5,
                10,
                0.2,
                ['id' => 123],
                1048576,
                'POST',
                null,
                [],
                null
            );

        $handler = new RecordMetricsMessageHandler($this->metricsService);
        $handler($message);
    }

    public function testInvokeWithOnlyRequiredParameters(): void
    {
        $message = new RecordMetricsMessage('app_home', 'dev');

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
                null
            );

        $handler = new RecordMetricsMessageHandler($this->metricsService);
        $handler($message);
    }

    public function testInvokeWithZeroValues(): void
    {
        $message = new RecordMetricsMessage(
            'app_home',
            'dev',
            0.0,
            0,
            0.0,
            [],
            0,
            'GET'
        );

        $this->metricsService->expects($this->once())
            ->method('recordMetrics')
            ->with(
                'app_home',
                'dev',
                0.0,
                0,
                0.0,
                [],
                0,
                'GET',
                null,
                [],
                null
            );

        $handler = new RecordMetricsMessageHandler($this->metricsService);
        $handler($message);
    }

    public function testInvokeWithEmptyStringRouteName(): void
    {
        $message = new RecordMetricsMessage('', 'dev');

        $this->metricsService->expects($this->once())
            ->method('recordMetrics')
            ->with(
                '',
                'dev',
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                [],
                null
            );

        $handler = new RecordMetricsMessageHandler($this->metricsService);
        $handler($message);
    }

    public function testInvokeWithEmptyStringEnv(): void
    {
        $message = new RecordMetricsMessage('app_home', '');

        $this->metricsService->expects($this->once())
            ->method('recordMetrics')
            ->with(
                'app_home',
                '',
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                [],
                null
            );

        $handler = new RecordMetricsMessageHandler($this->metricsService);
        $handler($message);
    }

    public function testInvokeWithComplexParams(): void
    {
        $params = [
            'id' => 123,
            'slug' => 'test-article',
            'category' => ['tech', 'programming'],
        ];

        $message = new RecordMetricsMessage(
            'app_home',
            'dev',
            null,
            null,
            null,
            $params
        );

        $this->metricsService->expects($this->once())
            ->method('recordMetrics')
            ->with(
                'app_home',
                'dev',
                null,
                null,
                null,
                $params,
                null,
                null,
                null,
                [],
                null
            );

        $handler = new RecordMetricsMessageHandler($this->metricsService);
        $handler($message);
    }

    public function testInvokeWithVeryLargeValues(): void
    {
        $message = new RecordMetricsMessage(
            'app_home',
            'dev',
            999999.99,
            999999,
            999999.99,
            null,
            999999999,
            'GET'
        );

        $this->metricsService->expects($this->once())
            ->method('recordMetrics')
            ->with(
                'app_home',
                'dev',
                999999.99,
                999999,
                999999.99,
                null,
                999999999,
                'GET',
                null,
                [],
                null
            );

        $handler = new RecordMetricsMessageHandler($this->metricsService);
        $handler($message);
    }

    public function testInvokeWithVerySmallValues(): void
    {
        $message = new RecordMetricsMessage(
            'app_home',
            'dev',
            0.0001,
            1,
            0.0001,
            null,
            1024,
            'GET'
        );

        $this->metricsService->expects($this->once())
            ->method('recordMetrics')
            ->with(
                'app_home',
                'dev',
                0.0001,
                1,
                0.0001,
                null,
                1024,
                'GET',
                null,
                [],
                null
            );

        $handler = new RecordMetricsMessageHandler($this->metricsService);
        $handler($message);
    }

    public function testInvokeWithDifferentHttpMethods(): void
    {
        $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];

        foreach ($methods as $method) {
            $message = new RecordMetricsMessage(
                'app_home',
                'dev',
                null,
                null,
                null,
                null,
                null,
                $method
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
                    $method,
                    null,
                    [],
                    null
                );

            $handler = new RecordMetricsMessageHandler($this->metricsService);
            $handler($message);
        }
    }

    public function testInvokeWithDifferentEnvironments(): void
    {
        $environments = ['dev', 'test', 'prod', 'stage', 'qa'];

        foreach ($environments as $env) {
            $message = new RecordMetricsMessage('app_home', $env);

            $this->metricsService->expects($this->once())
                ->method('recordMetrics')
                ->with(
                    'app_home',
                    $env,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    [],
                    null
                );

            $handler = new RecordMetricsMessageHandler($this->metricsService);
            $handler($message);
        }
    }

    public function testInvokeHandlesServiceExceptions(): void
    {
        $message = new RecordMetricsMessage('app_home', 'dev', 0.5);

        $this->metricsService->expects($this->once())
            ->method('recordMetrics')
            ->willThrowException(new \Exception('Database error'));

        $handler = new RecordMetricsMessageHandler($this->metricsService);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database error');

        $handler($message);
    }

    public function testInvokeCanBeCalledMultipleTimes(): void
    {
        $message1 = new RecordMetricsMessage('app_home', 'dev', 0.5);
        $message2 = new RecordMetricsMessage('app_about', 'dev', 0.3);

        $this->metricsService->expects($this->exactly(2))
            ->method('recordMetrics');

        $handler = new RecordMetricsMessageHandler($this->metricsService);
        $handler($message1);
        $handler($message2);
    }

    public function testHandlerIsCallable(): void
    {
        $handler = new RecordMetricsMessageHandler($this->metricsService);

        $this->assertTrue(is_callable($handler));
    }

    public function testInvokeWithPartialParameters(): void
    {
        $message = new RecordMetricsMessage(
            'app_home',
            'dev',
            0.5,
            null,
            null,
            null,
            null,
            'GET'
        );

        $this->metricsService->expects($this->once())
            ->method('recordMetrics')
            ->with(
                'app_home',
                'dev',
                0.5,
                null,
                null,
                null,
                null,
                'GET',
                null,
                [],
                null
            );

        $handler = new RecordMetricsMessageHandler($this->metricsService);
        $handler($message);
    }
}
