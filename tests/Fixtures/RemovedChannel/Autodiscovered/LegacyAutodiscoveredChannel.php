<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Fixtures\RemovedChannel\Autodiscovered;

use Nowo\PerformanceBundle\Notification\NotificationChannelInterface;
use Nowo\PerformanceBundle\Notification\PerformanceAlert;

/**
 * A 3.x host channel registered by resource autodiscovery that still implements the interface removed in 4.0. It
 * cannot be loaded (the interface no longer exists): the container build must fail instead of dropping it silently.
 * Never loaded by the test suite itself.
 */
final class LegacyAutodiscoveredChannel implements NotificationChannelInterface
{
    public function send(PerformanceAlert $alert, object $context): bool
    {
        return true;
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function getName(): string
    {
        return 'legacy_autodiscovered';
    }
}
