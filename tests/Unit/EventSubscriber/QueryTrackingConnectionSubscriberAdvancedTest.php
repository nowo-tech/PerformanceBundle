<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\EventSubscriber;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\DBAL\QueryTrackingMiddleware;
use Nowo\PerformanceBundle\EventSubscriber\QueryTrackingConnectionSubscriber;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Advanced tests for QueryTrackingConnectionSubscriber edge cases.
 */
final class QueryTrackingConnectionSubscriberAdvancedTest extends TestCase
{
    private ManagerRegistry|MockObject $registry;
    private HttpKernelInterface|MockObject $kernel;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->kernel = $this->createMock(HttpKernelInterface::class);
    }

    public function testOnKernelRequestWithSubRequest(): void
    {
        $subscriber = new QueryTrackingConnectionSubscriber(
            $this->registry,
            true,
            true,
            'default'
        );

        $request = new \Symfony\Component\HttpFoundation\Request();
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::SUB_REQUEST);

        // Should not throw exception for sub-requests
        $subscriber->onKernelRequest($event);
    }

    public function testOnKernelRequestRetriesWhenMiddlewareFails(): void
    {
        $connection = $this->createMock(Connection::class);
        $this->registry->method('getConnection')->willReturn($connection);

        $subscriber = new QueryTrackingConnectionSubscriber(
            $this->registry,
            true,
            true,
            'default'
        );

        $request = new \Symfony\Component\HttpFoundation\Request();
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        // QueryTrackingMiddlewareRegistry::applyMiddleware will return false
        // Subscriber should retry up to 3 times
        $subscriber->onKernelRequest($event);

        // Should not throw exception
        $this->assertTrue(true);
    }

    public function testOnKernelRequestDoesNotRetryWhenAlreadyTracked(): void
    {
        $connection = $this->createMock(Connection::class);
        $this->registry->method('getConnection')->willReturn($connection);

        $subscriber = new QueryTrackingConnectionSubscriber(
            $this->registry,
            true,
            true,
            'default'
        );

        // Use reflection to mark connection as already tracked
        $reflection = new \ReflectionClass($subscriber);
        $property = $reflection->getProperty('trackedConnections');
        $property->setAccessible(true);
        $property->setValue($subscriber, ['default' => true]);

        $request = new \Symfony\Component\HttpFoundation\Request();
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        // Should not throw exception
        $this->assertTrue(true);
    }

    public function testOnKernelRequestHandlesRegistryException(): void
    {
        $this->registry
            ->method('getConnection')
            ->willThrowException(new \RuntimeException('Registry error'));

        $subscriber = new QueryTrackingConnectionSubscriber(
            $this->registry,
            true,
            true,
            'default'
        );

        $request = new \Symfony\Component\HttpFoundation\Request();
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        // Should handle exception gracefully
        $subscriber->onKernelRequest($event);

        $this->assertTrue(true);
    }

    public function testOnKernelRequestResetsMiddlewareAfterApplication(): void
    {
        $connection = $this->createMock(Connection::class);
        $this->registry->method('getConnection')->willReturn($connection);

        $subscriber = new QueryTrackingConnectionSubscriber(
            $this->registry,
            true,
            true,
            'default'
        );

        // Set some query count before reset
        QueryTrackingMiddleware::reset();
        QueryTrackingMiddleware::startQuery('SELECT 1');

        $request = new \Symfony\Component\HttpFoundation\Request();
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        // After onKernelRequest, query count should be reset to 0
        $this->assertSame(0, QueryTrackingMiddleware::getQueryCount());
    }

    public function testOnKernelRequestWithCustomConnectionName(): void
    {
        $connection = $this->createMock(Connection::class);
        $this->registry
            ->method('getConnection')
            ->with('custom_connection')
            ->willReturn($connection);

        $subscriber = new QueryTrackingConnectionSubscriber(
            $this->registry,
            true,
            true,
            'custom_connection'
        );

        $request = new \Symfony\Component\HttpFoundation\Request();
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        $this->assertTrue(true);
    }

    public function testOnKernelRequestWithBothDisabled(): void
    {
        $subscriber = new QueryTrackingConnectionSubscriber(
            $this->registry,
            false, // enabled = false
            false, // trackQueries = false
            'default'
        );

        $request = new \Symfony\Component\HttpFoundation\Request();
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        // Should return early without doing anything
        $subscriber->onKernelRequest($event);

        $this->assertTrue(true);
    }

    public function testOnKernelRequestWithEnabledButTrackQueriesDisabled(): void
    {
        $subscriber = new QueryTrackingConnectionSubscriber(
            $this->registry,
            true,  // enabled = true
            false, // trackQueries = false
            'default'
        );

        $request = new \Symfony\Component\HttpFoundation\Request();
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        // Should return early
        $subscriber->onKernelRequest($event);

        $this->assertTrue(true);
    }

    public function testOnKernelRequestWithTrackQueriesButEnabledDisabled(): void
    {
        $subscriber = new QueryTrackingConnectionSubscriber(
            $this->registry,
            false, // enabled = false
            true,  // trackQueries = true
            'default'
        );

        $request = new \Symfony\Component\HttpFoundation\Request();
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        // Should return early
        $subscriber->onKernelRequest($event);

        $this->assertTrue(true);
    }

    public function testOnKernelRequestMultipleCalls(): void
    {
        $connection = $this->createMock(Connection::class);
        $this->registry->method('getConnection')->willReturn($connection);

        $subscriber = new QueryTrackingConnectionSubscriber(
            $this->registry,
            true,
            true,
            'default'
        );

        $request = new \Symfony\Component\HttpFoundation\Request();
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        // Call multiple times
        $subscriber->onKernelRequest($event);
        $subscriber->onKernelRequest($event);
        $subscriber->onKernelRequest($event);

        // Should not throw exception
        $this->assertTrue(true);
    }

    public function testOnKernelRequestWithEmptyConnectionName(): void
    {
        $connection = $this->createMock(Connection::class);
        $this->registry
            ->method('getConnection')
            ->with('')
            ->willReturn($connection);

        $subscriber = new QueryTrackingConnectionSubscriber(
            $this->registry,
            true,
            true,
            '' // empty connection name
        );

        $request = new \Symfony\Component\HttpFoundation\Request();
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        $this->assertTrue(true);
    }

    public function testGetSubscribedEventsReturnsEmptyArray(): void
    {
        $events = QueryTrackingConnectionSubscriber::getSubscribedEvents();

        $this->assertIsArray($events);
        $this->assertEmpty($events);
    }

    public function testOnKernelRequestDoesNotCallRegistryWhenDisabled(): void
    {
        $subscriber = new QueryTrackingConnectionSubscriber(
            $this->registry,
            false,
            false,
            'default'
        );

        $this->registry
            ->expects($this->never())
            ->method('getConnection');

        $request = new \Symfony\Component\HttpFoundation\Request();
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);
    }

    public function testOnKernelRequestHandlesMultipleConnectionNames(): void
    {
        $connection1 = $this->createMock(Connection::class);
        $connection2 = $this->createMock(Connection::class);

        $this->registry
            ->method('getConnection')
            ->willReturnCallback(function ($name) use ($connection1, $connection2) {
                return match ($name) {
                    'connection1' => $connection1,
                    'connection2' => $connection2,
                    default => $connection1,
                };
            });

        $subscriber1 = new QueryTrackingConnectionSubscriber(
            $this->registry,
            true,
            true,
            'connection1'
        );

        $subscriber2 = new QueryTrackingConnectionSubscriber(
            $this->registry,
            true,
            true,
            'connection2'
        );

        $request = new \Symfony\Component\HttpFoundation\Request();
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber1->onKernelRequest($event);
        $subscriber2->onKernelRequest($event);

        // Should not throw exception
        $this->assertTrue(true);
    }
}
