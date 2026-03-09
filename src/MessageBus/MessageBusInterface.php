<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\MessageBus;

/**
 * Minimal interface for dispatching messages (Symfony Messenger compatible).
 * Used so the bundle does not require symfony/messenger as a direct dependency.
 */
interface MessageBusInterface
{
    public function dispatch(object $message): mixed;
}
