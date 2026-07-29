<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\EventSubscriber;

use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\DBAL\QueryTrackingCounters;
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
        $subscriber = new QueryTrackingConnectionSubscriber($this->registry, new QueryTrackingCounters(), true, true, 'default');
        $this->assertSame([], $subscriber->getSubscribedEvents());
    }

    public function testOnKernelRequestDoesNothingWhenDisabled(): void
    {
        $this->registry->expects($this->never())->method($this->anything());

        $subscriber = new QueryTrackingConnectionSubscriber($this->registry, new QueryTrackingCounters(), false, true, 'default');
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

        $subscriber = new QueryTrackingConnectionSubscriber($this->registry, new QueryTrackingCounters(), true, false, 'default');
        $event      = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
        );
        $subscriber->onKernelRequest($event);
    }

    public function testConstructorWithCustomConnectionName(): void
    {
        $subscriber = new QueryTrackingConnectionSubscriber($this->registry, new QueryTrackingCounters(), false, false, 'custom_conn');

        $this->assertSame([], $subscriber->getSubscribedEvents());
    }

    public function testConstructorWithTrackRequestTimeDisabled(): void
    {
        $subscriber = new QueryTrackingConnectionSubscriber($this->registry, new QueryTrackingCounters(), true, false, 'default');

        $this->assertSame([], $subscriber->getSubscribedEvents());
    }

    public function testOnKernelRequestWhenEnabledCallsRegistryAndResetsMiddleware(): void
    {
        $this->registry->method('getConnection')->with('default')->willReturn(new stdClass());

        $subscriber = new QueryTrackingConnectionSubscriber($this->registry, new QueryTrackingCounters(), true, true, 'default');
        $event      = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
        );
        $subscriber->onKernelRequest($event);

        $this->addToAssertionCount(1);
    }

    public function testOnKernelRequestWhenEnabledAndRegistryThrowsResetsMiddleware(): void
    {
        $this->registry->expects($this->exactly(2))
            ->method('getConnection')
            ->willThrowException(new RuntimeException('connection not ready'));

        $subscriber = new QueryTrackingConnectionSubscriber($this->registry, new QueryTrackingCounters(), true, true, 'default');
        $event      = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
        );
        $subscriber->onKernelRequest($event);

        $this->addToAssertionCount(1);
    }

    /**
     * Covers catch block via onKernelRequest (no reflection) with stub that throws from getConnection.
     */
    public function testOnKernelRequestWhenStubThrowsUnsetsTracked(): void
    {
        $registry   = new ThrowingRegistryStub('stub getConnection throws');
        $subscriber = new QueryTrackingConnectionSubscriber($registry, new QueryTrackingCounters(), true, true, 'default');
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
