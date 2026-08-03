<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Security;

/**
 * Access control for the performance dashboard UI (REQ-UI-002).
 */
interface PerformanceAccessCheckerInterface
{
    public function canAccess(?object $user): bool;
}
