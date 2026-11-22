<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Ui;

use LogicException;
use Nowo\UiKitBundle\View\Label;
use Throwable;
use Twig\Environment;

use function in_array;
use function is_string;
use function sprintf;

/**
 * Page plumbing of the UiKit page contract (REQ-UI-003) for the performance dashboard (`web_ui`).
 *
 * Resolves the layout the page scaffolds extend (`web_ui.layout_template`, null = `nowo_ui_kit.layouts.admin`; the
 * former bundle default layout means null), refuses a 3.14 layout (no UiKit canonical blocks: a project
 * `base.html.twig` with `body` / `stylesheets` / `javascripts`; the 3.15 bridge for it was removed in 4.0), and
 * resolves the per-page template overrides (`web_ui.templates.<page>`). Configuration only: no request state, safe in
 * worker mode.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class PerformancePages
{
    /** Bundle name of the page view models (theme exceptions `nowo_ui_kit.theme.bundles.NowoPerformanceBundle`). */
    public const BUNDLE = 'NowoPerformanceBundle';

    /** Translation domain of the bundle labels. */
    public const DOMAIN = 'NowoPerformanceBundle';

    /** Page ids accepted by `web_ui.templates` (page id => default template). */
    public const TEMPLATES = [
        'index'             => '@NowoPerformanceBundle/Performance/index.html.twig',
        'statistics'        => '@NowoPerformanceBundle/Performance/statistics.html.twig',
        'access_statistics' => '@NowoPerformanceBundle/Performance/access_statistics.html.twig',
        'access_records'    => '@NowoPerformanceBundle/Performance/access_records.html.twig',
        'diagnose'          => '@NowoPerformanceBundle/Performance/diagnose.html.twig',
    ];

    /** Former bundle default layout (removed in 3.15; configured explicitly it now means "UiKit layout"). */
    public const FORMER_DEFAULT_LAYOUT = '@NowoPerformanceBundle/Performance/layout.html.twig';

    /** UiKit admin layout (value of the Twig global nowo_performance_layout_template when no layout is set). */
    public const UI_KIT_ADMIN_LAYOUT = '@NowoUiKit/layout/admin.html.twig';

    /** A layout that defines this block renders the UiKit canonical blocks. */
    private const CANONICAL_BLOCK = 'nowo_ui_content';

    /**
     * @param array<string, string> $templates `web_ui.templates` (page id => template)
     * @param Environment|null $twig refuses a 3.14 layout (null: the configured layout is used as is)
     */
    public function __construct(
        private ?string $layoutTemplate = null,
        private array $templates = [],
        private ?Environment $twig = null,
    ) {
    }

    /**
     * Translation key of the bundle domain as a UiKit label.
     *
     * @param array<string, mixed> $params
     */
    public static function label(string $key, array $params = []): Label
    {
        return new Label($key, self::DOMAIN, $params);
    }

    /**
     * Template of a page: the `web_ui.templates.<page>` override, else the bundle template.
     */
    public function template(string $page): string
    {
        $override = $this->templates[$page] ?? null;

        return is_string($override) && $override !== '' ? $override : self::TEMPLATES[$page] ?? $page;
    }

    /**
     * Layout the page scaffolds extend (`page.layout`): the configured layout, else null (UiKit `nowo_ui_layout.admin`).
     *
     * @throws LogicException when the configured layout does not render the UiKit canonical blocks (a 3.14 layout)
     */
    public function layout(): ?string
    {
        $layout = $this->configuredLayout();
        if ($layout !== null && $this->twig instanceof Environment) {
            $this->assertCanonicalLayout($this->twig, $layout);
        }

        return $layout;
    }

    /**
     * Page variables (the shared ones of 3.x are gone with the layout bridge; kept so page factories keep one call).
     *
     * @param array<string, mixed> $vars page variables
     *
     * @return array<string, mixed>
     */
    public function context(array $vars): array
    {
        return $vars;
    }

    /**
     * A layout that does not define the UiKit canonical blocks would render the dashboard pages empty: refuse it. A
     * layout that cannot be loaded is left to Twig, which reports it when the page extends it.
     */
    private function assertCanonicalLayout(Environment $twig, string $layout): void
    {
        try {
            $canonical = $twig->load($layout)->hasBlock(self::CANONICAL_BLOCK, $twig->getGlobals());
        } catch (Throwable) {
            return;
        }

        if (!$canonical) {
            throw new LogicException(sprintf('The layout "%s" (nowo_performance.web_ui.layout_template) does not render the UiKit canonical blocks (nowo_ui_page_header, nowo_ui_content, ...). The bridge that wrapped such a 3.14 layout (Performance/_legacy_layout.html.twig, deprecated since 3.15.0) was removed in 4.0: render the canonical blocks (or extend @NowoUiKit/layout/admin.html.twig), or set web_ui.layout_template to null to use nowo_ui_kit.layouts.admin; see docs/UPGRADING.md "To 4.0.0".', $layout));
        }
    }

    private function configuredLayout(): ?string
    {
        $layout = $this->layoutTemplate;

        return in_array($layout, [null, '', self::FORMER_DEFAULT_LAYOUT], true) ? null : $layout;
    }
}
