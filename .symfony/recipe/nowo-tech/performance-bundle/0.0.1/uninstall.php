<?php

/**
 * Symfony Flex uninstall hook for Performance Bundle.
 *
 * This script is automatically executed when the bundle is removed via:
 * composer remove nowo-tech/performance-bundle
 *
 * It removes:
 * - Bundle registration from config/bundles.php
 * - Configuration file config/packages/nowo_performance.yaml
 */

use Symfony\Flex\Recipe;

/**
 * @param Recipe $recipe
 * @return void
 */
function uninstall(Recipe $recipe): void
{
    // Get the project root directory using reflection or direct access
    // The Recipe class has a private $projectDir property
    $reflection = new \ReflectionClass($recipe);
    $projectDirProperty = $reflection->getProperty('projectDir');
    $projectDirProperty->setAccessible(true);
    $projectDir = $projectDirProperty->getValue($recipe);
    
    $configDir = $projectDir . '/config';
    $bundlesFile = $configDir . '/bundles.php';
    $configFile = $configDir . '/packages/nowo_performance.yaml';

    // Remove bundle from bundles.php
    if (file_exists($bundlesFile)) {
        $bundlesContent = file_get_contents($bundlesFile);
        $bundleClass = 'Nowo\\PerformanceBundle\\NowoPerformanceBundle';
        
        // Pattern to match the bundle line (with or without trailing comma)
        // Matches: BundleClass::class => ['all' => true],
        $pattern = '/\s*' . preg_quote($bundleClass, '/') . '::class\s*=>\s*\[.*?\],?\s*/';
        
        $newContent = preg_replace($pattern, '', $bundlesContent);
        
        // Clean up extra blank lines (more than 2 consecutive)
        $newContent = preg_replace('/\n{3,}/', "\n\n", $newContent);
        
        // Only write if content changed
        if ($newContent !== $bundlesContent) {
            file_put_contents($bundlesFile, $newContent);
            echo "  ✓ Removed bundle from config/bundles.php\n";
        }
    }

    // Remove configuration file
    if (file_exists($configFile)) {
        unlink($configFile);
        echo "  ✓ Removed config/packages/nowo_performance.yaml\n";
    }

    // Remove dev-specific config if exists
    $devConfigFile = $configDir . '/packages/dev/nowo_performance.yaml';
    if (file_exists($devConfigFile)) {
        unlink($devConfigFile);
        echo "  ✓ Removed config/packages/dev/nowo_performance.yaml\n";
    }
}
