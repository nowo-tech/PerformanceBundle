<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Message;

use Nowo\PerformanceBundle\Message\RecordMetricsMessage;
use PHPUnit\Framework\TestCase;

final class RecordMetricsMessageTest extends TestCase
{
    public function testGettersReturnConstructorValues(): void
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

        $this->assertSame('app_home', $message->getRouteName());
        $this->assertSame('dev', $message->getEnv());
        $this->assertSame(0.5, $message->getRequestTime());
        $this->assertSame(10, $message->getTotalQueries());
        $this->assertSame(0.2, $message->getQueryTime());
        $this->assertSame(['id' => 123], $message->getParams());
        $this->assertSame(1048576, $message->getMemoryUsage());
        $this->assertSame('GET', $message->getHttpMethod());
    }

    public function testGettersReturnNullForOptionalParameters(): void
    {
        $message = new RecordMetricsMessage(
            'app_home',
            'dev'
        );

        $this->assertSame('app_home', $message->getRouteName());
        $this->assertSame('dev', $message->getEnv());
        $this->assertNull($message->getRequestTime());
        $this->assertNull($message->getTotalQueries());
        $this->assertNull($message->getQueryTime());
        $this->assertNull($message->getParams());
        $this->assertNull($message->getMemoryUsage());
        $this->assertNull($message->getHttpMethod());
    }
}
