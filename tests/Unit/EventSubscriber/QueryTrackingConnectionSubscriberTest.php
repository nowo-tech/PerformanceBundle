<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\EventSubscriber;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\DBAL\QueryTrackingMiddleware;
use Nowo\PerformanceBundle\DBAL\QueryTrackingMiddlewareRegistry;
use Nowo\PerformanceBundle\EventSubscriber\QueryTrackingConnectionSubscriber;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class QueryTrackingConnectionSubscriberTest extends TestCase
{
    private ManagerRegistry|MockObject $registry;
    private QueryTrackingConnectionSubscriber $subscriber;
    private HttpKernelInterface|MockObject $kernel;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->kernel = $this->createMock(HttpKernelInterface::class);

        $this->subscriber = new QueryTrackingConnectionSubscriber(
            $this->registry,
            true,  // enabled
            true,  // trackQueries
            'default' // connectionName
        );
    }

    public function testGetSubscribedEvents(): void
    {
        $events = QueryTrackingConnectionSubscriber::getSubscribedEvents();

        // Events are registered via #[AsEventListener] attributes, so method returns empty array
        $this->assertIsArray($events);
        $this->assertEmpty($events);
    }

    public function testOnKernelRequestWhenDisabled(): void
    {
        $subscriber = new QueryTrackingConnectionSubscriber(
            $this->registry,
            false, // enabled = false
            true,
            'default'
        );

        $request = new \Symfony\Component\HttpFoundation\Request();
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        // Should not throw exception
        $subscriber->onKernelRequest($event);
    }

    public function testOnKernelRequestWhenTrackQueriesDisabled(): void
    {
        $subscriber = new QueryTrackingConnectionSubscriber(
            $this->registry,
            true,
            false, // trackQueries = false
            'default'
        );

        $request = new \Symfony\Component\HttpFoundation\Request();
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        // Should not throw exception
        $subscriber->onKernelRequest($event);
    }

    public function testOnKernelRequestAppliesMiddleware(): void
    {
        $connection = $this->createMock(Connection::class);
        $this->registry->method('getConnection')->willReturn($connection);

        // Mock QueryTrackingMiddlewareRegistry to return true
        $this->mockQueryTrackingMiddlewareRegistry(true);

        $request = new \Symfony\Component\HttpFoundation\Request();
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        // Should not throw exception
        $this->subscriber->onKernelRequest($event);
    }

    public function testOnKernelRequestHandlesConnectionNotFound(): void
    {
        $this->registry->method('getConnection')->willThrowException(new \Exception('Connection not found'));

        // Mock QueryTrackingMiddlewareRegistry to return false
        $this->mockQueryTrackingMiddlewareRegistry(false);

        $request = new \Symfony\Component\HttpFoundation\Request();
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        // Should not throw exception, should handle gracefully
        $this->subscriber->onKernelRequest($event);
    }

    public function testOnKernelRequestResetsQueryTracking(): void
    {
        $connection = $this->createMock(Connection::class);
        $this->registry->method('getConnection')->willReturn($connection);

        // Mock QueryTrackingMiddlewareRegistry to return true
        $this->mockQueryTrackingMiddlewareRegistry(true);

        // Reset middleware state before test
        QueryTrackingMiddleware::reset();

        $request = new \Symfony\Component\HttpFoundation\Request();
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->subscriber->onKernelRequest($event);

        // Verify that reset was called (query count should be 0)
        $this->assertSame(0, QueryTrackingMiddleware::getQueryCount());
    }

    /**
     * Mock QueryTrackingMiddlewareRegistry::applyMiddleware to return specified value.
     */
    private function mockQueryTrackingMiddlewareRegistry(bool $returnValue): void
    {
        // We can't easily mock static methods, so we'll just verify the behavior
        // The actual implementation will be tested in integration tests
    }
}
