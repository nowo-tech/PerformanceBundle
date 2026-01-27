<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Controller;

use Nowo\PerformanceBundle\Controller\PerformanceController;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Tests for PerformanceController::diagnose() method - Subscriber detection.
 */
final class PerformanceControllerDiagnoseSubscriberTest extends TestCase
{
    private PerformanceMetricsService|MockObject $metricsService;
    private RouteDataRepository|MockObject $repository;

    protected function setUp(): void
    {
        $this->metricsService = $this->createMock(PerformanceMetricsService::class);
        $this->repository = $this->createMock(RouteDataRepository::class);
    }

    /**
     * Helper method to create a controller with container mock.
     */
    private function createControllerWithContainer(?object $container = null): PerformanceController
    {
        $controller = $this->getMockBuilder(PerformanceController::class)
            ->setConstructorArgs([
                $this->metricsService,
                null,
                true,
                [],
                'bootstrap',
                null,
                null,
                null,
                false,
                false,
                null,
                0.5,
                1.0,
                20,
                50,
                20.0,
                50.0,
                'Y-m-d H:i:s',
                'Y-m-d H:i',
                0,
                [200, 404, 500, 503],
                null,
                false,
                true,
                ['dev', 'test'],
                'default',
                true,
                true,
                false,
                [],
                false,
                1.0,
                true,
            ])
            ->onlyMethods(['getParameter', 'render'])
            ->getMock();

        // Set container using reflection
        if (null !== $container) {
            $reflection = new \ReflectionClass($controller);
            $containerProperty = $reflection->getProperty('container');
            $containerProperty->setAccessible(true);
            $containerProperty->setValue($controller, $container);
        }

        $controller->expects($this->any())
            ->method('getParameter')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'kernel.environment' => 'dev',
                    default => null,
                };
            });

        return $controller;
    }

    public function testDiagnoseDetectsSubscriberInEventDispatcherListeners(): void
    {
        // Create mock subscriber
        $subscriber = $this->createMock(\Nowo\PerformanceBundle\EventSubscriber\PerformanceMetricsSubscriber::class);

        // Create event dispatcher with subscriber in listeners
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('getListeners')
            ->with(KernelEvents::REQUEST)
            ->willReturn([
                [$subscriber, 'onKernelRequest'],
            ]);

        // Create container with event dispatcher
        $container = $this->createMock(\Psr\Container\ContainerInterface::class);
        $container->method('has')
            ->willReturnCallback(function ($id) {
                return 'event_dispatcher' === $id;
            });
        $container->method('get')
            ->with('event_dispatcher')
            ->willReturn($eventDispatcher);

        $controller = $this->createControllerWithContainer($container);

        $request = new Request();

        $controller->expects($this->once())
            ->method('render')
            ->with(
                '@NowoPerformanceBundle/Performance/diagnose.html.twig',
                $this->callback(function ($vars) {
                    return isset($vars['diagnostic']['subscriber_status'])
                        && $vars['diagnostic']['subscriber_status']['subscriber_registered'] === true;
                })
            )
            ->willReturn(new Response());

        $result = $controller->diagnose($request);

        $this->assertInstanceOf(Response::class, $result);
    }

    public function testDiagnoseDetectsSubscriberInTerminateListeners(): void
    {
        // Create mock subscriber
        $subscriber = $this->createMock(\Nowo\PerformanceBundle\EventSubscriber\PerformanceMetricsSubscriber::class);

        // Create event dispatcher with subscriber in TERMINATE listeners
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('getListeners')
            ->willReturnCallback(function ($eventName) use ($subscriber) {
                if (KernelEvents::REQUEST === $eventName) {
                    return [];
                }
                if (KernelEvents::TERMINATE === $eventName) {
                    return [
                        [$subscriber, 'onKernelTerminate'],
                    ];
                }
                return [];
            });

        // Create container with event dispatcher
        $container = $this->createMock(\Psr\Container\ContainerInterface::class);
        $container->method('has')
            ->willReturnCallback(function ($id) {
                return 'event_dispatcher' === $id;
            });
        $container->method('get')
            ->with('event_dispatcher')
            ->willReturn($eventDispatcher);

        $controller = $this->createControllerWithContainer($container);

        $request = new Request();

        $controller->expects($this->once())
            ->method('render')
            ->with(
                '@NowoPerformanceBundle/Performance/diagnose.html.twig',
                $this->callback(function ($vars) {
                    return isset($vars['diagnostic']['subscriber_status'])
                        && $vars['diagnostic']['subscriber_status']['subscriber_registered'] === true;
                })
            )
            ->willReturn(new Response());

        $result = $controller->diagnose($request);

        $this->assertInstanceOf(Response::class, $result);
    }

    public function testDiagnoseDetectsSubscriberInContainer(): void
    {
        // Create mock subscriber
        $subscriber = $this->createMock(\Nowo\PerformanceBundle\EventSubscriber\PerformanceMetricsSubscriber::class);

        // Create event dispatcher without subscriber in listeners
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('getListeners')
            ->willReturn([]);

        // Create container with subscriber service
        $container = $this->createMock(\Psr\Container\ContainerInterface::class);
        $container->method('has')
            ->willReturnCallback(function ($id) {
                return 'event_dispatcher' === $id
                    || 'Nowo\\PerformanceBundle\\EventSubscriber\\PerformanceMetricsSubscriber' === $id;
            });
        $container->method('get')
            ->willReturnCallback(function ($id) use ($eventDispatcher, $subscriber) {
                return match ($id) {
                    'event_dispatcher' => $eventDispatcher,
                    'Nowo\\PerformanceBundle\\EventSubscriber\\PerformanceMetricsSubscriber' => $subscriber,
                    default => null,
                };
            });

        $controller = $this->createControllerWithContainer($container);

        $request = new Request();

        $controller->expects($this->once())
            ->method('render')
            ->with(
                '@NowoPerformanceBundle/Performance/diagnose.html.twig',
                $this->callback(function ($vars) {
                    return isset($vars['diagnostic']['subscriber_status'])
                        && $vars['diagnostic']['subscriber_status']['subscriber_registered'] === true;
                })
            )
            ->willReturn(new Response());

        $result = $controller->diagnose($request);

        $this->assertInstanceOf(Response::class, $result);
    }

    public function testDiagnoseFallsBackToClassExistenceCheck(): void
    {
        // Create event dispatcher without subscriber
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('getListeners')
            ->willReturn([]);

        // Create container without subscriber service
        $container = $this->createMock(\Psr\Container\ContainerInterface::class);
        $container->method('has')
            ->willReturnCallback(function ($id) {
                return 'event_dispatcher' === $id;
            });
        $container->method('get')
            ->with('event_dispatcher')
            ->willReturn($eventDispatcher);

        $controller = $this->createControllerWithContainer($container);

        $request = new Request();

        $controller->expects($this->once())
            ->method('render')
            ->with(
                '@NowoPerformanceBundle/Performance/diagnose.html.twig',
                $this->callback(function ($vars) {
                    // Should detect subscriber via class existence check
                    return isset($vars['diagnostic']['subscriber_status'])
                        && $vars['diagnostic']['subscriber_status']['subscriber_registered'] === true;
                })
            )
            ->willReturn(new Response());

        $result = $controller->diagnose($request);

        $this->assertInstanceOf(Response::class, $result);
    }

    public function testDiagnoseHandlesEventDispatcherException(): void
    {
        // Create container that throws exception when getting event dispatcher
        $container = $this->createMock(\Psr\Container\ContainerInterface::class);
        $container->method('has')
            ->willReturnCallback(function ($id) {
                return 'event_dispatcher' === $id;
            });
        $container->method('get')
            ->with('event_dispatcher')
            ->willThrowException(new \Exception('Event dispatcher error'));

        $controller = $this->createControllerWithContainer($container);

        $request = new Request();

        $controller->expects($this->once())
            ->method('render')
            ->with(
                '@NowoPerformanceBundle/Performance/diagnose.html.twig',
                $this->callback(function ($vars) {
                    // Should fall back to class existence check even if event dispatcher fails
                    return isset($vars['diagnostic']['subscriber_status'])
                        && isset($vars['diagnostic']['subscriber_status']['subscriber_error'])
                        && $vars['diagnostic']['subscriber_status']['subscriber_registered'] === true;
                })
            )
            ->willReturn(new Response());

        $result = $controller->diagnose($request);

        $this->assertInstanceOf(Response::class, $result);
    }

    public function testDiagnoseHandlesGetListenersException(): void
    {
        // Create event dispatcher that throws exception
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('getListeners')
            ->willThrowException(new \Exception('getListeners error'));

        // Create container with event dispatcher
        $container = $this->createMock(\Psr\Container\ContainerInterface::class);
        $container->method('has')
            ->willReturnCallback(function ($id) {
                return 'event_dispatcher' === $id;
            });
        $container->method('get')
            ->with('event_dispatcher')
            ->willReturn($eventDispatcher);

        $controller = $this->createControllerWithContainer($container);

        $request = new Request();

        $controller->expects($this->once())
            ->method('render')
            ->with(
                '@NowoPerformanceBundle/Performance/diagnose.html.twig',
                $this->callback(function ($vars) {
                    // Should fall back to class existence check
                    return isset($vars['diagnostic']['subscriber_status'])
                        && $vars['diagnostic']['subscriber_status']['subscriber_registered'] === true;
                })
            )
            ->willReturn(new Response());

        $result = $controller->diagnose($request);

        $this->assertInstanceOf(Response::class, $result);
    }

    public function testDiagnoseShowsDetectionMethod(): void
    {
        // Create mock subscriber
        $subscriber = $this->createMock(\Nowo\PerformanceBundle\EventSubscriber\PerformanceMetricsSubscriber::class);

        // Create event dispatcher with subscriber
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('getListeners')
            ->with(KernelEvents::REQUEST)
            ->willReturn([
                [$subscriber, 'onKernelRequest'],
            ]);

        // Create container
        $container = $this->createMock(\Psr\Container\ContainerInterface::class);
        $container->method('has')
            ->willReturnCallback(function ($id) {
                return 'event_dispatcher' === $id;
            });
        $container->method('get')
            ->with('event_dispatcher')
            ->willReturn($eventDispatcher);

        $controller = $this->createControllerWithContainer($container);

        $request = new Request();

        $controller->expects($this->once())
            ->method('render')
            ->with(
                '@NowoPerformanceBundle/Performance/diagnose.html.twig',
                $this->callback(function ($vars) {
                    return isset($vars['diagnostic']['subscriber_status'])
                        && $vars['diagnostic']['subscriber_status']['subscriber_registered'] === true
                        && isset($vars['diagnostic']['subscriber_status']['detection_method'])
                        && str_contains($vars['diagnostic']['subscriber_status']['detection_method'], 'REQUEST');
                })
            )
            ->willReturn(new Response());

        $result = $controller->diagnose($request);

        $this->assertInstanceOf(Response::class, $result);
    }
}
