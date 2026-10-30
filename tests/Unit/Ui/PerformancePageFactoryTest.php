<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Ui;

use ArrayObject;
use DateTimeImmutable;
use Nowo\PerformanceBundle\Form\PerformanceFiltersType;
use Nowo\PerformanceBundle\Tests\Support\PageFixtures;
use Nowo\PerformanceBundle\Ui\PerformancePageFactory;
use Nowo\PerformanceBundle\Ui\PerformancePages;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\DashboardPage;
use Nowo\UiKitBundle\View\DetailItem;
use Nowo\UiKitBundle\View\DetailSection;
use Nowo\UiKitBundle\View\Label;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\Pagination;
use Nowo\UiKitBundle\View\ShowPage;
use PHPUnit\Framework\TestCase;

/**
 * The view models the controller renders (the page contract test renders the same fixtures through Twig).
 */
final class PerformancePageFactoryTest extends TestCase
{
    public function testIndexWithRecordManagementAndReview(): void
    {
        $page = PageFixtures::index()['page'];

        self::assertInstanceOf(ListPage::class, $page);
        self::assertSame(PerformancePages::BUNDLE, $page->bundle);
        self::assertSame(['export', 'export', 'delete'], array_map(static fn (Action $a): string => $a->kind, $page->header->actions));
        self::assertSame('POST', $page->header->actions[2]->method);
        self::assertNotNull($page->header->actions[2]->confirm);
        self::assertSame('/nowo_performance.export_csv?env=dev', $page->header->actions[0]->href);
        self::assertContains('review', array_map(static fn (Column $c): string => $c->key, $page->columns));
        self::assertContains('statusCodes', array_map(static fn (Column $c): string => $c->key, $page->columns));

        $rowActions = $page->rowActions($page->rows[0]);
        self::assertSame(['view', 'edit', 'delete'], array_map(static fn (Action $a): string => $a->kind, $rowActions));
        self::assertSame(PerformancePageFactory::REVIEW_DIALOG_PREFIX . '1', $rowActions[1]->dialogId);
        self::assertSame('/nowo_performance.access_records?route=app_home&env=dev', $rowActions[0]->href);
        self::assertSame('/nowo_performance.delete?id=1', $rowActions[2]->href);
        self::assertInstanceOf(Label::class, $rowActions[1]->label);
        self::assertSame('routes_table.edit_review', $rowActions[1]->label->key);
        self::assertSame('routes_table.mark_as_reviewed', $page->rowActions($page->rows[1])[1]->label?->key ?? null);
    }

    public function testIndexWithoutManagementOrStatusCodes(): void
    {
        $factory = PageFixtures::factory(accessRecords: false);
        $route   = PageFixtures::route(3, 'app_x', 'GET', 0.01, 1, 0.001, 1024, 1, []);
        $filters = PageFixtures::formFactory()->create(PerformanceFiltersType::class, null, ['with_submit' => false])->createView();
        $page    = $factory->index([$route], 'prod', new Pagination(1, 10, 1), $filters, trackStatusCodes: false);

        self::assertSame(['export', 'export'], array_map(static fn (Action $a): string => $a->kind, $page->header->actions));
        self::assertSame([], $page->rowActions($route));
        self::assertNotContains('statusCodes', array_map(static fn (Column $c): string => $c->key, $page->columns));
        self::assertNotContains('review', array_map(static fn (Column $c): string => $c->key, $page->columns));
        self::assertCount(3, $page->header->tabs?->items ?? []);
    }

    public function testIndexKpis(): void
    {
        $factory = PageFixtures::factory();
        $stats   = ['total_routes' => 1200, 'avg_request_time' => 0.0421, 'max_request_time' => 1.5, 'avg_query_time' => '0.002', 'max_query_time' => 'n/a', 'avg_queries' => 3.25, 'max_queries' => 9, 'total_records' => 50];

        $tiles = $factory->indexKpis($stats, 'dev', true);
        self::assertCount(5, $tiles);
        self::assertSame('1,200', $tiles[0]->value);
        self::assertSame('42.10 ms', $tiles[1]->value);
        self::assertSame('2.00 ms', $tiles[2]->value);
        self::assertInstanceOf(Label::class, $tiles[2]->hint);
        self::assertSame(['%value%' => '0.00 ms'], $tiles[2]->hint->params);
        self::assertSame('3.3', $tiles[3]->value);
        self::assertTrue($tiles[4]->linkTile);
        self::assertSame('/nowo_performance.access_records?env=dev', $tiles[4]->href);
        self::assertCount(4, $factory->indexKpis([], 'dev'));
    }

    public function testStatistics(): void
    {
        $page = PageFixtures::statistics()['page'];

        self::assertInstanceOf(DashboardPage::class, $page);
        self::assertCount(4, $page->kpis);
        self::assertSame('warning', $page->kpis[3]->variant);
        self::assertSame('1,250.00 ms', $page->kpis[1]->hint instanceof Label ? $page->kpis[1]->hint->params['%value%'] : null);
        self::assertSame('success', PageFixtures::statistics(true)['page']->kpis[3]->variant);
    }

    public function testAccessStatistics(): void
    {
        $page = PageFixtures::accessStatistics()['page'];

        self::assertInstanceOf(DashboardPage::class, $page);
        self::assertSame(['view', 'export', 'export', 'delete'], array_map(static fn (Action $a): string => $a->kind, $page->header->actions));
        self::assertSame('13:00', $page->kpis[2]->value);
        self::assertSame('2026-10-23 00:00', $page->kpis[1]->value);

        $empty = PageFixtures::accessStatistics(true, false)['page'];
        self::assertSame(['view', 'export', 'export'], array_map(static fn (Action $a): string => $a->kind, $empty->header->actions));
        self::assertSame('—', $empty->kpis[2]->value);
        self::assertNull($empty->kpis[2]->hint);
        self::assertSame('0.00 ms', $empty->kpis[3]->value);

        $tiles = PageFixtures::factory()->accessStatistics(3, new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-01-02'), [['hour' => 3, 'count' => 1, 'avg_response_time' => 0.1], ['hour' => 4, 'count' => 2, 'avg_response_time' => 0.4]])->kpis;
        self::assertSame('04:00', $tiles[2]->value);
        self::assertSame('300.00 ms', $tiles[3]->value);
    }

    public function testAccessRecords(): void
    {
        $page = PageFixtures::accessRecords()['page'];

        self::assertInstanceOf(ListPage::class, $page);
        self::assertSame(['export', 'export', 'delete', 'custom'], array_map(static fn (Action $a): string => $a->kind, $page->header->actions));
        self::assertSame(PerformancePageFactory::PURGE_DIALOG_ID, $page->header->actions[3]->dialogId);
        self::assertSame('accessedAt', $page->table->sort);
        $sortable = array_map(static fn (Column $c): string => $c->key, array_filter($page->columns, static fn (Column $c): bool => $c->sortable));
        self::assertSame(array_keys(PerformancePageFactory::RECORD_SORTS), array_values($sortable));
        self::assertSame('0.001', $page->filterFields['min_query_time']->placeholder);

        $plain = PageFixtures::accessRecords(true, false)['page'];
        self::assertSame(['export', 'export'], array_map(static fn (Action $a): string => $a->kind, $plain->header->actions));
    }

    public function testRecordSortMapping(): void
    {
        self::assertSame('response_time', PerformancePageFactory::recordSortKey('responseTime'));
        self::assertNull(PerformancePageFactory::recordSortKey('referer'));
        self::assertNull(PerformancePageFactory::recordSortKey(null));
        self::assertSame('statusCode', PerformancePageFactory::recordSortColumn('status_code'));
        self::assertNull(PerformancePageFactory::recordSortColumn('unknown'));
        self::assertNull(PerformancePageFactory::recordSortColumn(null));
    }

    public function testDiagnose(): void
    {
        $page = PageFixtures::diagnose()['page'];

        self::assertInstanceOf(ShowPage::class, $page);
        self::assertSame('1', $page->attributes['data-issues']);
        // route_tracking is empty: no section
        self::assertSame(['diagnose-configuration', 'diagnose-environment', 'diagnose-database_connection', 'diagnose-table_status', 'diagnose-data_status', 'diagnose-subscriber_status'], array_map(static fn (DetailSection $s): ?string => $s->id, $page->sections));

        $items = [];
        foreach ($page->sections as $section) {
            foreach ($section->items as $item) {
                self::assertInstanceOf(DetailItem::class, $item);
                $items[(string) $item->label] = $item;
            }
        }
        self::assertSame('success', $items['bundle_enabled']->badge);
        self::assertInstanceOf(Label::class, $items['bundle_enabled']->value);
        self::assertSame('_wdt*, _profiler*', $items['ignore_routes']->value);
        self::assertSame('—', $items['allowed_environments']->value);
        self::assertSame('—', $items['error']->value);
        self::assertSame('{"enabled":true,"sampled":false}', $this->findItem($page, 'tracking_conditions')->value);
        self::assertSame('{"dev":2}', $items['records_by_environment']->value);
        self::assertSame('2026-10-23 08:00:00', $items['first_record_date']->value);
        self::assertSame(1.0, $items['sampling_rate']->value);

        $odd = PageFixtures::factory()->diagnose(['configuration' => ['flags' => [true, false], 'object' => new ArrayObject([1])], 'issues' => 'not a list']);
        self::assertSame('0', $odd->attributes['data-issues']);
        self::assertSame('true, false', $odd->sections[0]->items[0]->value);
        self::assertSame('{"0":1}', $odd->sections[0]->items[1]->value);
    }

    private function findItem(ShowPage $page, string $key): DetailItem
    {
        foreach ($page->sections as $section) {
            foreach ($section->items as $item) {
                if ($item->label === $key) {
                    return $item;
                }
            }
        }

        self::fail('No item ' . $key);
    }
}
