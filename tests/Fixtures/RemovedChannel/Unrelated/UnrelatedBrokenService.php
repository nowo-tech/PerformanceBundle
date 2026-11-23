<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Fixtures\RemovedChannel\Unrelated;

use Nowo\PerformanceBundle\Tests\Fixtures\RemovedChannel\Unrelated\Missing\NotificationChannelInterface;

/**
 * Autodiscovered class implementing an unrelated missing interface (same short name as the removed port): its loading
 * error must stay Symfony's lazy one, the container still compiles. Never loaded by the test suite itself.
 */
final class UnrelatedBrokenService implements NotificationChannelInterface
{
}
