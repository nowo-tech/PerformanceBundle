<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\EventSubscriber;

use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\EventSubscriber\QueryTrackingConnectionSubscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class QueryTrackingConnectionSubscriberTest extends TestCase
{
    private ManagerRegistry|MockObject $registry;

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
}
