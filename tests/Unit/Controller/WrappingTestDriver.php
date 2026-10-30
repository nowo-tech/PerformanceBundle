<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Controller;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\ServerVersionProvider;

/**
 * Middleware-like driver keeping the wrapped driver in a private `driver` property (no getName() of its own).
 */
final readonly class WrappingTestDriver implements Driver
{
    public function __construct(private Driver $driver)
    {
    }

    public function connect(array $params): DriverConnection
    {
        return $this->driver->connect($params);
    }

    public function getDatabasePlatform(ServerVersionProvider $versionProvider): AbstractPlatform
    {
        return $this->driver->getDatabasePlatform($versionProvider);
    }

    public function getExceptionConverter(): ExceptionConverter
    {
        return $this->driver->getExceptionConverter();
    }
}
