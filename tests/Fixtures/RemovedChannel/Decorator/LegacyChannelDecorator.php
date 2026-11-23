<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Fixtures\RemovedChannel\Decorator;

use Nowo\PerformanceBundle\Notification\NotificationChannelInterface;
use Nowo\PerformanceBundle\Notification\PerformanceAlert;

/**
 * A 3.x decorator of a built-in channel that still implements the interface removed in 4.0: the container build must
 * fail with the upgrade hint. Never loaded by the test suite itself.
 */
final readonly class LegacyChannelDecorator implements NotificationChannelInterface
{
    public function __construct(private object $inner)
    {
    }

    public function send(PerformanceAlert $alert, object $context): bool
    {
        return $this->inner !== $context;
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function getName(): string
    {
        return 'legacy_decorator';
    }
}
