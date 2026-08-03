<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Security;

use Nowo\PerformanceBundle\Security\AllowAllPerformanceAccessChecker;
use PHPUnit\Framework\TestCase;
use stdClass;

final class AllowAllPerformanceAccessCheckerTest extends TestCase
{
    public function testCanAccessAlwaysReturnsTrue(): void
    {
        $checker = new AllowAllPerformanceAccessChecker();

        self::assertTrue($checker->canAccess(null));
        self::assertTrue($checker->canAccess(new stdClass()));
    }
}
