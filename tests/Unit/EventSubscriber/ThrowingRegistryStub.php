<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\EventSubscriber;

use BadMethodCallException;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use Exception;

/**
 * Stub ManagerRegistry that throws from getConnection() to trigger the subscriber's catch block.
 * Implements only the methods used by QueryTrackingMiddlewareRegistry::applyMiddleware (getConnection).
 */
final class ThrowingRegistryStub implements ManagerRegistry
{
    public function __construct(
        private readonly string $message = 'connection error',
    ) {
    }

    public function getConnection(?string $name = null): object
    {
        throw new Exception($this->message);
    }

    public function getConnections(): array
    {
        return [];
    }

    public function getConnectionNames(): array
    {
        return [];
    }

    public function getDefaultConnectionName(): string
    {
        return 'default';
    }

    public function getManager(?string $name = null): ObjectManager
    {
        throw new BadMethodCallException('Not implemented');
    }

    public function getManagers(): array
    {
        return [];
    }

    public function getManagerNames(): array
    {
        return [];
    }

    public function getDefaultManagerName(): string
    {
        return 'default';
    }

    public function getManagerForClass(string $class): ?ObjectManager
    {
        return null;
    }

    public function resetManager(?string $name = null): ObjectManager
    {
        throw new BadMethodCallException('Not implemented');
    }

    public function getRepository(string $class, ?string $bundle = null): ObjectRepository
    {
        throw new BadMethodCallException('Not implemented');
    }

    public function getAliasNamespace(string $alias): string
    {
        return '';
    }
}
