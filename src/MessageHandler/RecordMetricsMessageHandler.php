<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\MessageHandler;

use Nowo\PerformanceBundle\Message\RecordMetricsMessage;
use Nowo\PerformanceBundle\Service\PerformanceMetricsService;

// Load polyfill if Messenger is not available
require_once __DIR__.'/AsMessageHandlerPolyfill.php';

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Message handler for recording performance metrics asynchronously.
 *
 * This handler processes RecordMetricsMessage messages from the queue
 * and saves the metrics to the database.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[AsMessageHandler]
final class RecordMetricsMessageHandler
{
    /**
     * Creates a new instance.
     *
     * @param PerformanceMetricsService $metricsService The metrics service
     */
    public function __construct(
        private readonly PerformanceMetricsService $metricsService,
    ) {
    }

    /**
     * Handle the message.
     *
     * @param RecordMetricsMessage $message The message to process
     */
    public function __invoke(RecordMetricsMessage $message): void
    {
        $this->metricsService->recordMetrics(
            $message->getRouteName(),
            $message->getEnv(),
            $message->getRequestTime(),
            $message->getTotalQueries(),
            $message->getQueryTime(),
            $message->getParams(),
            $message->getMemoryUsage(),
            $message->getHttpMethod()
        );
    }
}
