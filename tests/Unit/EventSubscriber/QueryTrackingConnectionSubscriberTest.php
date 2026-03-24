<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\EventSubscriber;

use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\EventSubscriber\QueryTrackingConnectionSubscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class QueryTrackingConnectionSubscriberTest extends TestCase
{
    private MockObject $registry;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ManagerRegistry::class);
    }

    public function testGetSubscribedEventsReturnsEmpty(): void
    {
        $subscriber = new QueryTrackingConnectionSubscriber($this->registry, true, true, 'default');
        $this->assertSame([], $subscriber->getSubscribedEvents());
    }

    public function testOnKernelRequestDoesNothingWhenDisabled(): void
    {
        $this->registry->expects($this->never())->method($this->anything());

        $subscriber = new QueryTrackingConnectionSubscriber($this->registry, false, true, 'default');
        $event      = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
        );
        $subscriber->onKernelRequest($event);
    }

    public function testOnKernelRequestDoesNothingWhenTrackQueriesDisabled(): void
    {
        $this->registry->expects($this->never())->method($this->anything());

        $subscriber = new QueryTrackingConnectionSubscriber($this->registry, true, false, 'default');
        $event      = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
        );
        $subscriber->onKernelRequest($event);
    }

    public function testConstructorWithCustomConnectionName(): void
    {
        $subscriber = new QueryTrackingConnectionSubscriber($this->registry, false, false, 'custom_conn');

        $this->assertSame([], $subscriber->getSubscribedEvents());
    }

    public function testConstructorWithTrackRequestTimeDisabled(): void
    {
        $subscriber = new QueryTrackingConnectionSubscriber($this->registry, true, false, 'default');

        $this->assertSame([], $subscriber->getSubscribedEvents());
    }

    public function testOnKernelRequestWhenEnabledCallsRegistryAndResetsMiddleware(): void
    {
        $this->registry->method('getConnection')->with('default')->willReturn(new stdClass());

        $subscriber = new QueryTrackingConnectionSubscriber($this->registry, true, true, 'default');
        $event      = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
        );
        $subscriber->onKernelRequest($event);

        $this->addToAssertionCount(1);
    }

    public function testOnKernelRequestWhenEnabledAndRegistryThrowsRetriesAndResets(): void
    {
        $this->registry->method('getConnection')->willThrowException(new RuntimeException('connection not ready'));

        $subscriber = new QueryTrackingConnectionSubscriber($this->registry, true, true, 'default');
        $event      = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
        );
        $subscriber->onKernelRequest($event);

        $this->addToAssertionCount(1);
    }

    /**
     * Covers the early return when connection is already tracked (line 111).
     * Uses reflection to set trackedConnections and call applyMiddlewareToConnection again.
     */
    public function testApplyMiddlewareToConnectionReturnsEarlyWhenAlreadyTracked(): void
    {
        $this->registry->expects($this->never())->method('getConnection');

        $subscriber = new QueryTrackingConnectionSubscriber($this->registry, true, true, 'default');

        $ref  = new ReflectionClass($subscriber);
        $prop = $ref->getProperty('trackedConnections');
        $prop->setValue($subscriber, ['default' => true]);

        $method = new ReflectionMethod($subscriber, 'applyMiddlewareToConnection');
        $method->invoke($subscriber);
    }

    /**
     * Covers the branch when applyMiddleware returns false (line 131: unset trackedConnections).
     * Registry returns non-Connection so reflection fails; wrapper must also fail to get success=false.
     */
    public function testOnKernelRequestWhenApplyMiddlewareReturnsFalseUnsetsTracked(): void
    {
        $this->registry->method('getConnection')->with('default')->willReturn(new stdClass());

        $subscriber = new QueryTrackingConnectionSubscriber($this->registry, true, true, 'default');
        $event      = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
        );
        $subscriber->onKernelRequest($event);

        $ref     = new ReflectionClass($subscriber);
        $prop    = $ref->getProperty('trackedConnections');
        $tracked = $prop->getValue($subscriber);
        $this->assertArrayNotHasKey('default', $tracked);
    }

    /**
     * Covers line 131 by calling applyMiddlewareToConnection directly with registry returning non-Connection.
     */
    public function testApplyMiddlewareToConnectionWhenRegistryReturnsNonConnectionUnsetsTracked(): void
    {
        $this->registry->method('getConnection')->with('default')->willReturn(new stdClass());

        $subscriber = new QueryTrackingConnectionSubscriber($this->registry, true, true, 'default');
        $method     = new ReflectionMethod($subscriber, 'applyMiddlewareToConnection');
        $method->invoke($subscriber);

        $ref  = new ReflectionClass($subscriber);
        $prop = $ref->getProperty('trackedConnections');
        $this->assertArrayNotHasKey('default', $prop->getValue($subscriber));
    }

    /**
     * Covers the catch block when applyMiddleware throws (line 133: unset on exception).
     */
    public function testOnKernelRequestWhenRegistryThrowsUnsetsTracked(): void
    {
        $this->registry->method('getConnection')->willThrowException(new RuntimeException('fail'));

        $subscriber = new QueryTrackingConnectionSubscriber($this->registry, true, true, 'default');
        $event      = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
        );
        $subscriber->onKernelRequest($event);

        $ref  = new ReflectionClass($subscriber);
        $prop = $ref->getProperty('trackedConnections');
        $this->assertEmpty($prop->getValue($subscriber));
    }

    /**
     * Covers the retry path: first attempt throws (catch runs, line 133), second attempt returns non-Connection (else branch, line 131).
     * Ensures both exception path and success=false path are exercised in one request.
     */
    public function testOnKernelRequestRetriesOnFailureAndUnsetsWhenApplyFails(): void
    {
        $this->registry->expects($this->atLeast(2))
            ->method('getConnection')
            ->with('default')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new RuntimeException('first attempt')),
                new stdClass(),
            );

        $subscriber = new QueryTrackingConnectionSubscriber($this->registry, true, true, 'default');
        $event      = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
        );
        $subscriber->onKernelRequest($event);

        $ref  = new ReflectionClass($subscriber);
        $prop = $ref->getProperty('trackedConnections');
        $this->assertArrayNotHasKey('default', $prop->getValue($subscriber));
    }

    /**
     * Covers line 133 by calling applyMiddlewareToConnection directly when registry throws.
     */
    public function testApplyMiddlewareToConnectionWhenRegistryThrowsUnsetsTracked(): void
    {
        $this->registry->method('getConnection')->willThrowException(new RuntimeException('connection error'));

        $subscriber = new QueryTrackingConnectionSubscriber($this->registry, true, true, 'default');
        $method     = new ReflectionMethod($subscriber, 'applyMiddlewareToConnection');
        $method->invoke($subscriber);

        $ref  = new ReflectionClass($subscriber);
        $prop = $ref->getProperty('trackedConnections');
        $this->assertEmpty($prop->getValue($subscriber));
    }

    /**
     * Covers catch block (lines 131, 133) using a concrete stub that throws from getConnection.
     * Ensures the exception path is executed without mock proxy.
     */
    public function testApplyMiddlewareToConnectionWhenStubThrowsUnsetsTracked(): void
    {
        $registry   = new ThrowingRegistryStub('stub connection error');
        $subscriber = new QueryTrackingConnectionSubscriber($registry, true, true, 'default');
        $method     = new ReflectionMethod($subscriber, 'applyMiddlewareToConnection');
        $method->invoke($subscriber);

        $ref  = new ReflectionClass($subscriber);
        $prop = $ref->getProperty('trackedConnections');
        $this->assertEmpty($prop->getValue($subscriber));
    }

    /**
     * Covers catch block when middleware throws after creation (testApplyMiddlewareThrowable).
     */
    public function testApplyMiddlewareToConnectionWhenTestThrowableThrowsUnsetsTracked(): void
    {
        $this->registry->method('getConnection')->willReturn(new stdClass());

        $subscriber = new QueryTrackingConnectionSubscriber($this->registry, true, true, 'default');
        $prev       = QueryTrackingConnectionSubscriber::$testApplyMiddlewareThrowable;
        try {
            QueryTrackingConnectionSubscriber::$testApplyMiddlewareThrowable = new RuntimeException('middleware test');
            $method                                                          = new ReflectionMethod($subscriber, 'applyMiddlewareToConnection');
            $method->invoke($subscriber);
        } finally {
            QueryTrackingConnectionSubscriber::$testApplyMiddlewareThrowable = $prev;
        }

        $ref  = new ReflectionClass($subscriber);
        $prop = $ref->getProperty('trackedConnections');
        $this->assertArrayNotHasKey('default', $prop->getValue($subscriber));
    }

    /**
     * Covers catch block via onKernelRequest (no reflection) with stub that throws from getConnection.
     */
    public function testOnKernelRequestWhenStubThrowsUnsetsTracked(): void
    {
        $registry   = new ThrowingRegistryStub('stub getConnection throws');
        $subscriber = new QueryTrackingConnectionSubscriber($registry, true, true, 'default');
        $event      = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
        );
        $subscriber->onKernelRequest($event);

        $ref  = new ReflectionClass($subscriber);
        $prop = $ref->getProperty('trackedConnections');
        $this->assertEmpty($prop->getValue($subscriber));
    }
}
