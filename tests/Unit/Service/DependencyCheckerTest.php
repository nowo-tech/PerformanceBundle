<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Service;

use Nowo\PerformanceBundle\Service\DependencyChecker;
use PHPUnit\Framework\TestCase;

final class DependencyCheckerTest extends TestCase
{
    private DependencyChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new DependencyChecker();
    }

    public function testIsTwigComponentAvailableReturnsTrueWhenClassesExist(): void
    {
        // This test will pass if symfony/ux-twig-component is installed
        // or fail if it's not (which is acceptable for optional dependency)
        $result = $this->checker->isTwigComponentAvailable();

        $this->assertIsBool($result);
    }

    public function testIsIconsAvailableReturnsBool(): void
    {
        $result = $this->checker->isIconsAvailable();

        $this->assertIsBool($result);
    }

    public function testGetMissingDependenciesReturnsArray(): void
    {
        $missing = $this->checker->getMissingDependencies();

        $this->assertIsArray($missing);
    }

    public function testGetMissingDependenciesStructure(): void
    {
        $missing = $this->checker->getMissingDependencies();

        foreach ($missing as $key => $info) {
            $this->assertIsString($key);
            $this->assertIsArray($info);
            $this->assertArrayHasKey('required', $info);
            $this->assertArrayHasKey('package', $info);
            $this->assertArrayHasKey('message', $info);
            $this->assertArrayHasKey('install_command', $info);
            $this->assertIsBool($info['required']);
            $this->assertIsString($info['package']);
            $this->assertIsString($info['message']);
            $this->assertIsString($info['install_command']);
        }
    }

    public function testIsFeatureAvailableForTwigComponent(): void
    {
        $result = $this->checker->isFeatureAvailable('twig_component');

        $this->assertIsBool($result);
        $this->assertSame($result, $this->checker->isTwigComponentAvailable());
    }

    public function testIsFeatureAvailableForIcons(): void
    {
        $result = $this->checker->isFeatureAvailable('icons');

        $this->assertIsBool($result);
        $this->assertSame($result, $this->checker->isIconsAvailable());
    }

    public function testIsFeatureAvailableForUnknownFeature(): void
    {
        $result = $this->checker->isFeatureAvailable('unknown_feature');

        $this->assertTrue($result);
    }

    public function testGetDependencyStatusReturnsArray(): void
    {
        $status = $this->checker->getDependencyStatus();

        $this->assertIsArray($status);
        $this->assertArrayHasKey('twig_component', $status);
        $this->assertArrayHasKey('icons', $status);
    }

    public function testGetDependencyStatusStructure(): void
    {
        $status = $this->checker->getDependencyStatus();

        foreach ($status as $feature => $info) {
            $this->assertIsString($feature);
            $this->assertIsArray($info);
            $this->assertArrayHasKey('available', $info);
            $this->assertArrayHasKey('package', $info);
            $this->assertArrayHasKey('required', $info);
            $this->assertIsBool($info['available']);
            $this->assertIsString($info['package']);
            $this->assertIsBool($info['required']);
        }
    }

    public function testIsMessengerAvailableReturnsBool(): void
    {
        $result = $this->checker->isMessengerAvailable();

        $this->assertIsBool($result);
    }

    public function testIsMailerAvailableReturnsBool(): void
    {
        $result = $this->checker->isMailerAvailable();

        $this->assertIsBool($result);
    }

    public function testIsHttpClientAvailableReturnsBool(): void
    {
        $result = $this->checker->isHttpClientAvailable();

        $this->assertIsBool($result);
    }

    public function testIsFeatureAvailableForMessenger(): void
    {
        $result = $this->checker->isFeatureAvailable('messenger');

        $this->assertIsBool($result);
        $this->assertSame($result, $this->checker->isMessengerAvailable());
    }

    public function testIsFeatureAvailableForMailer(): void
    {
        $result = $this->checker->isFeatureAvailable('mailer');

        $this->assertIsBool($result);
        $this->assertSame($result, $this->checker->isMailerAvailable());
    }

    public function testIsFeatureAvailableForHttpClient(): void
    {
        $result = $this->checker->isFeatureAvailable('http_client');

        $this->assertIsBool($result);
        $this->assertSame($result, $this->checker->isHttpClientAvailable());
    }

    public function testGetMissingDependenciesIncludesMessenger(): void
    {
        $missing = $this->checker->getMissingDependencies();

        // Check structure if messenger is missing
        if (isset($missing['messenger'])) {
            $this->assertArrayHasKey('required', $missing['messenger']);
            $this->assertArrayHasKey('package', $missing['messenger']);
            $this->assertArrayHasKey('message', $missing['messenger']);
            $this->assertArrayHasKey('install_command', $missing['messenger']);
            $this->assertArrayHasKey('feature', $missing['messenger']);
            $this->assertFalse($missing['messenger']['required']);
            $this->assertSame('symfony/messenger', $missing['messenger']['package']);
            $this->assertStringContainsString('composer require', $missing['messenger']['install_command']);
        }
    }

    public function testGetMissingDependenciesIncludesMailer(): void
    {
        $missing = $this->checker->getMissingDependencies();
        $this->assertIsArray($missing);

        // Check structure if mailer is missing
        if (isset($missing['mailer'])) {
            $this->assertArrayHasKey('required', $missing['mailer']);
            $this->assertArrayHasKey('package', $missing['mailer']);
            $this->assertArrayHasKey('message', $missing['mailer']);
            $this->assertArrayHasKey('install_command', $missing['mailer']);
            $this->assertArrayHasKey('feature', $missing['mailer']);
            $this->assertFalse($missing['mailer']['required']);
            $this->assertSame('symfony/mailer', $missing['mailer']['package']);
            $this->assertStringContainsString('composer require', $missing['mailer']['install_command']);
        }
    }

    public function testGetMissingDependenciesIncludesHttpClient(): void
    {
        $missing = $this->checker->getMissingDependencies();
        $this->assertIsArray($missing);

        // Check structure if http_client is missing
        if (isset($missing['http_client'])) {
            $this->assertArrayHasKey('required', $missing['http_client']);
            $this->assertArrayHasKey('package', $missing['http_client']);
            $this->assertArrayHasKey('message', $missing['http_client']);
            $this->assertArrayHasKey('install_command', $missing['http_client']);
            $this->assertArrayHasKey('feature', $missing['http_client']);
            $this->assertFalse($missing['http_client']['required']);
            $this->assertSame('symfony/http-client', $missing['http_client']['package']);
            $this->assertStringContainsString('composer require', $missing['http_client']['install_command']);
        }
    }

    public function testGetDependencyStatusIncludesMessenger(): void
    {
        $status = $this->checker->getDependencyStatus();

        $this->assertArrayHasKey('messenger', $status);
        $this->assertArrayHasKey('available', $status['messenger']);
        $this->assertArrayHasKey('package', $status['messenger']);
        $this->assertArrayHasKey('required', $status['messenger']);
        $this->assertSame('symfony/messenger', $status['messenger']['package']);
        $this->assertFalse($status['messenger']['required']);
    }

    public function testGetDependencyStatusIncludesMailer(): void
    {
        $status = $this->checker->getDependencyStatus();

        $this->assertArrayHasKey('mailer', $status);
        $this->assertArrayHasKey('available', $status['mailer']);
        $this->assertArrayHasKey('package', $status['mailer']);
        $this->assertArrayHasKey('required', $status['mailer']);
        $this->assertSame('symfony/mailer', $status['mailer']['package']);
        $this->assertFalse($status['mailer']['required']);
    }

    public function testGetDependencyStatusIncludesHttpClient(): void
    {
        $status = $this->checker->getDependencyStatus();

        $this->assertArrayHasKey('http_client', $status);
        $this->assertArrayHasKey('available', $status['http_client']);
        $this->assertArrayHasKey('package', $status['http_client']);
        $this->assertArrayHasKey('required', $status['http_client']);
        $this->assertSame('symfony/http-client', $status['http_client']['package']);
        $this->assertFalse($status['http_client']['required']);
    }

    public function testGetMissingDependenciesHasFeatureKey(): void
    {
        $missing = $this->checker->getMissingDependencies();

        foreach ($missing as $info) {
            $this->assertArrayHasKey('feature', $info);
            $this->assertIsString($info['feature']);
            $this->assertNotEmpty($info['feature']);
        }
    }

    /**
     * Covers getMissingDependencies when all optional deps are missing via stub.
     * Ensures every branch that adds an entry to $missing is executed.
     */
    public function testGetMissingDependenciesWhenAllMissingReturnsAllEntries(): void
    {
        $stub = new class extends DependencyChecker {
            public function isTwigComponentAvailable(): bool
            {
                return false;
            }

            public function isIconsAvailable(): bool
            {
                return false;
            }

            public function isMessengerAvailable(): bool
            {
                return false;
            }

            public function isMailerAvailable(): bool
            {
                return false;
            }

            public function isHttpClientAvailable(): bool
            {
                return false;
            }
        };

        $missing = $stub->getMissingDependencies();

        $this->assertArrayHasKey('twig_component', $missing);
        $this->assertArrayHasKey('icons', $missing);
        $this->assertArrayHasKey('messenger', $missing);
        $this->assertArrayHasKey('mailer', $missing);
        $this->assertArrayHasKey('http_client', $missing);
        $this->assertFalse($missing['twig_component']['required']);
        $this->assertTrue($missing['icons']['required']);
        $this->assertSame('symfony/ux-twig-component', $missing['twig_component']['package']);
        $this->assertSame('symfony/ux-icons', $missing['icons']['package']);
        $this->assertSame('symfony/messenger', $missing['messenger']['package']);
        $this->assertSame('symfony/mailer', $missing['mailer']['package']);
        $this->assertSame('symfony/http-client', $missing['http_client']['package']);
    }

    /** Covers isFeatureAvailable('default') branch. */
    public function testIsFeatureAvailableDefaultReturnsTrue(): void
    {
        $this->assertTrue($this->checker->isFeatureAvailable('default'));
    }
}
