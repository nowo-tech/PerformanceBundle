<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Twig;

use Nowo\PerformanceBundle\Twig\PerformanceLayoutExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PerformanceLayoutExtension::class)]
final class PerformanceLayoutExtensionTest extends TestCase
{
    public function testGetGlobalsExposesLayoutTemplate(): void
    {
        $layout    = '@NowoPerformanceBundle/Performance/layout.html.twig';
        $extension = new PerformanceLayoutExtension($layout);

        $globals = $extension->getGlobals();

        self::assertArrayHasKey(PerformanceLayoutExtension::GLOBAL_LAYOUT_TEMPLATE, $globals);
        self::assertSame($layout, $globals[PerformanceLayoutExtension::GLOBAL_LAYOUT_TEMPLATE]);
        self::assertSame('nowo_performance_layout_template', PerformanceLayoutExtension::GLOBAL_LAYOUT_TEMPLATE);
    }
}
