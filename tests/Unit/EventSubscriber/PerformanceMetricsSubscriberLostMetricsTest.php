<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\EventSubscriber;

use Nowo\PerformanceBundle\DataCollector\PerformanceDataCollector;
use Nowo\PerformanceBundle\EventSubscriber\PerformanceMetricsSubscriber;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;
use Nowo\TransactionContract\TransientStorageFailure;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * The metrics subscriber is fire-and-forget: a failed recording never breaks the host request, and it is never silent
 * either: a warning names the route and the exception class, never the message (it can hold SQL), whatever
 * enable_logging says (REQ-RUNTIME-003 rule 8).
 */
final class PerformanceMetricsSubscriberLostMetricsTest extends TestCase
{
    public function testAFailedRecordingIsLoggedAsAWarningWithoutTheMessageEvenWithLoggingDisabled(): void
    {
        $metricsService = $this->createMock(PerformanceMetricsService::class);
        $metricsService->expects(self::once())->method('recordMetrics')
            ->willThrowException(TransientStorageFailure::fromPrevious(new RuntimeException('SELECT secret FROM t')));
        $dataCollector = $this->createMock(PerformanceDataCollector::class);
        $dataCollector->method('isEnabled')->willReturn(true);
        $dataCollector->expects(self::atLeastOnce())->method('setRecordOperation')->with(false, false);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with(
            '[PerformanceBundle] The metrics of a request were not saved.',
            ['bundle' => 'performance', 'route' => 'app_home', 'exception' => TransientStorageFailure::class],
        );
        $logger->expects(self::never())->method('log');

        $subscriber = new PerformanceMetricsSubscriber(
            $metricsService,
            $dataCollector,
            true,
            ['dev', 'test'],
            [],
            true,
            true,
            enableLogging: false,
            logger: $logger,
        );

        $kernel  = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/');
        $request->server->set('APP_ENV', 'dev');
        $request->attributes->set('_route', 'app_home');
        $subscriber->onKernelRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));
        $subscriber->onKernelTerminate(new TerminateEvent($kernel, $request, new Response()));
    }
}
