<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\EventSubscriber;

use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\DataCollector\PerformanceDataCollector;
use Nowo\PerformanceBundle\EventSubscriber\PerformanceMetricsSubscriber;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Tests for PerformanceMetricsSubscriber when track_user is enabled:
 * security null (no crash, user ids passed as null) and security with user (identifier and id passed).
 */
final class PerformanceMetricsSubscriberSecurityTest extends TestCase
{
    private PerformanceMetricsService|MockObject $metricsService;
    private ManagerRegistry|MockObject $registry;
    private PerformanceDataCollector|MockObject $dataCollector;
    private HttpKernelInterface|MockObject $kernel;

    protected function setUp(): void
    {
        $this->metricsService = $this->createMock(PerformanceMetricsService::class);
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->dataCollector = $this->createMock(PerformanceDataCollector::class);
        $this->kernel = $this->createMock(HttpKernelInterface::class);
    }

    private function createSubscriber(
        bool $trackUser = false,
        ?object $security = null,
        ?\Symfony\Component\HttpFoundation\RequestStack $requestStack = null,
        ?\Symfony\Component\Stopwatch\Stopwatch $stopwatch = null,
        ?\Symfony\Component\HttpKernel\KernelInterface $kernel = null
    ): PerformanceMetricsSubscriber {
        return new PerformanceMetricsSubscriber(
            $this->metricsService,
            $this->registry,
            'default',
            $this->dataCollector,
            true,
            ['dev', 'test'],
            [],
            true,
            true,
            false,
            false,
            1.0,
            [200, 404, 500, 503],
            true,
            $trackUser,
            $requestStack,
            $security,
            $stopwatch,
            $kernel ?? $this->kernel
        );
    }

    public function testTrackUserTrueSecurityNullRecordsWithNullUserIds(): void
    {
        $subscriber = $this->createSubscriber(true, null);

        $request = Request::create('/');
        $request->server->set('APP_ENV', 'dev');
        $request->attributes->set('_route', 'app_home');
        $requestEvent = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->dataCollector->method('setEnabled')->willReturnSelf();
        $this->dataCollector->method('setRouteName')->willReturnSelf();
        $this->dataCollector->method('setStartTime')->willReturnSelf();
        $this->dataCollector->method('isEnabled')->willReturn(true);
        $this->dataCollector->method('setRequestTime')->with($this->anything());
        $this->dataCollector->method('setQueryCount')->with($this->anything());
        $this->dataCollector->method('setQueryTime')->with($this->anything());
        $this->kernel->method('getEnvironment')->willReturn('dev');

        $subscriber->onKernelRequest($requestEvent);

        $this->metricsService
            ->expects($this->once())
            ->method('recordMetrics')
            ->with(
                'app_home',
                'dev',
                $this->isType('float'),
                0,
                0.0,
                [],
                null,
                'GET',
                200,
                [200, 404, 500, 503],
                $this->isType('string'),
                null,
                null,
                null
            );

        $terminateEvent = new TerminateEvent($this->kernel, $request, new Response());
        $subscriber->onKernelTerminate($terminateEvent);
    }

    public function testTrackUserTrueSecurityWithUserPassesIdentifierAndId(): void
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('john@example.com');
        $user->method('getId')->willReturn('uuid-123');

        $security = $this->createMock(\stdClass::class);
        $security->method('getUser')->willReturn($user);

        $subscriber = $this->createSubscriber(true, $security);

        $request = Request::create('/');
        $request->server->set('APP_ENV', 'dev');
        $request->attributes->set('_route', 'app_home');
        $requestEvent = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->dataCollector->method('setEnabled')->willReturnSelf();
        $this->dataCollector->method('setRouteName')->willReturnSelf();
        $this->dataCollector->method('setStartTime')->willReturnSelf();
        $this->dataCollector->method('isEnabled')->willReturn(true);
        $this->dataCollector->method('setRequestTime')->with($this->anything());
        $this->dataCollector->method('setQueryCount')->with($this->anything());
        $this->dataCollector->method('setQueryTime')->with($this->anything());
        $this->kernel->method('getEnvironment')->willReturn('dev');

        $subscriber->onKernelRequest($requestEvent);

        $this->metricsService
            ->expects($this->once())
            ->method('recordMetrics')
            ->with(
                'app_home',
                'dev',
                $this->isType('float'),
                0,
                0.0,
                [],
                null,
                'GET',
                200,
                [200, 404, 500, 503],
                $this->isType('string'),
                null,
                'john@example.com',
                'uuid-123'
            );

        $terminateEvent = new TerminateEvent($this->kernel, $request, new Response());
        $subscriber->onKernelTerminate($terminateEvent);
    }

    public function testTrackUserTrueSecurityReturnsNullUserPassesNullIds(): void
    {
        $security = $this->createMock(\stdClass::class);
        $security->method('getUser')->willReturn(null);

        $subscriber = $this->createSubscriber(true, $security);

        $request = Request::create('/');
        $request->server->set('APP_ENV', 'dev');
        $request->attributes->set('_route', 'app_home');
        $requestEvent = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->dataCollector->method('setEnabled')->willReturnSelf();
        $this->dataCollector->method('setRouteName')->willReturnSelf();
        $this->dataCollector->method('setStartTime')->willReturnSelf();
        $this->dataCollector->method('isEnabled')->willReturn(true);
        $this->dataCollector->method('setRequestTime')->with($this->anything());
        $this->dataCollector->method('setQueryCount')->with($this->anything());
        $this->dataCollector->method('setQueryTime')->with($this->anything());
        $this->kernel->method('getEnvironment')->willReturn('dev');

        $subscriber->onKernelRequest($requestEvent);

        $this->metricsService
            ->expects($this->once())
            ->method('recordMetrics')
            ->with(
                'app_home',
                'dev',
                $this->isType('float'),
                0,
                0.0,
                [],
                null,
                'GET',
                200,
                [200, 404, 500, 503],
                $this->isType('string'),
                null,
                null,
                null
            );

        $terminateEvent = new TerminateEvent($this->kernel, $request, new Response());
        $subscriber->onKernelTerminate($terminateEvent);
    }

    public function testTrackUserFalseSecurityIgnored(): void
    {
        $subscriber = $this->createSubscriber(false, null);

        $request = Request::create('/');
        $request->server->set('APP_ENV', 'dev');
        $request->attributes->set('_route', 'app_home');
        $requestEvent = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->dataCollector->method('setEnabled')->willReturnSelf();
        $this->dataCollector->method('setRouteName')->willReturnSelf();
        $this->dataCollector->method('setStartTime')->willReturnSelf();
        $this->dataCollector->method('isEnabled')->willReturn(true);
        $this->dataCollector->method('setRequestTime')->with($this->anything());
        $this->dataCollector->method('setQueryCount')->with($this->anything());
        $this->dataCollector->method('setQueryTime')->with($this->anything());
        $this->kernel->method('getEnvironment')->willReturn('dev');

        $subscriber->onKernelRequest($requestEvent);

        $this->metricsService
            ->expects($this->once())
            ->method('recordMetrics')
            ->with(
                'app_home',
                'dev',
                $this->isType('float'),
                0,
                0.0,
                [],
                null,
                'GET',
                200,
                [200, 404, 500, 503],
                $this->isType('string'),
                null,
                null,
                null
            );

        $terminateEvent = new TerminateEvent($this->kernel, $request, new Response());
        $subscriber->onKernelTerminate($terminateEvent);
    }

    public function testTrackUserTrueUserWithoutGetIdPassesNullUserId(): void
    {
        $user = new class implements UserInterface {
            public function getUserIdentifier(): string
            {
                return 'jane@example.com';
            }

            public function getRoles(): array
            {
                return ['ROLE_USER'];
            }

            public function eraseCredentials(): void
            {
            }
        };

        $security = $this->createMock(\stdClass::class);
        $security->method('getUser')->willReturn($user);

        $subscriber = $this->createSubscriber(true, $security);

        $request = Request::create('/');
        $request->server->set('APP_ENV', 'dev');
        $request->attributes->set('_route', 'app_home');
        $requestEvent = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->dataCollector->method('setEnabled')->willReturnSelf();
        $this->dataCollector->method('setRouteName')->willReturnSelf();
        $this->dataCollector->method('setStartTime')->willReturnSelf();
        $this->dataCollector->method('isEnabled')->willReturn(true);
        $this->dataCollector->method('setRequestTime')->with($this->anything());
        $this->dataCollector->method('setQueryCount')->with($this->anything());
        $this->dataCollector->method('setQueryTime')->with($this->anything());
        $this->kernel->method('getEnvironment')->willReturn('dev');

        $subscriber->onKernelRequest($requestEvent);

        $this->metricsService
            ->expects($this->once())
            ->method('recordMetrics')
            ->with(
                'app_home',
                'dev',
                $this->isType('float'),
                0,
                0.0,
                [],
                null,
                'GET',
                200,
                [200, 404, 500, 503],
                $this->isType('string'),
                null,
                'jane@example.com',
                null
            );

        $terminateEvent = new TerminateEvent($this->kernel, $request, new Response());
        $subscriber->onKernelTerminate($terminateEvent);
    }
}
