<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Integration\Contract;

use Nowo\PerformanceBundle\Form\PerformanceFiltersType;
use Nowo\PerformanceBundle\Tests\Support\PageFixtures;
use Nowo\UiKitBundle\Test\PageContractTestCase;
use Nowo\UiKitBundle\Test\PageFixture;

/**
 * REQ-UI-003 page contract: the dashboard pages extend UiKit scaffolds (list, dashboard, show); no <html>, CDN,
 * inline handler or confirm(); framework classes only through theme slots; the stub theme is reflected; no raw or
 * missing translation keys (strict rule); the CSRF forms of the actions keep their hidden fields.
 */
final class PageContractTest extends PageContractTestCase
{
    use PerformanceContractEnvironment;

    public static function pageFixtures(): iterable
    {
        yield 'routes' => new PageFixture('@NowoPerformanceBundle/Performance/index.html.twig', PageFixtures::index());
        yield 'routes (empty, no record management)' => new PageFixture('@NowoPerformanceBundle/Performance/index.html.twig', PageFixtures::index(true, false));
        yield 'statistics' => new PageFixture('@NowoPerformanceBundle/Performance/statistics.html.twig', PageFixtures::statistics());
        yield 'statistics (no data)' => new PageFixture('@NowoPerformanceBundle/Performance/statistics.html.twig', PageFixtures::statistics(true));
        yield 'access statistics' => new PageFixture('@NowoPerformanceBundle/Performance/access_statistics.html.twig', PageFixtures::accessStatistics());
        yield 'access statistics (no data, no record management)' => new PageFixture('@NowoPerformanceBundle/Performance/access_statistics.html.twig', PageFixtures::accessStatistics(true, false));
        yield 'access records' => new PageFixture('@NowoPerformanceBundle/Performance/access_records.html.twig', PageFixtures::accessRecords());
        yield 'access records (empty, no record management)' => new PageFixture('@NowoPerformanceBundle/Performance/access_records.html.twig', PageFixtures::accessRecords(true, false));
        yield 'diagnose' => new PageFixture('@NowoPerformanceBundle/Performance/diagnose.html.twig', PageFixtures::diagnose());
        yield 'diagnose (healthy)' => new PageFixture('@NowoPerformanceBundle/Performance/diagnose.html.twig', PageFixtures::diagnose(true));
    }

    /**
     * The deprecated Twig components render through the slot-based partials: no framework class, inline handler or CDN.
     */
    public function testDeprecatedComponentTemplatesRender(): void
    {
        $twig    = $this->createTwig('none');
        $index   = PageFixtures::index();
        $routes  = $index['page']->rows;
        $filters = PageFixtures::formFactory()->create(PerformanceFiltersType::class, null, ['with_submit' => false])->createView();
        $stats   = ['total_routes' => 2, 'avg_request_time' => 0.5, 'max_request_time' => 1.0, 'avg_query_time' => 0.1, 'max_query_time' => 0.2, 'avg_queries' => 3.0, 'max_queries' => 5, 'total_records' => 9];

        $html = $twig->render('@NowoPerformanceBundle/components/Statistics.html.twig', ['stats' => $stats, 'enableAccessRecords' => true, 'environment' => 'dev']);
        self::assertStringContainsString('nowo-ui-kpi', $html);
        self::assertStringContainsString('nowo_performance.access_records', $html);

        $html .= $twig->render('@NowoPerformanceBundle/components/Filters.html.twig', ['form' => $filters]);
        $html .= $twig->render('@NowoPerformanceBundle/components/RoutesTable.html.twig', ['routes' => $routes, 'thresholds' => []]);
        $html .= $twig->render('@NowoPerformanceBundle/components/RoutesTable.html.twig', ['routes' => []]);
        $html .= $twig->render('@NowoPerformanceBundle/components/Charts.html.twig', ['chartDataUrl' => '/performance/api/chart-data', 'environment' => 'dev', 'currentRoute' => null]);

        self::assertStringContainsString('app_product_edit', $html);
        self::assertStringContainsString('/performance/api/chart-data?env=dev', $html);
        self::assertDoesNotMatchRegularExpression('/\son[a-z]+=|confirm\(|cdn\.|class="[^"]*(?<![\w-])(btn|table|card|form-control|bg-[a-z]+)(?![\w-])/', $html);
    }
}
