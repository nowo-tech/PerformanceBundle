<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Integration\Routing;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\Loader\AttributeServicesLoader;
use Symfony\Component\Routing\RouterInterface;

use function array_filter;
use function array_keys;
use function array_values;
use function assert;
use function str_starts_with;

/**
 * REQ-SF-004 rule 7: the dashboard routes are opt-in. The host's default `routing.controllers` resource must not
 * expose them (they exist only through the documented routes.yaml import, under web_ui.path_prefix; the web tests
 * serve the dashboard through that import).
 */
final class OptInRoutesTest extends TestCase
{
    protected function setUp(): void
    {
        $this->removeKernelCache();
    }

    protected function tearDown(): void
    {
        $this->removeKernelCache();
    }

    public function testRoutingControllersResourceDoesNotRegisterBundleRoutes(): void
    {
        if (!class_exists(AttributeServicesLoader::class)) {
            self::markTestSkipped('The routing.controllers resource needs symfony/routing >= 7.3.');
        }

        $kernel = new OptInRoutesKernel('test', true);
        $kernel->boot();

        try {
            $router = $kernel->getContainer()->get('router');
            assert($router instanceof RouterInterface);
            $bundleRoutes = array_values(array_filter(
                array_keys($router->getRouteCollection()->all()),
                static fn (string $name): bool => str_starts_with($name, 'nowo_performance'),
            ));
            self::assertSame([], $bundleRoutes, 'Bundle routes must only exist when the host imports routes.yaml.');
        } finally {
            $kernel->shutdown();
        }
    }

    private function removeKernelCache(): void
    {
        (new Filesystem())->remove(__DIR__ . '/../var/cache/opt-in-routes');
    }
}
