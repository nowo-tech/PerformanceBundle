<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\DependencyInjection;

use Nowo\PerformanceBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

use function sprintf;

final class ConfigurationTest extends TestCase
{
    private Processor $processor;

    protected function setUp(): void
    {
        $this->processor = new Processor();
    }

    public function testDefaultConfiguration(): void
    {
        $configuration = new Configuration();
        $config        = $this->processor->processConfiguration($configuration, []);

        $this->assertTrue($config['enabled']);
        $this->assertSame(['prod', 'dev', 'test'], $config['environments']);
        $this->assertSame('default', $config['connection']);
        $this->assertSame('routes_data', $config['table_name']);
        $this->assertTrue($config['track_queries']);
        $this->assertTrue($config['track_request_time']);
        $this->assertTrue($config['check_table_status']);
        $this->assertSame(['_wdt', '_profiler', 'web_profiler*', '_error'], $config['ignore_routes']);

        // Dashboard configuration defaults (web_ui / security since 4.0; the dashboard.* UI keys are gone)
        $this->assertTrue($config['web_ui']['enabled']);
        $this->assertSame('/performance', $config['web_ui']['path_prefix']);
        foreach (['enabled', 'path', 'prefix', 'roles', 'css_framework', 'template', 'layout_template'] as $removed) {
            $this->assertArrayNotHasKey($removed, $config['dashboard']);
        }
        $this->assertArrayNotHasKey('query_tracking_threshold', $config);
        $this->assertSame('nowo_performance.cache', $config['cache']['pool']);
        $this->assertSame(['ROLE_ADMIN'], $config['security']['access_roles']);
        $this->assertNull($config['security']['access_checker']);
        $this->assertFalse($config['security']['allow_unauthenticated']);
        $this->assertSame(5000, $config['export']['max_rows']);
    }

    public function testCustomConfiguration(): void
    {
        $configuration = new Configuration();
        $config        = $this->processor->processConfiguration($configuration, [[
            'enabled'            => false,
            'environments'       => ['prod'],
            'connection'         => 'custom_connection',
            'table_name'         => 'custom_table',
            'track_queries'      => false,
            'track_request_time' => false,
            'ignore_routes'      => ['_custom'],
            'web_ui'             => [
                'enabled'     => false,
                'path_prefix' => '/admin/metrics',
            ],
            'security' => [
                'access_roles' => ['ROLE_ADMIN', 'ROLE_PERFORMANCE_VIEWER'],
            ],
        ]]);

        $this->assertFalse($config['enabled']);
        $this->assertSame(['prod'], $config['environments']);
        $this->assertSame('custom_connection', $config['connection']);
        $this->assertSame('custom_table', $config['table_name']);
        $this->assertFalse($config['track_queries']);
        $this->assertFalse($config['track_request_time']);
        $this->assertSame(['_custom'], $config['ignore_routes']);

        // Dashboard configuration
        $this->assertFalse($config['web_ui']['enabled']);
        $this->assertSame('/admin/metrics', $config['web_ui']['path_prefix']);
        $this->assertSame(['ROLE_ADMIN', 'ROLE_PERFORMANCE_VIEWER'], $config['security']['access_roles']);
    }

    public function testPartialConfiguration(): void
    {
        $configuration = new Configuration();
        $config        = $this->processor->processConfiguration($configuration, [[
            'enabled' => false,
        ]]);

        $this->assertFalse($config['enabled']);
        $this->assertSame(['prod', 'dev', 'test'], $config['environments']); // Default
        $this->assertSame('default', $config['connection']); // Default

        // Dashboard should have defaults even when not specified
        $this->assertTrue($config['web_ui']['enabled']);
        $this->assertSame('/performance', $config['web_ui']['path_prefix']);
        $this->assertSame(['ROLE_ADMIN'], $config['security']['access_roles']);
        $this->assertFalse($config['dashboard']['enable_record_management']);
    }

    public function testTrackUserDefaultIsFalse(): void
    {
        $configuration = new Configuration();
        $config        = $this->processor->processConfiguration($configuration, []);

        $this->assertFalse($config['track_user']);
    }

    public function testEnableAccessRecordsDefaultIsFalse(): void
    {
        $configuration = new Configuration();
        $config        = $this->processor->processConfiguration($configuration, []);

        $this->assertFalse($config['enable_access_records']);
    }

    public function testTrackUserCanBeEnabled(): void
    {
        $configuration = new Configuration();
        $config        = $this->processor->processConfiguration($configuration, [[
            'track_user' => true,
        ]]);

        $this->assertTrue($config['track_user']);
    }

    public function testTrackSubRequestsDefaultFalse(): void
    {
        $configuration = new Configuration();
        $config        = $this->processor->processConfiguration($configuration, []);

        $this->assertFalse($config['track_sub_requests']);
    }

    public function testTrackStatusCodesDefault(): void
    {
        $configuration = new Configuration();
        $config        = $this->processor->processConfiguration($configuration, []);

        $this->assertSame([200, 404, 500, 503], $config['track_status_codes']);
    }

    public function testAsyncDefaultFalse(): void
    {
        $configuration = new Configuration();
        $config        = $this->processor->processConfiguration($configuration, []);

        $this->assertFalse($config['async']);
    }

    public function testSamplingRateDefault(): void
    {
        $configuration = new Configuration();
        $config        = $this->processor->processConfiguration($configuration, []);

        $this->assertSame(1.0, $config['sampling_rate']);
    }

    public function testThresholdsDefaults(): void
    {
        $configuration = new Configuration();
        $config        = $this->processor->processConfiguration($configuration, []);

        $this->assertSame(0.5, $config['thresholds']['request_time']['warning']);
        $this->assertSame(1.0, $config['thresholds']['request_time']['critical']);
        $this->assertSame(20, $config['thresholds']['query_count']['warning']);
        $this->assertSame(50, $config['thresholds']['query_count']['critical']);
        $this->assertSame(20.0, $config['thresholds']['memory_usage']['warning']);
        $this->assertSame(50.0, $config['thresholds']['memory_usage']['critical']);
    }

    public function testNotificationsDefaults(): void
    {
        $configuration = new Configuration();
        $config        = $this->processor->processConfiguration($configuration, []);

        $this->assertFalse($config['notifications']['enabled']);
        $this->assertFalse($config['notifications']['email']['enabled']);
        $this->assertSame('noreply@example.com', $config['notifications']['email']['from']);
        $this->assertSame([], $config['notifications']['email']['to']);
        $this->assertFalse($config['notifications']['slack']['enabled']);
        $this->assertSame('', $config['notifications']['slack']['webhook_url']);
    }

    public function testDashboardEnableRecordManagementDefault(): void
    {
        $configuration = new Configuration();
        $config        = $this->processor->processConfiguration($configuration, []);

        $this->assertFalse($config['dashboard']['enable_record_management']);
    }

    public function testConfigurationAlias(): void
    {
        $this->assertSame('nowo_performance', Configuration::ALIAS);
    }

    public function testEnableLoggingDefaultIsTrue(): void
    {
        $configuration = new Configuration();
        $config        = $this->processor->processConfiguration($configuration, []);

        $this->assertTrue($config['enable_logging']);
    }

    public function testNotificationsWebhookDefaults(): void
    {
        $configuration = new Configuration();
        $config        = $this->processor->processConfiguration($configuration, []);

        $this->assertFalse($config['notifications']['webhook']['enabled']);
        $this->assertSame('', $config['notifications']['webhook']['url']);
        $this->assertSame('json', $config['notifications']['webhook']['format']);
        $this->assertSame([], $config['notifications']['webhook']['headers']);
    }

    public function testNotificationsTeamsDefaults(): void
    {
        $configuration = new Configuration();
        $config        = $this->processor->processConfiguration($configuration, []);

        $this->assertFalse($config['notifications']['teams']['enabled']);
        $this->assertSame('', $config['notifications']['teams']['webhook_url']);
    }

    public function testDashboardEnableReviewSystemDefaultIsFalse(): void
    {
        $configuration = new Configuration();
        $config        = $this->processor->processConfiguration($configuration, []);

        $this->assertFalse($config['dashboard']['enable_review_system']);
    }

    public function testDashboardAutoRefreshIntervalDefaultIsZero(): void
    {
        $configuration = new Configuration();
        $config        = $this->processor->processConfiguration($configuration, []);

        $this->assertSame(0, $config['dashboard']['auto_refresh_interval']);
    }

    public function testDashboardEnableRankingQueriesDefaultIsTrue(): void
    {
        $configuration = new Configuration();
        $config        = $this->processor->processConfiguration($configuration, []);

        $this->assertTrue($config['dashboard']['enable_ranking_queries']);
    }

    public function testDashboardDateFormatsDefaults(): void
    {
        $configuration = new Configuration();
        $config        = $this->processor->processConfiguration($configuration, []);

        $this->assertSame('Y-m-d H:i:s', $config['dashboard']['date_formats']['datetime']);
        $this->assertSame('Y-m-d H:i', $config['dashboard']['date_formats']['date']);
    }

    public function testIgnoreRoutesDefaultContainsProfiler(): void
    {
        $configuration = new Configuration();
        $config        = $this->processor->processConfiguration($configuration, []);

        $this->assertContains('_profiler', $config['ignore_routes']);
        $this->assertContains('_wdt', $config['ignore_routes']);
    }

    public function testTableNameDefault(): void
    {
        $configuration = new Configuration();
        $config        = $this->processor->processConfiguration($configuration, []);

        $this->assertSame('routes_data', $config['table_name']);
    }

    public function testEnvironmentsCanIncludeStage(): void
    {
        $configuration = new Configuration();
        $config        = $this->processor->processConfiguration($configuration, [[
            'environments' => ['dev', 'stage', 'prod'],
        ]]);

        $this->assertSame(['dev', 'stage', 'prod'], $config['environments']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string, string}>
     */
    public static function removedKeys(): iterable
    {
        yield 'query_tracking_threshold' => [['query_tracking_threshold' => 5], 'query_tracking_threshold', 'never had any effect'];
        yield 'dashboard.enabled' => [['dashboard' => ['enabled' => false]], 'dashboard.enabled', 'nowo_performance.web_ui.enabled'];
        yield 'dashboard.path' => [['dashboard' => ['path' => '/metrics']], 'dashboard.path', 'nowo_performance.web_ui.path_prefix'];
        yield 'dashboard.prefix' => [['dashboard' => ['prefix' => '/admin']], 'dashboard.prefix', 'nowo_performance.web_ui.path_prefix'];
        yield 'dashboard.layout_template' => [['dashboard' => ['layout_template' => 'base.html.twig']], 'dashboard.layout_template', 'nowo_performance.web_ui.layout_template'];
        yield 'dashboard.css_framework' => [['dashboard' => ['css_framework' => 'tailwind']], 'dashboard.css_framework', 'nowo_ui_kit.css_framework'];
        yield 'dashboard.template' => [['dashboard' => ['template' => 'bootstrap']], 'dashboard.template', 'nowo_ui_kit.css_framework'];
        yield 'dashboard.roles' => [['dashboard' => ['roles' => ['ROLE_ADMIN']]], 'dashboard.roles', 'nowo_performance.security.access_roles'];
    }

    /**
     * 4.0 removes the keys deprecated in 3.x: a leftover key fails loudly with the replacement and UPGRADING.
     *
     * @param array<string, mixed> $config
     */
    #[DataProvider('removedKeys')]
    public function testRemovedKeysFailWithTheirReplacement(array $config, string $path, string $replacement): void
    {
        try {
            $this->processor->processConfiguration(new Configuration(), [$config]);
            $this->fail('The removed key was accepted.');
        } catch (InvalidConfigurationException $e) {
            $this->assertStringContainsString(sprintf('The option "nowo_performance.%s" was removed in 4.0.', $path), $e->getMessage());
            $this->assertStringContainsString($replacement, $e->getMessage());
            $this->assertStringContainsString('UPGRADING.md "To 4.0.0"', $e->getMessage());
        }
    }

    public function testCssFrameworksConstantIsKept(): void
    {
        $this->assertContains('bootstrap5', Configuration::CSS_FRAMEWORKS);
    }

    public function testNonArrayRootConfigurationIsLeftToSymfony(): void
    {
        $config = $this->processor->processConfiguration(new Configuration(), [null]);

        $this->assertTrue($config['enabled']);
    }
}
