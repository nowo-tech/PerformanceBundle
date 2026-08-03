<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Security;

use Nowo\PerformanceBundle\Security\ConfigurablePerformanceAccessChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class ConfigurablePerformanceAccessCheckerTest extends TestCase
{
    public function testCanAccessReturnsTrueWhenAnyRoleIsGranted(): void
    {
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker
            ->expects(self::exactly(2))
            ->method('isGranted')
            ->willReturnCallback(static fn (string $role): bool => $role === 'ROLE_ADMIN');

        $checker = new ConfigurablePerformanceAccessChecker($authorizationChecker, ['ROLE_EDITOR', 'ROLE_ADMIN']);

        self::assertTrue($checker->canAccess(null));
    }

    public function testCanAccessReturnsFalseWhenNoRoleIsGranted(): void
    {
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker
            ->expects(self::exactly(2))
            ->method('isGranted')
            ->willReturn(false);

        $checker = new ConfigurablePerformanceAccessChecker($authorizationChecker, ['ROLE_EDITOR', 'ROLE_ADMIN']);

        self::assertFalse($checker->canAccess(null));
    }

    public function testCanAccessReturnsTrueWhenAccessRolesAreEmpty(): void
    {
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->expects(self::never())->method('isGranted');

        $checker = new ConfigurablePerformanceAccessChecker($authorizationChecker, []);

        self::assertTrue($checker->canAccess(null));
    }
}
