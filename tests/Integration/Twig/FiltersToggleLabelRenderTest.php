<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Integration\Twig;

use Nowo\PerformanceBundle\Form\PerformanceFiltersType;
use Nowo\PerformanceBundle\Tests\Integration\TestKernel;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Twig\Environment;

/**
 * Regression: the "Show/Hide Advanced Filters" toggle rendered both labels at once.
 * Only the label matching the initial collapse state may be visible.
 */
final class FiltersToggleLabelRenderTest extends KernelTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', true);
    }

    protected function tearDown(): void
    {
        self::ensureKernelShutdown();
        parent::tearDown();
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>}>
     */
    public static function templateProvider(): iterable
    {
        yield 'component template' => ['@NowoPerformanceBundle/components/Filters.html.twig', ['ui_template' => 'bootstrap']];
        yield 'include template' => ['@NowoPerformanceBundle/Performance/components/_filters_bootstrap.html.twig', []];
    }

    /**
     * @param array<string, mixed> $context
     */
    #[DataProvider('templateProvider')]
    public function testCollapsedByDefaultShowsOnlyShowLabel(string $template, array $context): void
    {
        $crawler = $this->render($template, $context, []);

        $button = $crawler->filterXPath('//button[@data-bs-target="#advancedFilters"]');
        self::assertCount(1, $button);
        self::assertSame('false', $button->attr('aria-expanded'));
        self::assertStringContainsString('display: none', (string) $button->filterXPath('.//span[@class="when-expanded"]')->attr('style'));
        self::assertNull($button->filterXPath('.//span[@class="when-collapsed"]')->attr('style'));
        self::assertNotContains('show', explode(' ', (string) $crawler->filterXPath('//*[@id="advancedFilters"]')->attr('class')));
    }

    /**
     * @param array<string, mixed> $context
     */
    #[DataProvider('templateProvider')]
    public function testExpandedWhenAdvancedFilterSetShowsOnlyHideLabel(string $template, array $context): void
    {
        $crawler = $this->render($template, $context, ['current_min_request_time' => 0.5]);

        $button = $crawler->filterXPath('//button[@data-bs-target="#advancedFilters"]');
        self::assertSame('true', $button->attr('aria-expanded'));
        self::assertNull($button->filterXPath('.//span[@class="when-expanded"]')->attr('style'));
        self::assertStringContainsString('display: none', (string) $button->filterXPath('.//span[@class="when-collapsed"]')->attr('style'));
        self::assertContains('show', explode(' ', (string) $crawler->filterXPath('//*[@id="advancedFilters"]')->attr('class')));
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $formOptions
     */
    private function render(string $template, array $context, array $formOptions): Crawler
    {
        self::bootKernel();
        $container = self::getContainer();
        /** @var FormFactoryInterface $formFactory */
        $formFactory = $container->get('form.factory');
        /** @var Environment $twig */
        $twig = $container->get('twig');

        $form = $formFactory->create(PerformanceFiltersType::class, null, $formOptions)->createView();
        $html = $twig->render($template, $context + ['form' => $form]);

        return new Crawler($html);
    }
}
