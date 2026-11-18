<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Support;

use Doctrine\DBAL\Connection;

use function str_starts_with;

/**
 * A DBAL connection (wrapperClass) that runs a test callback right before a statement starting with a given prefix:
 * the moment a second connection plays a live worker in the race tests. A hook returning false is removed.
 */
class HookedConnection extends Connection
{
    /** @var array<string, callable(): (bool|null)> statement prefix => callback */
    public static array $hooks = [];

    public function executeStatement(string $sql, array $params = [], array $types = []): int|string
    {
        foreach (self::$hooks as $prefix => $hook) {
            if (str_starts_with($sql, $prefix) && $hook() === false) {
                unset(self::$hooks[$prefix]);
            }
        }

        return parent::executeStatement($sql, $params, $types);
    }
}
