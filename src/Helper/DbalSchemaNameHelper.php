<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Helper;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Name;
use Exception;
use ReflectionClass;
use Throwable;

use function is_string;
use function method_exists;
use function trim;

/**
 * Resolves Doctrine DBAL schema asset names without using deprecated APIs.
 *
 * Prefers {@see Name} via getObjectName() (DBAL 4.4+). Falls back to
 * getName() / getQuotedName() on older DBAL versions.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DbalSchemaNameHelper
{
    /**
     * Logical (unquoted) name for comparisons and metadata matching.
     */
    public static function getLogicalName(object $asset): string
    {
        if (method_exists($asset, 'getObjectName')) {
            try {
                $objectName = $asset->getObjectName();
                if ($objectName instanceof Name) {
                    $resolved = trim($objectName->toString(), '`"\'');
                    // Prefer getObjectName when it yields a name. Empty string from an
                    // incomplete test double falls through to legacy getName().
                    if ($resolved !== '' || !method_exists($asset, 'getName')) {
                        return $resolved;
                    }
                }
            } catch (Throwable) {
                // Uninitialized name or incomplete test double — fall through.
            }
        }

        if (method_exists($asset, 'getName')) {
            $name = $asset->getName();

            return trim(is_string($name) ? $name : (string) $name, '`"\'');
        }

        try {
            $reflection   = new ReflectionClass($asset);
            $nameProperty = $reflection->getProperty('name');
            $name         = $nameProperty->getValue($asset);
            if ($name instanceof Name) {
                return trim($name->toString(), '`"\'');
            }

            return trim(is_string($name) ? $name : (string) $name, '`"\'');
        } catch (Exception) {
            return '';
        }
    }

    /**
     * SQL-quoted name for DDL statements.
     */
    public static function getQuotedName(object $asset, Connection|AbstractPlatform $connectionOrPlatform): string
    {
        $platform = $connectionOrPlatform instanceof Connection
            ? $connectionOrPlatform->getDatabasePlatform()
            : $connectionOrPlatform;

        if (method_exists($asset, 'getObjectName')) {
            try {
                $objectName = $asset->getObjectName();
                if ($objectName instanceof Name) {
                    return $objectName->toSQL($platform);
                }
            } catch (Throwable) {
                // Fall through to legacy APIs.
            }
        }

        if (method_exists($asset, 'getQuotedName')) {
            return $asset->getQuotedName($platform);
        }

        return self::getLogicalName($asset);
    }
}
