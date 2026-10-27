<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Security;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Default role-based access checker driven by nowo_performance.security.access_roles.
 *
 * An empty role list grants nothing (fail closed); the extension refuses to compile that configuration.
 */
final readonly class ConfigurablePerformanceAccessChecker implements PerformanceAccessCheckerInterface
{
    /** @param list<string> $accessRoles */
    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker,
        private array $accessRoles,
    ) {
    }

    public function canAccess(?object $user): bool
    {
        foreach ($this->accessRoles as $role) {
            if ($this->authorizationChecker->isGranted($role)) {
                return true;
            }
        }

        return false;
    }
}
