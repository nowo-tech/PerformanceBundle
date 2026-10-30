<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\DBAL;

use Composer\InstalledVersions;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use ReflectionClass;
use ReflectionException;
use TypeError;

/**
 * Registry that applies QueryTrackingMiddleware using different methods
 * depending on the Doctrine version.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class QueryTrackingMiddlewareRegistry
{
    /**
     * Apply middleware to a connection using the appropriate method for the Doctrine version.
     *
     * @param ManagerRegistry $registry The Doctrine registry
     * @param string $connectionName The connection name
     * @param QueryTrackingMiddleware $middleware The middleware instance
     *
     * @return bool True if middleware was applied successfully, false otherwise
     */
    public static function applyMiddleware(
        ManagerRegistry $registry,
        string $connectionName,
        QueryTrackingMiddleware $middleware,
    ): bool {
        // Method 1: Try reflection-based approach (works with DoctrineBundle 3.x)
        if (self::applyMiddlewareViaReflection($registry, $connectionName, $middleware)) {
            return true;
        }

        // Method 2: Try connection wrapper approach (fallback)
        return self::applyMiddlewareViaConnectionWrapper($registry, $connectionName);
    }

    /**
     * Apply middleware using reflection to access and wrap the driver.
     *
     * This method works with DoctrineBundle 3.x where the driver is a private property.
     *
     * @param ManagerRegistry $registry The Doctrine registry
     * @param string $connectionName The connection name
     * @param QueryTrackingMiddleware $middleware The middleware instance
     *
     * @return bool True if successful
     */
    private static function applyMiddlewareViaReflection(
        ManagerRegistry $registry,
        string $connectionName,
        QueryTrackingMiddleware $middleware,
    ): bool {
        try {
            $connection = $registry->getConnection($connectionName);

            if (!$connection instanceof Connection) {
                return false;
            }

            $reflection = new ReflectionClass($connection);

            // Try to find the driver property (may be named differently in different versions)
            // In DBAL 3.x, the driver is typically stored in a private property
            $driverPropertyNames = ['driver', '_driver', 'wrappedConnection', '_conn'];

            foreach ($driverPropertyNames as $propertyName) {
                if (!$reflection->hasProperty($propertyName)) {
                    continue;
                }

                try {
                    $driverProperty = $reflection->getProperty($propertyName);
                    $originalDriver = $driverProperty->getValue($connection);

                    if ($originalDriver instanceof Driver) {
                        // Check if already wrapped (avoid double wrapping)
                        $driverClass = $originalDriver::class;
                        if (str_contains($driverClass, 'AbstractDriverMiddleware')
                            || str_contains($driverClass, 'QueryTracking')) {
                            // Already wrapped, return true to indicate it's working
                            return true;
                        }

                        // Wrap the driver with our middleware
                        $wrappedDriver = $middleware->wrap($originalDriver);
                        $driverProperty->setValue($connection, $wrappedDriver);

                        // New connections created by the wrapped driver are tracked; the current internal driver
                        // connection (if already open) is left as is.
                        return true;
                    }
                } catch (ReflectionException) {
                    // Continue to next property name
                    continue;
                } catch (TypeError) {
                    // Property exists but type doesn't match, continue
                    continue;
                }
            }

            // If we couldn't find the driver property, try accessing via parent class
            $parentClass = $reflection->getParentClass();
            if ($parentClass !== false) {
                foreach ($driverPropertyNames as $propertyName) {
                    if (!$parentClass->hasProperty($propertyName)) {
                        continue;
                    }

                    try {
                        $driverProperty = $parentClass->getProperty($propertyName);
                        $originalDriver = $driverProperty->getValue($connection);

                        if ($originalDriver instanceof Driver) {
                            $driverClass = $originalDriver::class;
                            if (str_contains($driverClass, 'AbstractDriverMiddleware')
                                || str_contains($driverClass, 'QueryTracking')) {
                                return true;
                            }

                            $wrappedDriver = $middleware->wrap($originalDriver);
                            $driverProperty->setValue($connection, $wrappedDriver);

                            return true;
                        }
                    } catch (ReflectionException|TypeError) {
                        continue;
                    }
                }
            }
        } catch (Exception) {
            // Silently fail
        }

        return false;
    }

    /**
     * Apply middleware by wrapping the connection itself.
     *
     * This method wraps the connection object to intercept queries at the connection level.
     * This is needed when the connection is already created and cached.
     *
     * @param ManagerRegistry $registry The Doctrine registry
     * @param string $connectionName The connection name
     *
     * @return bool True if successful
     */
    private static function applyMiddlewareViaConnectionWrapper(
        ManagerRegistry $registry,
        string $connectionName,
    ): bool {
        try {
            $connection = $registry->getConnection($connectionName);

            if (!$connection instanceof Connection) {
                return false;
            }

            // Check if connection is already a QueryTrackingConnection
            $connectionClass = $connection::class;

            return str_contains($connectionClass, 'QueryTrackingConnection')
                  // Already wrapped

            // We can't easily wrap an existing connection object
            // The connection is already created and may be in use
            // This method is a placeholder for future implementation
            // For now, we rely on the driver wrapping method

            ;
        } catch (Exception) {
            // Silently fail
        }

        return false;
    }

    /**
     * Detect DoctrineBundle version.
     *
     * @return string|null The installed version (Composer runtime data), else "3.0.0" (never null since DoctrineBundle is a hard dependency; nullable for BC)
     */
    public static function detectDoctrineBundleVersion(): ?string
    {
        // Composer runtime data (DoctrineBundle is a hard dependency, so it is normally listed there)
        if (class_exists(InstalledVersions::class)) {
            try {
                $version = InstalledVersions::getVersion('doctrine/doctrine-bundle');
                if ($version !== null) {
                    // Remove 'v' prefix if present
                    return ltrim($version, 'v');
                }
            } catch (Exception) {
                // Not registered with this Composer autoloader
            }
        }

        // Unknown to Composer (custom autoloader): assume the 3.x API (the supported 2.8+ / 3.x lines share it)
        return '3.0.0';
    }

    /**
     * Check if DoctrineBundle version supports YAML middleware configuration.
     *
     * NOTE: This method now returns false by default because YAML middleware
     * configuration is not reliably available across all DoctrineBundle versions.
     * We use reflection-based middleware application instead, which works
     * consistently across all versions.
     *
     * @return bool True if YAML configuration is supported
     */
    public static function supportsYamlMiddlewareConfig(): bool
    {
        // Disabled: YAML middleware configuration is not reliably available
        // across all DoctrineBundle versions, even within the same major version.
        // We use reflection-based middleware application via QueryTrackingConnectionSubscriber
        // which works consistently across all versions (2.x and 3.x).
        return false;
    }

    /**
     * Check if DoctrineBundle version supports yamlMiddleware configuration.
     *
     * NOTE: This method now returns false by default because yamlMiddleware
     * is not reliably available across all DoctrineBundle 2.x versions.
     * We use 'middlewares' instead, which is more widely supported.
     *
     * @return bool True if yamlMiddleware configuration is supported
     */
    public static function supportsYamlMiddleware(): bool
    {
        // Disabled: yamlMiddleware is not reliably available across all versions
        // Even in versions that should support it (2.10.0+), it may not be recognized
        // We'll use 'middlewares' instead, which is more widely supported
        return false;
    }
}
