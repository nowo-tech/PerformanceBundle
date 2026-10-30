<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Integration\Contract;

use function dirname;

/**
 * Twig environment of the UiKit page contract test for the bundle: bundle views and catalogue, the Symfony form
 * layout (the pages render the bundle forms), strict missing-translation rule.
 *
 * @phpstan-require-extends \Nowo\UiKitBundle\Test\PageContractTestCase
 */
trait PerformanceContractEnvironment
{
    protected static function templatePaths(): array
    {
        return ['NowoPerformanceBundle' => dirname(__DIR__, 3) . '/src/Resources/views'];
    }

    protected static function translationDirs(): array
    {
        return [dirname(__DIR__, 3) . '/src/Resources/translations'];
    }

    protected static function templatesDocument(): string
    {
        return dirname(__DIR__, 3) . '/docs/TEMPLATES.md';
    }

    protected static function formThemes(): array
    {
        return ['form_div_layout.html.twig'];
    }

    protected static function strictMissingTranslations(): bool
    {
        return true;
    }
}
