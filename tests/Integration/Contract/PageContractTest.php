<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Integration\Contract;

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

    public function testRemovedComponentTemplatesAreGone(): void
    {
        $twig = $this->createTwig('none');

        foreach (['Statistics', 'Filters', 'RoutesTable', 'Charts'] as $component) {
            self::assertFalse($twig->getLoader()->exists('@NowoPerformanceBundle/components/' . $component . '.html.twig'), $component . ' (removed in 4.0)');
        }
        self::assertFalse($twig->getLoader()->exists('@NowoPerformanceBundle/Performance/_legacy_layout.html.twig'));
    }
}
