<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\MessageBus;

use Nowo\PerformanceBundle\Message\RecordMetricsMessage;
use Nowo\PerformanceBundle\MessageBus\MessageBusInterface;
use Nowo\PerformanceBundle\MessageBus\MessengerBusAdapter;
use PHPUnit\Framework\TestCase;
use stdClass;

final class MessengerBusAdapterTest extends TestCase
{
    public function testDispatchWhenBusIsNullReturnsNull(): void
    {
        $adapter = new MessengerBusAdapter(null);
        $message = new RecordMetricsMessage('route', 'dev', null, null, null, null, null, null, null, null, null, null, null);

        $result = $adapter->dispatch($message);

        $this->assertNull($result);
    }

    public function testDispatchWhenBusHasNoDispatchMethodReturnsNull(): void
    {
        $bus     = new stdClass();
        $adapter = new MessengerBusAdapter($bus);
        $message = new RecordMetricsMessage('route', 'dev', null, null, null, null, null, null, null, null, null, null, null);

        $result = $adapter->dispatch($message);

        $this->assertNull($result);
    }

    public function testDispatchWhenBusHasDispatchMethodCallsIt(): void
    {
        $message = new RecordMetricsMessage('route', 'dev', null, null, null, null, null, null, null, null, null, null, null);
        $bus     = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($message)
            ->willReturn('envelope');

        $adapter = new MessengerBusAdapter($bus);

        $result = $adapter->dispatch($message);

        $this->assertSame('envelope', $result);
    }
}
