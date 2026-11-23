<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Fixtures\RemovedChannel\Override;

use Nowo\PerformanceBundle\Notification\NotificationChannelInterface;
use Nowo\PerformanceBundle\Notification\PerformanceAlert;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * A 3.x host channel first registered by resource autodiscovery, then re-declared explicitly to pass an argument (the
 * override discards the autodiscovery loading error). It still implements the interface removed in 4.0: the container
 * build must fail. Never loaded by the test suite itself.
 */
#[AutoconfigureTag('nowo_performance.notification_channel')]
final readonly class LegacyOverriddenChannel implements NotificationChannelInterface
{
    public function __construct(private string $webhook)
    {
    }

    public function send(PerformanceAlert $alert, object $context): bool
    {
        return $this->webhook !== '';
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function getName(): string
    {
        return 'legacy_override';
    }
}
