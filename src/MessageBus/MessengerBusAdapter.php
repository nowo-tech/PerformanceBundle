<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\MessageBus;

use function call_user_func;

/**
 * Adapter that wraps Symfony Messenger's message bus (when available) for use with PerformanceMetricsService.
 * When Messenger is not installed, the wrapped bus is null and dispatch() is a no-op.
 */
final class MessengerBusAdapter implements MessageBusInterface
{
    public function __construct(
        private readonly ?object $bus = null,
    ) {
    }

    public function dispatch(object $message): mixed
    {
        if ($this->bus === null || !method_exists($this->bus, 'dispatch')) {
            return null;
        }

        return call_user_func([$this->bus, 'dispatch'], $message);
    }
}
