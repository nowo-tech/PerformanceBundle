<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Ui;

use Nowo\UiKitBundle\View\Label;
use Throwable;
use Twig\Environment;

use function in_array;
use function is_string;
use function trigger_deprecation;

/**
 * Page plumbing of the UiKit page contract (REQ-UI-003) for the performance dashboard (`web_ui`).
 *
 * Resolves the layout the page scaffolds extend (`web_ui.layout_template`, null = `nowo_ui_kit.layouts.admin`; the
 * former bundle default layout means null), wraps a 3.14 layout (no UiKit canonical blocks: a project `base.html.twig`
 * with `body` / `stylesheets` / `javascripts`) in a deprecated bridge, and resolves the per-page template overrides
 * (`web_ui.templates.<page>`). Configuration only: no request state, safe in worker mode.
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

    /** Deprecated BC bridge around a 3.14 layout (removed in 4.0). */
    public const LEGACY_LAYOUT_BRIDGE = '@NowoPerformanceBundle/Performance/_legacy_layout.html.twig';

    /** Former bundle default layout (removed in 3.15; configured explicitly it now means "UiKit layout"). */
    public const FORMER_DEFAULT_LAYOUT = '@NowoPerformanceBundle/Performance/layout.html.twig';

    /** UiKit admin layout (value of the deprecated Twig global nowo_performance_layout_template when no layout is set). */
    public const UI_KIT_ADMIN_LAYOUT = '@NowoUiKit/layout/admin.html.twig';

    /** Variable of the legacy bridge: the wrapped 3.14 layout. */
    public const LEGACY_LAYOUT_VARIABLE = 'nowo_performance_legacy_layout';

    /** A layout that defines this block renders the UiKit canonical blocks. */
    private const CANONICAL_BLOCK = 'nowo_ui_content';

    /**
     * @param array<string, string> $templates `web_ui.templates` (page id => template)
     * @param Environment|null $twig detects a 3.14 layout (null: the configured layout is used as is)
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
     * Layout the page scaffolds extend (`page.layout`): the configured layout, the legacy bridge for a 3.14 layout,
     * else null (UiKit `nowo_ui_layout.admin`).
     */
    public function layout(): ?string
    {
        return $this->legacyLayout() !== null ? self::LEGACY_LAYOUT_BRIDGE : $this->configuredLayout();
    }

    /**
     * Page variables plus the shared ones: the 3.14 layout wrapped by the deprecated bridge (null when none).
     *
     * @param array<string, mixed> $vars page variables
     *
     * @return array<string, mixed>
     */
    public function context(array $vars): array
    {
        return $vars + [self::LEGACY_LAYOUT_VARIABLE => $this->legacyLayout()];
    }

    /**
     * The configured layout when it is a 3.14 layout (it does not define the UiKit canonical blocks), else null.
     */
    public function legacyLayout(): ?string
    {
        $layout = $this->configuredLayout();
        if ($layout === null || !$this->twig instanceof Environment) {
            return null;
        }

        try {
            $canonical = $this->twig->load($layout)->hasBlock(self::CANONICAL_BLOCK, $this->twig->getGlobals());
        } catch (Throwable) {
            return null;
        }

        if ($canonical) {
            return null;
        }

        trigger_deprecation('nowo-tech/performance-bundle', '3.15.0', 'The layout "%s" does not render the UiKit canonical blocks (nowo_ui_page_header, nowo_ui_content, ...); it is wrapped in the deprecated bridge "%s" (blocks body, stylesheets, javascripts). Render the canonical blocks (or extend @NowoUiKit/layout/admin.html.twig) or set web_ui.layout_template to null to use nowo_ui_kit.layouts.admin; see docs/UPGRADING.md.', $layout, self::LEGACY_LAYOUT_BRIDGE);

        return $layout;
    }

    private function configuredLayout(): ?string
    {
        $layout = $this->layoutTemplate;

        return in_array($layout, [null, '', self::FORMER_DEFAULT_LAYOUT], true) ? null : $layout;
    }
}
