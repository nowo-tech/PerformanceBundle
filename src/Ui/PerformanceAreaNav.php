<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Ui;

use Nowo\UiKitBundle\Navigation\AreaNavProviderInterface;
use Nowo\UiKitBundle\View\AreaNav;
use Nowo\UiKitBundle\View\AreaNavItem;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use function in_array;
use function is_string;

/**
 * Publishes the dashboard navigation to the UiKit area-nav registry (REQ-UI-003 "Navigation integration"), so a host
 * menu (DashboardMenu) can mount it: `nowo_ui_area_nav('performance')` = Dashboard | Statistics | Access statistics |
 * Access records (the last two only with `enable_access_records`) | Diagnose. `current` (an href) marks the active
 * item. The dashboard pages render the same navigation as page-header tabs (fallback for hosts without DashboardMenu).
 * Stateless: safe in worker mode.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class PerformanceAreaNav implements AreaNavProviderInterface
{
    public const AREA = 'performance';

    /** Area entries (id => [route, translation key]), in tab order. */
    public const ITEMS = [
        'index'             => ['nowo_performance.index', 'ui.nav.dashboard'],
        'statistics'        => ['nowo_performance.statistics', 'ui.nav.statistics'],
        'access_statistics' => ['nowo_performance.access_statistics', 'ui.nav.access_statistics'],
        'access_records'    => ['nowo_performance.access_records', 'ui.nav.access_records'],
        'diagnose'          => ['nowo_performance.diagnose', 'ui.nav.diagnose'],
    ];

    /** Entries that need `enable_access_records`. */
    private const ACCESS_RECORD_ITEMS = ['access_statistics', 'access_records'];

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        #[Autowire('%nowo_performance.enable_access_records%')]
        private bool $enableAccessRecords = false,
    ) {
    }

    /**
     * @param array<string, mixed> $context `current` (href of the active item)
     */
    public function areaNav(string $area, array $context = []): ?AreaNav
    {
        if ($area !== self::AREA) {
            return null;
        }

        $items = [];
        foreach (self::ITEMS as $id => [$route, $label]) {
            if (!$this->enableAccessRecords && in_array($id, self::ACCESS_RECORD_ITEMS, true)) {
                continue;
            }
            $items[] = new AreaNavItem(
                PerformancePages::label($label),
                $this->urlGenerator->generate($route),
                false,
                ['data-testid' => 'performance-nav-' . $id],
            );
        }

        $nav     = new AreaNav($items, PerformancePages::label('ui.nav.label'));
        $current = $context['current'] ?? null;

        return is_string($current) && $current !== '' ? $nav->withActive($current) : $nav;
    }

    /**
     * Navigation with the entry of a page active (`index`, `statistics`, ...).
     */
    public function forPage(string $page): AreaNav
    {
        $route = self::ITEMS[$page][0] ?? self::ITEMS['index'][0];

        /** @var AreaNav $nav */
        $nav = $this->areaNav(self::AREA, ['current' => $this->urlGenerator->generate($route)]);

        return $nav;
    }
}
