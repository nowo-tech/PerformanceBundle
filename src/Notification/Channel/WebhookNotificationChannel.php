<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Notification\Channel;

use Exception;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Event\AfterMetricsRecordedEvent;
use Nowo\PerformanceBundle\Helper\LogHelper;
use Nowo\PerformanceBundle\Notification\NotificationChannelInterface;
use Nowo\PerformanceBundle\Notification\PerformanceAlert;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function sprintf;

/**
 * Generic webhook notification channel.
 *
 * Sends performance alerts to a webhook URL (supports Slack, Teams, custom services).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class WebhookNotificationChannel implements NotificationChannelInterface
{
    /**
     * Creates a new instance.
     *
     * @param HttpClientInterface|null $httpClient Symfony HTTP Client
     * @param string $webhookUrl Webhook URL
     * @param string $format Payload format ('json', 'slack', 'teams')
     * @param array<string, mixed> $headers Additional HTTP headers
     * @param bool $enabled Whether this channel is enabled
     */
    public function __construct(
        private readonly ?HttpClientInterface $httpClient,
        private readonly string $webhookUrl = '',
        private readonly string $format = 'json',
        private readonly array $headers = [],
        private readonly bool $enabled = false,
    ) {
    }

    public function send(PerformanceAlert $alert, RouteData|AfterMetricsRecordedEvent $context): bool
    {
        if (!$this->isEnabled() || $this->httpClient === null || empty($this->webhookUrl)) {
            return false;
        }

        try {
            $payload = $this->buildPayload($alert, $context);
            $headers = array_merge(['Content-Type' => 'application/json'], $this->headers);

            $this->httpClient->request('POST', $this->webhookUrl, [
                'headers' => $headers,
                'json'    => $payload,
            ]);

            return true;
        } catch (Exception $e) {
            // Log error but don't throw (logging enabled by default for backward compatibility)
            LogHelper::logf('Failed to send webhook notification: %s', null, $e->getMessage());

            return false;
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled && $this->httpClient !== null && !empty($this->webhookUrl);
    }

    public function getName(): string
    {
        return 'webhook';
    }

    /**
     * Build the webhook payload based on format.
     *
     * @param PerformanceAlert $alert The alert
     * @param AfterMetricsRecordedEvent|RouteData $context Route or event
     *
     * @return array<string, mixed> Payload array
     */
    private function buildPayload(PerformanceAlert $alert, RouteData|AfterMetricsRecordedEvent $context): array
    {
        return match ($this->format) {
            'slack' => $this->buildSlackPayload($alert, $context),
            'teams' => $this->buildTeamsPayload($alert, $context),
            default => $this->buildJsonPayload($alert, $context),
        };
    }

    /**
     * Build JSON payload (generic format).
     *
     * @param PerformanceAlert $alert The alert
     * @param AfterMetricsRecordedEvent|RouteData $context Route or event
     *
     * @return array<string, mixed>
     */
    private function buildJsonPayload(PerformanceAlert $alert, RouteData|AfterMetricsRecordedEvent $context): array
    {
        $routeData    = $context instanceof AfterMetricsRecordedEvent ? $context->getRouteData() : $context;
        $requestTime  = $context instanceof AfterMetricsRecordedEvent ? $context->getRequestTime() : null;
        $totalQueries = $context instanceof AfterMetricsRecordedEvent ? $context->getTotalQueries() : null;
        $memoryUsage  = $context instanceof AfterMetricsRecordedEvent ? $context->getMemoryUsage() : null;

        return [
            'alert' => [
                'type'     => $alert->getType(),
                'severity' => $alert->getSeverity(),
                'message'  => $alert->getMessage(),
                'context'  => $alert->getContext(),
            ],
            'route' => [
                'name'             => $routeData->getName(),
                'env'              => $routeData->getEnv(),
                'http_method'      => $routeData->getHttpMethod(),
                'request_time'     => $requestTime,
                'query_count'      => $totalQueries,
                'query_time'       => null,
                'memory_usage'     => $memoryUsage,
                'access_count'     => 1,
                'last_accessed_at' => $routeData->getLastAccessedAt()?->format('c'),
            ],
            'timestamp' => date('c'),
        ];
    }

    /**
     * Build Slack webhook payload.
     *
     * @param PerformanceAlert $alert The alert
     * @param AfterMetricsRecordedEvent|RouteData $context Route or event
     *
     * @return array<string, mixed>
     */
    private function buildSlackPayload(PerformanceAlert $alert, RouteData|AfterMetricsRecordedEvent $context): array
    {
        $routeData    = $context instanceof AfterMetricsRecordedEvent ? $context->getRouteData() : $context;
        $requestTime  = $context instanceof AfterMetricsRecordedEvent ? $context->getRequestTime() : null;
        $totalQueries = $context instanceof AfterMetricsRecordedEvent ? $context->getTotalQueries() : null;

        $color = $alert->isCritical() ? 'danger' : 'warning';
        $emoji = $alert->isCritical() ? '🚨' : '⚠️';

        return [
            'text'        => sprintf('%s Performance Alert: %s', $emoji, $alert->getMessage()),
            'attachments' => [
                [
                    'color'  => $color,
                    'title'  => sprintf('%s Alert - %s', ucfirst($alert->getSeverity()), $routeData->getName()),
                    'fields' => [
                        [
                            'title' => 'Route',
                            'value' => $routeData->getName() ?? 'Unknown',
                            'short' => true,
                        ],
                        [
                            'title' => 'Environment',
                            'value' => $routeData->getEnv() ?? 'Unknown',
                            'short' => true,
                        ],
                        [
                            'title' => 'Request Time',
                            'value' => $requestTime !== null ? number_format($requestTime, 4) . 's' : 'N/A',
                            'short' => true,
                        ],
                        [
                            'title' => 'Query Count',
                            'value' => (string) ($totalQueries ?? 'N/A'),
                            'short' => true,
                        ],
                        [
                            'title' => 'Alert Type',
                            'value' => $alert->getType(),
                            'short' => true,
                        ],
                        [
                            'title' => 'Severity',
                            'value' => ucfirst($alert->getSeverity()),
                            'short' => true,
                        ],
                    ],
                    'footer' => 'Performance Bundle',
                    'ts'     => time(),
                ],
            ],
        ];
    }

    /**
     * Build Microsoft Teams webhook payload.
     *
     * @param PerformanceAlert $alert The alert
     * @param AfterMetricsRecordedEvent|RouteData $context Route or event
     *
     * @return array<string, mixed>
     */
    private function buildTeamsPayload(PerformanceAlert $alert, RouteData|AfterMetricsRecordedEvent $context): array
    {
        $routeData    = $context instanceof AfterMetricsRecordedEvent ? $context->getRouteData() : $context;
        $requestTime  = $context instanceof AfterMetricsRecordedEvent ? $context->getRequestTime() : null;
        $totalQueries = $context instanceof AfterMetricsRecordedEvent ? $context->getTotalQueries() : null;

        $color    = $alert->isCritical() ? 'FF0000' : 'FFA500';
        $severity = ucfirst($alert->getSeverity());

        return [
            '@type'      => 'MessageCard',
            '@context'   => 'https://schema.org/extensions',
            'summary'    => sprintf('Performance Alert: %s', $alert->getMessage()),
            'themeColor' => $color,
            'title'      => sprintf('%s Performance Alert', $severity),
            'sections'   => [
                [
                    'activityTitle' => $alert->getMessage(),
                    'facts'         => [
                        [
                            'name'  => 'Route',
                            'value' => $routeData->getName() ?? 'Unknown',
                        ],
                        [
                            'name'  => 'Environment',
                            'value' => $routeData->getEnv() ?? 'Unknown',
                        ],
                        [
                            'name'  => 'Request Time',
                            'value' => $requestTime !== null ? number_format($requestTime, 4) . 's' : 'N/A',
                        ],
                        [
                            'name'  => 'Query Count',
                            'value' => (string) ($totalQueries ?? 'N/A'),
                        ],
                        [
                            'name'  => 'Alert Type',
                            'value' => $alert->getType(),
                        ],
                        [
                            'name'  => 'Severity',
                            'value' => $severity,
                        ],
                    ],
                ],
            ],
        ];
    }
}
