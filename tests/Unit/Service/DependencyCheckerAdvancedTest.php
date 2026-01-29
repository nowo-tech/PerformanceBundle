<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Service;

use Nowo\PerformanceBundle\Service\DependencyChecker;
use PHPUnit\Framework\TestCase;

/**
 * Advanced tests for DependencyChecker.
 */
final class DependencyCheckerAdvancedTest extends TestCase
{
    private DependencyChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new DependencyChecker();
    }

    public function testIsTwigComponentAvailableReturnsBool(): void
    {
        $result = $this->checker->isTwigComponentAvailable();

        $this->assertIsBool($result);
    }

    public function testIsIconsAvailableReturnsBool(): void
    {
        $result = $this->checker->isIconsAvailable();

        $this->assertIsBool($result);
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
            $this->assertArrayHasKey('feature', $info);
            $this->assertIsBool($info['required']);
            $this->assertIsString($info['package']);
            $this->assertIsString($info['message']);
            $this->assertIsString($info['install_command']);
            $this->assertIsString($info['feature']);
        }
    }

    public function testIsFeatureAvailableForTwigComponent(): void
    {
        $result = $this->checker->isFeatureAvailable('twig_component');

        $this->assertIsBool($result);
    }

    public function testIsFeatureAvailableForIcons(): void
    {
        $result = $this->checker->isFeatureAvailable('icons');

        $this->assertIsBool($result);
    }

    public function testIsFeatureAvailableForMessenger(): void
    {
        $result = $this->checker->isFeatureAvailable('messenger');

        $this->assertIsBool($result);
    }

    public function testIsFeatureAvailableForMailer(): void
    {
        $result = $this->checker->isFeatureAvailable('mailer');

        $this->assertIsBool($result);
    }

    public function testIsFeatureAvailableForHttpClient(): void
    {
        $result = $this->checker->isFeatureAvailable('http_client');

        $this->assertIsBool($result);
    }

    public function testIsFeatureAvailableForUnknownFeature(): void
    {
        $result = $this->checker->isFeatureAvailable('unknown_feature');

        // Should return true for unknown features (default case)
        $this->assertTrue($result);
    }

    public function testGetDependencyStatusReturnsArray(): void
    {
        $status = $this->checker->getDependencyStatus();

        $this->assertIsArray($status);
    }

    public function testGetDependencyStatusStructure(): void
    {
        $status = $this->checker->getDependencyStatus();

        $this->assertArrayHasKey('twig_component', $status);
        $this->assertArrayHasKey('icons', $status);
        $this->assertArrayHasKey('messenger', $status);
        $this->assertArrayHasKey('mailer', $status);
        $this->assertArrayHasKey('http_client', $status);

        foreach ($status as $key => $info) {
            $this->assertIsString($key);
            $this->assertIsArray($info);
            $this->assertArrayHasKey('available', $info);
            $this->assertArrayHasKey('package', $info);
            $this->assertArrayHasKey('required', $info);
            $this->assertIsBool($info['available']);
            $this->assertIsString($info['package']);
            $this->assertIsBool($info['required']);
        }
    }

    public function testGetDependencyStatusIconsRequiredOthersOptional(): void
    {
        $status = $this->checker->getDependencyStatus();

        $this->assertTrue($status['icons']['required'], 'Icons (symfony/ux-icons) should be required');
        foreach ($status as $key => $info) {
            if ($key !== 'icons') {
                $this->assertFalse($info['required'], "Dependency {$key} should be optional");
            }
        }
    }

    public function testGetMissingDependenciesOnlyIncludesMissing(): void
    {
        $missing = $this->checker->getMissingDependencies();

        // If a dependency is available, it should not be in the missing list
        $status = $this->checker->getDependencyStatus();

        foreach ($status as $key => $info) {
            if ($info['available']) {
                $this->assertArrayNotHasKey($key, $missing, "Available dependency {$key} should not be in missing list");
            }
        }
    }

    public function testIsFeatureAvailableConsistency(): void
    {
        $status = $this->checker->getDependencyStatus();

        foreach ($status as $key => $info) {
            $featureAvailable = $this->checker->isFeatureAvailable($key);
            $this->assertSame($info['available'], $featureAvailable, "Feature {$key} availability should match status");
        }
    }
}
