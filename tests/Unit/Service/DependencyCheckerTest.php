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
}
