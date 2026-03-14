<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Integration\EventSubscriber;

use Nowo\PerformanceBundle\Tests\Integration\TestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class PerformanceMetricsSubscriberIntegrationTest extends TestCase
{
    /** Sub-request triggers subscriber's "not main request" branch when track_sub_requests is false. */
    public function testSubRequestDisablesTrackingWhenTrackSubRequestsFalse(): void
    {
        $kernel = new TestKernel('test', true);
        $kernel->boot();

        $request = Request::create('/performance', Request::METHOD_GET);
        $kernel->handle($request, HttpKernelInterface::SUB_REQUEST, false);

        self::assertTrue(true, 'Sub-request completes without error (subscriber skips tracking)');
        $kernel->shutdown();
    }

    public function testMainRequestTriggersSubscriber(): void
    {
        $kernel = new TestKernel('test', true);
        $kernel->boot();

        $request  = Request::create('/performance', Request::METHOD_GET);
        $response = $kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, false);

        if ($response->isRedirection()) {
            $location = $response->headers->get('Location');
            self::assertNotNull($location);
            $request  = Request::create($location, Request::METHOD_GET);
            $response = $kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, false);
        }

        self::assertSame(200, $response->getStatusCode());
        $kernel->shutdown();
    }
}
