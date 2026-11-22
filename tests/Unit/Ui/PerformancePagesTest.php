<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Ui;

use LogicException;
use Nowo\PerformanceBundle\Ui\PerformancePages;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

use function defined;

final class PerformancePagesTest extends TestCase
{
    public function testDefaultsUseTheUiKitLayoutAndTheBundleTemplates(): void
    {
        $pages = new PerformancePages();

        self::assertNull($pages->layout());
        self::assertSame(PerformancePages::TEMPLATES['index'], $pages->template('index'));
        self::assertSame('unknown', $pages->template('unknown'));
        self::assertSame(['page' => 1], $pages->context(['page' => 1]));

        $label = PerformancePages::label('dashboard.title', ['%x%' => 1]);
        self::assertSame('dashboard.title', $label->key);
        self::assertSame(PerformancePages::DOMAIN, $label->domain);
        self::assertSame(['%x%' => 1], $label->params);
    }

    public function testTemplateOverridesAndFormerDefaultLayout(): void
    {
        $pages = new PerformancePages(PerformancePages::FORMER_DEFAULT_LAYOUT, ['index' => 'perf/index.html.twig', 'diagnose' => '']);

        self::assertNull($pages->layout());
        self::assertSame('perf/index.html.twig', $pages->template('index'));
        self::assertSame(PerformancePages::TEMPLATES['diagnose'], $pages->template('diagnose'));
        self::assertNull((new PerformancePages(''))->layout());
    }

    public function testCanonicalLayoutIsUsedAsIs(): void
    {
        $twig  = new Environment(new ArrayLoader(['admin.html.twig' => '{% block nowo_ui_content %}{% endblock %}']));
        $pages = new PerformancePages('admin.html.twig', [], $twig);

        self::assertSame('admin.html.twig', $pages->layout());
        // Without Twig the configured layout is trusted as a canonical one.
        self::assertSame('base.html.twig', (new PerformancePages('base.html.twig'))->layout());
    }

    public function testUnknownLayoutIsLeftToTwig(): void
    {
        $pages = new PerformancePages('missing.html.twig', [], new Environment(new ArrayLoader([])));

        self::assertSame('missing.html.twig', $pages->layout());
    }

    public function testLayoutWithoutTheCanonicalBlocksFailsClearly(): void
    {
        $twig  = new Environment(new ArrayLoader(['base.html.twig' => '{% block body %}{% endblock %}']));
        $pages = new PerformancePages('base.html.twig', [], $twig);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The layout "base.html.twig" (nowo_performance.web_ui.layout_template) does not render the UiKit canonical blocks');

        $pages->layout();
    }

    public function testTheLayoutBridgeIsGone(): void
    {
        self::assertFalse(defined(PerformancePages::class . '::LEGACY_LAYOUT_BRIDGE'));
        self::assertFalse(method_exists(PerformancePages::class, 'legacyLayout'));
        self::assertFileDoesNotExist(__DIR__ . '/../../../src/Resources/views/Performance/_legacy_layout.html.twig');
    }
}
