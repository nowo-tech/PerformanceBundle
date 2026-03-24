<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\EventSubscriber;

use Nowo\PerformanceBundle\DataCollector\PerformanceDataCollector;
use Nowo\PerformanceBundle\EventSubscriber\PerformanceMetricsSubscriber;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Covers environment detection when no Kernel is injected (APP_ENV from server bag / superglobals).
 */
final class PerformanceMetricsSubscriberEnvironmentFallbackTest extends TestCase
{
    public function testOnKernelRequestUsesRequestServerAppEnvWhenKernelIsNull(): void
    {
        $collector = $this->createMock(PerformanceDataCollector::class);
        $collector->method('setEnabled');
        $collector->method('setAsync');
        $collector->expects(self::atLeastOnce())->method('setCurrentEnvironment')->with('staging');

        $subscriber = $this->createSubscriberWithCollector($collector, null);

        $request = Request::create('/');
        $request->server->set('APP_ENV', 'staging');

        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        $subscriber->onKernelRequest($event);
    }

    public function testOnKernelRequestUsesSuperglobalServerAppEnvWhenKernelIsNull(): void
    {
        $prev               = $_SERVER['APP_ENV'] ?? null;
        $_SERVER['APP_ENV'] = 'staging';

        try {
            $collector = $this->createMock(PerformanceDataCollector::class);
            $collector->method('setEnabled');
            $collector->method('setAsync');
            $collector->expects(self::atLeastOnce())->method('setCurrentEnvironment')->with('staging');

            $subscriber = $this->createSubscriberWithCollector($collector, null);

            $request = Request::create('/');
            $request->server->remove('APP_ENV');
            $event = new RequestEvent(
                $this->createMock(HttpKernelInterface::class),
                $request,
                HttpKernelInterface::MAIN_REQUEST,
            );

            $subscriber->onKernelRequest($event);
        } finally {
            if ($prev === null) {
                unset($_SERVER['APP_ENV']);
            } else {
                $_SERVER['APP_ENV'] = $prev;
            }
        }
    }

    private function createSubscriberWithCollector(
        PerformanceDataCollector $collector,
        ?KernelInterface $kernel,
    ): PerformanceMetricsSubscriber {
        $metrics = $this->createMock(PerformanceMetricsService::class);

        return new PerformanceMetricsSubscriber(
            $metrics,
            $collector,
            true,
            ['staging', 'test', 'dev', 'prod'],
            [],
            false,
            false,
            false,
            false,
            1.0,
            [200],
            false,
            false,
            null,
            null,
            null,
            $kernel,
        );
    }
}
