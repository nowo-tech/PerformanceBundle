<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\DependencyInjection;

use LogicException;
use Nowo\PerformanceBundle\DependencyInjection\Configuration;
use Nowo\PerformanceBundle\DependencyInjection\PerformanceExtension;
use Nowo\PerformanceBundle\Ui\PerformancePages;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * REQ-UI-003 canonical web_ui keys, the deprecated dashboard.* bridges and the UiKit css_framework seed.
 */
final class WebUiConfigurationTest extends TestCase
{
    public function testWebUiDefaults(): void
    {
        $config = $this->process([]);

        self::assertSame(['enabled' => true, 'path_prefix' => '/performance', 'layout_template' => null, 'templates' => []], $config['web_ui']);
        self::assertNull($config['dashboard']['layout_template']);
    }

    public function testCanonicalKeys(): void
    {
        $config = $this->process(['web_ui' => [
            'enabled'         => false,
            'path_prefix'     => '/admin/perf',
            'layout_template' => 'layouts/admin.html.twig',
            'templates'       => ['index' => 'perf/index.html.twig'],
        ]]);

        self::assertSame(['enabled' => false, 'path_prefix' => '/admin/perf', 'layout_template' => 'layouts/admin.html.twig', 'templates' => ['index' => 'perf/index.html.twig']], $config['web_ui']);
    }

    public function testFormerDefaultLayoutAndEmptyLayoutMeanUiKitLayout(): void
    {
        self::assertNull($this->process(['web_ui' => ['layout_template' => PerformancePages::FORMER_DEFAULT_LAYOUT]])['web_ui']['layout_template']);
        self::assertNull($this->process(['web_ui' => ['layout_template' => '']])['web_ui']['layout_template']);
    }

    public function testUnknownTemplatePageIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Unknown nowo_performance.web_ui.templates page(s) "board"');

        $this->process(['web_ui' => ['templates' => ['board' => 'x.html.twig']]]);
    }

    #[IgnoreDeprecations]
    public function testDeprecatedDashboardKeysAreMapped(): void
    {
        $config = $this->process(['dashboard' => ['enabled' => false, 'prefix' => '/admin', 'path' => '/metrics', 'layout_template' => 'base.html.twig']]);

        self::assertFalse($config['web_ui']['enabled']);
        self::assertSame('/admin/metrics', $config['web_ui']['path_prefix']);
        self::assertSame('base.html.twig', $config['web_ui']['layout_template']);

        self::assertSame('/x/performance', $this->process(['dashboard' => ['prefix' => '/x']])['web_ui']['path_prefix']);
        self::assertSame('/only', $this->process(['dashboard' => ['path' => '/only']])['web_ui']['path_prefix']);
    }

    #[IgnoreDeprecations]
    public function testCanonicalKeysWinOverDeprecatedOnes(): void
    {
        $config = $this->process([
            'dashboard' => ['enabled' => false, 'path' => '/old', 'layout_template' => 'old.html.twig'],
            'web_ui'    => ['enabled' => true, 'path_prefix' => '/new', 'layout_template' => 'new.html.twig'],
        ]);

        self::assertTrue($config['web_ui']['enabled']);
        self::assertSame('/new', $config['web_ui']['path_prefix']);
        self::assertSame('new.html.twig', $config['web_ui']['layout_template']);
    }

    public function testNullRootConfiguration(): void
    {
        self::assertSame('/performance', (new Processor())->processConfiguration(new Configuration(), [null])['web_ui']['path_prefix']);
    }

    public function testDashboardWithoutUiKeysAndNullDashboard(): void
    {
        self::assertSame('/performance', $this->process(['dashboard' => ['enable_record_management' => true]])['web_ui']['path_prefix']);
        self::assertTrue($this->process(['dashboard' => null])['web_ui']['enabled']);
    }

    #[IgnoreDeprecations]
    public function testDeprecatedTemplateAliases(): void
    {
        self::assertSame('tailwind', $this->process(['dashboard' => ['template' => 'tailwind']])['dashboard']['css_framework']);
        self::assertSame('bootstrap5', $this->process(['dashboard' => ['css_framework' => 'bootstrap']])['dashboard']['css_framework']);

        $this->expectException(InvalidConfigurationException::class);
        $this->process(['dashboard' => ['template' => 'foundation6']]);
    }

    public function testExtensionSetsWebUiParametersAndRoutePrefix(): void
    {
        $container = $this->container();
        (new PerformanceExtension())->load([['web_ui' => ['path_prefix' => '/admin/perf', 'layout_template' => 'layouts/admin.html.twig', 'templates' => ['diagnose' => 'perf/diagnose.html.twig']]]], $container);

        self::assertSame('/admin/perf', $container->getParameter('nowo_performance.web_ui.path_prefix'));
        self::assertSame('/admin/perf', $container->getParameter('nowo_performance.dashboard.path'));
        self::assertSame('', $container->getParameter('nowo_performance.dashboard.prefix'));
        self::assertSame('layouts/admin.html.twig', $container->getParameter('nowo_performance.web_ui.layout_template'));
        self::assertSame('layouts/admin.html.twig', $container->getParameter('nowo_performance.dashboard.layout_template'));
        self::assertSame(['diagnose' => 'perf/diagnose.html.twig'], $container->getParameter('nowo_performance.web_ui.templates'));
        self::assertTrue($container->hasDefinition(PerformancePages::class));
    }

    public function testAccessCheckerUsesTheAuthorizationCheckerWhenRegistered(): void
    {
        $container = $this->container();
        $container->register('security.authorization_checker', stdClass::class);
        (new PerformanceExtension())->load([[]], $container);

        $argument = $container->getDefinition('nowo_performance.access_checker.default')->getArgument('$authorizationChecker');
        self::assertInstanceOf(Reference::class, $argument);
        self::assertSame('security.authorization_checker', (string) $argument);
    }

    public function testSecurityBundleIsDetectedThroughKernelBundles(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => stdClass::class]);
        (new PerformanceExtension())->load([[]], $container);
        self::assertTrue($container->hasParameter('nowo_performance.web_ui.enabled'));

        $this->expectException(LogicException::class);
        $bare = new ContainerBuilder();
        $bare->setParameter('kernel.bundles', []);
        (new PerformanceExtension())->load([[]], $bare);
    }

    public function testPrependDoesNotSeedUiKitWithoutDeprecatedFramework(): void
    {
        $container = $this->container(true);
        $container->prependExtensionConfig('nowo_performance', ['web_ui' => ['path_prefix' => '/p']]);

        (new PerformanceExtension())->prepend($container);

        self::assertSame([], $container->getExtensionConfig('nowo_ui_kit'));
    }

    #[IgnoreDeprecations]
    public function testPrependSeedsUiKitFromTheDeprecatedTemplateKey(): void
    {
        $container = $this->container(true);
        $container->prependExtensionConfig('nowo_ui_kit', ['icon_set' => 'none']);
        $container->prependExtensionConfig('nowo_performance', ['dashboard' => ['template' => 'tailwind']]);

        (new PerformanceExtension())->prepend($container);

        self::assertSame([['css_framework' => 'tailwind'], ['icon_set' => 'none']], $container->getExtensionConfig('nowo_ui_kit'));
    }

    #[IgnoreDeprecations]
    public function testPrependKeepsTheHostUiKitFramework(): void
    {
        $container = $this->container(true);
        $container->prependExtensionConfig('nowo_ui_kit', ['css_framework' => 'none']);
        $container->prependExtensionConfig('nowo_performance', ['dashboard' => ['css_framework' => 'tabler']]);

        (new PerformanceExtension())->prepend($container);

        self::assertSame([['css_framework' => 'none']], $container->getExtensionConfig('nowo_ui_kit'));
    }

    #[IgnoreDeprecations]
    public function testPrependSeedsTablerIcons(): void
    {
        $container = $this->container(true);
        $container->prependExtensionConfig('nowo_performance', ['dashboard' => ['css_framework' => 'tabler']]);

        (new PerformanceExtension())->prepend($container);

        self::assertSame([['css_framework' => 'tabler', 'icon_set' => 'tabler-icons']], $container->getExtensionConfig('nowo_ui_kit'));
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function process(array $config): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [$config]);
    }

    private function container(bool $withUiKit = false): ContainerBuilder
    {
        $container = new ContainerBuilder();
        foreach ($withUiKit ? ['security', 'nowo_ui_kit', 'nowo_performance'] : ['security'] as $alias) {
            $extension = $alias === 'nowo_performance' ? new PerformanceExtension() : $this->createStub(ExtensionInterface::class);
            if (!$extension instanceof PerformanceExtension) {
                $extension->method('getAlias')->willReturn($alias);
            }
            $container->registerExtension($extension);
        }

        return $container;
    }
}
