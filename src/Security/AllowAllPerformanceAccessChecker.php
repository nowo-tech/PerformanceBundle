<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Security;

/**
 * Permissive checker used only when security.allow_unauthenticated is true (demo/dev).
 */
final readonly class AllowAllPerformanceAccessChecker implements PerformanceAccessCheckerInterface
{
    public function canAccess(?object $user): bool
    {
        return true;
    }
}
