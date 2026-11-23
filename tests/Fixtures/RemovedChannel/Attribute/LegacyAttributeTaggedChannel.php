<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Fixtures\RemovedChannel\Attribute;

use Nowo\PerformanceBundle\Notification\NotificationChannelInterface;
use Nowo\PerformanceBundle\Notification\PerformanceAlert;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * A 3.x host channel tagged only through #[AutoconfigureTag] and registered explicitly with autoconfigure: it still
 * implements the interface removed in 4.0, so it cannot be loaded, its attribute is never read and it is never tagged.
 * The container build must fail. Never loaded by the test suite itself.
 */
#[AutoconfigureTag('nowo_performance.notification_channel')]
final class LegacyAttributeTaggedChannel implements NotificationChannelInterface
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
        return 'legacy_attribute';
    }
}
