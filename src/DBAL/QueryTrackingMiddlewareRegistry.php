<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\DBAL;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Registry that applies QueryTrackingMiddleware using different methods
 * depending on the Doctrine version.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
class QueryTrackingMiddlewareRegistry
{
    /**
     * Apply middleware to a connection using the appropriate method for the Doctrine version.
     *
     * @param ManagerRegistry         $registry       The Doctrine registry
     * @param string                  $connectionName The connection name
     * @param QueryTrackingMiddleware $middleware     The middleware instance
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

        // Method 2: Try driver wrapper approach (works with DBAL 3.x)
        if (self::applyMiddlewareViaDriverWrapper($registry, $connectionName, $middleware)) {
            return true;
        }

        // Method 3: Try connection wrapper approach (fallback)
        if (self::applyMiddlewareViaConnectionWrapper($registry, $connectionName, $middleware)) {
            return true;
        }

        return false;
    }

    /**
     * Apply middleware using reflection to access and wrap the driver.
     *
     * This method works with DoctrineBundle 3.x where the driver is a private property.
     *
     * @param ManagerRegistry         $registry       The Doctrine registry
     * @param string                  $connectionName The connection name
     * @param QueryTrackingMiddleware $middleware     The middleware instance
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

            $reflection = new \ReflectionClass($connection);

            // Try to find the driver property (may be named differently in different versions)
            // In DBAL 3.x, the driver is typically stored in a private property
            $driverPropertyNames = ['driver', '_driver', 'wrappedConnection', '_conn'];

            foreach ($driverPropertyNames as $propertyName) {
                if (!$reflection->hasProperty($propertyName)) {
                    continue;
                }

                try {
                    $driverProperty = $reflection->getProperty($propertyName);
                    $driverProperty->setAccessible(true);
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

                        // IMPORTANT: After wrapping the driver, the connection object itself
                        // is already created. The driver's connect() method will return
                        // QueryTrackingConnection for NEW connections, but the current
                        // connection object won't be affected.
                        //
                        // We need to wrap the connection's internal connection object.
                        // In DBAL, the Connection object has an internal connection that
                        // is created by the driver. We need to replace that with a
                        // QueryTrackingConnection wrapper.

                        // Try to find and wrap the internal connection
                        $internalConnectionPropertyNames = ['_conn', 'connection', '_connection'];
                        foreach ($internalConnectionPropertyNames as $connPropName) {
                            if ($reflection->hasProperty($connPropName)) {
                                try {
                                    $connProperty = $reflection->getProperty($connPropName);
                                    $connProperty->setAccessible(true);
                                    $internalConn = $connProperty->getValue($connection);

                                    // Check if it's already a QueryTrackingConnection
                                    if ($internalConn instanceof QueryTrackingConnection) {
                                        return true; // Already wrapped
                                    }

                                    // Wrap the internal connection
                                    if ($internalConn instanceof Connection) {
                                        $wrappedConn = new QueryTrackingConnection($internalConn);
                                        $connProperty->setValue($connection, $wrappedConn);

                                        return true;
                                    }
                                } catch (\ReflectionException $e) {
                                    continue;
                                } catch (\TypeError $e) {
                                    continue;
                                }
                            }
                        }

                        // If we couldn't wrap the internal connection, at least the driver is wrapped
                        // which means new connections will be tracked
                        return true;
                    }
                } catch (\ReflectionException $e) {
                    // Continue to next property name
                    continue;
                } catch (\TypeError $e) {
                    // Property exists but type doesn't match, continue
                    continue;
                }
            }

            // If we couldn't find the driver property, try accessing via parent class
            $parentClass = $reflection->getParentClass();
            if (false !== $parentClass) {
                foreach ($driverPropertyNames as $propertyName) {
                    if (!$parentClass->hasProperty($propertyName)) {
                        continue;
                    }

                    try {
                        $driverProperty = $parentClass->getProperty($propertyName);
                        $driverProperty->setAccessible(true);
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
                    } catch (\ReflectionException $e) {
                        continue;
                    } catch (\TypeError $e) {
                        continue;
                    }
                }
            }
        } catch (\Exception $e) {
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
     * @param ManagerRegistry         $registry       The Doctrine registry
     * @param string                  $connectionName The connection name
     * @param QueryTrackingMiddleware $middleware     The middleware instance
     *
     * @return bool True if successful
     */
    private static function applyMiddlewareViaConnectionWrapper(
        ManagerRegistry $registry,
        string $connectionName,
        QueryTrackingMiddleware $middleware,
    ): bool {
        try {
            $connection = $registry->getConnection($connectionName);

            if (!$connection instanceof Connection) {
                return false;
            }

            // Check if connection is already a QueryTrackingConnection
            $connectionClass = $connection::class;
            if (str_contains($connectionClass, 'QueryTrackingConnection')) {
                return true; // Already wrapped
            }

            // We can't easily wrap an existing connection object
            // The connection is already created and may be in use
            // This method is a placeholder for future implementation
            // For now, we rely on the driver wrapping method

            return false;
        } catch (\Exception $e) {
            // Silently fail
        }

        return false;
    }

    /**
     * Detect DoctrineBundle version.
     *
     * @return string|null The version string or null if not available
     */
    public static function detectDoctrineBundleVersion(): ?string
    {
        if (!class_exists(\Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class)) {
            return null;
        }

        // Method 1: Try to get version from installed packages (Composer)
        if (class_exists(\Composer\InstalledVersions::class)) {
            try {
                $version = \Composer\InstalledVersions::getVersion('doctrine/doctrine-bundle');
                if (null !== $version) {
                    // Remove 'v' prefix if present
                    return ltrim($version, 'v');
                }
            } catch (\Exception $e) {
                // Continue to next method
            }
        }

        // Method 2: Try to read from composer.json
        try {
            $reflection = new \ReflectionClass(\Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class);
            $filename = $reflection->getFileName();

            if (false !== $filename) {
                $composerPath = \dirname($filename, 2).'/composer.json';
                if (file_exists($composerPath)) {
                    $composer = json_decode(file_get_contents($composerPath), true);
                    if (isset($composer['version'])) {
                        return ltrim($composer['version'], 'v');
                    }
                }
            }
        } catch (\Exception $e) {
            // Silently fail
        }

        // Method 3: Try to detect by checking available methods/classes
        // DoctrineBundle 3.x has different structure than 2.x
        if (class_exists(\Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension::class)) {
            try {
                $reflection = new \ReflectionClass(\Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension::class);
                // Check if it has methods that indicate version 3.x
                if ($reflection->hasMethod('getConfiguration')) {
                    // This is a heuristic - version 3.x typically has this structure
                    return '3.0.0'; // Assume 3.x
                }
            } catch (\Exception $e) {
                // Silently fail
            }
        }

        return null;
    }

    /**
     * Check if DoctrineBundle version supports YAML middleware configuration.
     *
     * @return bool True if YAML configuration is supported
     */
    public static function supportsYamlMiddlewareConfig(): bool
    {
        $version = self::detectDoctrineBundleVersion();

        if (null === $version) {
            return false;
        }

        // DoctrineBundle 2.x supports middlewares in YAML
        // DoctrineBundle 3.x does not support it
        return version_compare($version, '3.0.0', '<');
    }
}
