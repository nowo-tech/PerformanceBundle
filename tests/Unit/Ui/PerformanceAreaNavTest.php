<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Ui;

use Nowo\PerformanceBundle\Ui\PerformanceAreaNav;
use Nowo\UiKitBundle\View\AreaNavItem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PerformanceAreaNavTest extends TestCase
{
    public function testItemsWithAccessRecords(): void
    {
        $nav = (new PerformanceAreaNav($this->urls(), true))->areaNav(PerformanceAreaNav::AREA);

        self::assertNotNull($nav);
        self::assertSame(['/nowo_performance.index', '/nowo_performance.statistics', '/nowo_performance.access_statistics', '/nowo_performance.access_records', '/nowo_performance.diagnose'], array_map(static fn (AreaNavItem $i): string => $i->href, $nav->items));
        self::assertNull($nav->active());
        self::assertSame(['data-testid' => 'performance-nav-index'], $nav->items[0]->attributes);
    }

    public function testAccessRecordEntriesNeedAccessRecords(): void
    {
        $nav = (new PerformanceAreaNav($this->urls()))->areaNav(PerformanceAreaNav::AREA, ['current' => '/nowo_performance.diagnose']);

        self::assertNotNull($nav);
        self::assertCount(3, $nav->items);
        self::assertSame('/nowo_performance.diagnose', $nav->active()?->href);
    }

    public function testOtherAreasAreUnknown(): void
    {
        self::assertNull((new PerformanceAreaNav($this->urls(), true))->areaNav('blog_kit'));
    }

    public function testForPageMarksThePageActive(): void
    {
        $areaNav = new PerformanceAreaNav($this->urls(), true);

        self::assertSame('/nowo_performance.access_records', $areaNav->forPage('access_records')->active()?->href);
        self::assertSame('/nowo_performance.index', $areaNav->forPage('unknown')->active()?->href);
    }

    private function urls(): UrlGeneratorInterface
    {
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(static fn (string $name): string => '/' . $name);

        return $urls;
    }
}
