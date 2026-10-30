<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Controller;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\ServerVersionProvider;
use RuntimeException;

/**
 * Driver exposing the DBAL 3 getName(); a null name throws (the controller then tries the next detection method).
 */
final readonly class NamedTestDriver implements Driver
{
    public function __construct(private ?string $name)
    {
    }

    public function getName(): string
    {
        return $this->name ?? throw new RuntimeException('no name');
    }

    public function connect(array $params): DriverConnection
    {
        throw new RuntimeException('not used');
    }

    public function getDatabasePlatform(ServerVersionProvider $versionProvider): AbstractPlatform
    {
        throw new RuntimeException('not used');
    }

    public function getExceptionConverter(): ExceptionConverter
    {
        throw new RuntimeException('not used');
    }
}
