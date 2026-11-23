<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Notification\Channel;

use Exception;
use Nowo\PerformanceBundle\Model\RouteMetricsSnapshot;
use Nowo\PerformanceBundle\Notification\PerformanceAlert;
use Nowo\PerformanceBundle\Notification\RouteMetricsNotificationChannelInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function filter_var;
use function is_array;
use function is_string;
use function parse_url;
use function sprintf;
use function str_ends_with;
use function strtolower;

use const FILTER_FLAG_NO_PRIV_RANGE;
use const FILTER_FLAG_NO_RES_RANGE;
use const FILTER_VALIDATE_IP;

/**
 * Generic webhook notification channel.
 *
 * Sends performance alerts to a webhook URL (supports Slack, Teams, custom services).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class WebhookNotificationChannel implements RouteMetricsNotificationChannelInterface
{
    /**
     * Creates a new instance.
     *
     * @param HttpClientInterface|null $httpClient Symfony HTTP Client
     * @param string $webhookUrl Webhook URL
     * @param string $format Payload format ('json', 'slack', 'teams')
     * @param array<string, mixed> $headers Additional HTTP headers
     * @param bool $enabled Whether this channel is enabled
     * @param float $timeout HTTP timeout in seconds (from nowo_performance.notifications.http_timeout)
     */
    public function __construct(
        private ?HttpClientInterface $httpClient,
        private string $webhookUrl = '',
        private string $format = 'json',
        private array $headers = [],
        private bool $enabled = false,
        private float $timeout = 10.0,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function sendRouteMetrics(PerformanceAlert $alert, RouteMetricsSnapshot $route): bool
    {
        if (!$this->isEnabled() || !$this->httpClient instanceof HttpClientInterface || ($this->webhookUrl === '' || $this->webhookUrl === '0')) {
            return false;
        }

        try {
            if (!$this->isSafeWebhookUrl($this->webhookUrl)) {
                $this->bundleLog('warning', 'Blocked webhook notification to unsafe URL (https public hosts only).');

                return false;
            }

            $payload = $this->buildPayload($alert, $route);
            $headers = array_merge(['Content-Type' => 'application/json'], $this->headers);
            $timeout = max(0.1, $this->timeout);

            $this->httpClient->request('POST', $this->webhookUrl, [
                'headers'      => $headers,
                'json'         => $payload,
                'timeout'      => $timeout,
                'max_duration' => $timeout,
                // Never follow redirects: the URL safety check above only covers the configured URL,
                // so a redirect could otherwise send the payload to an http:// or private/internal host.
                'max_redirects' => 0,
            ]);

            return true;
        } catch (Exception $e) {
            // Log error but don't throw (logging enabled by default for backward compatibility)
            $this->bundleLog('warning', 'Failed to send webhook notification: %s', $e->getMessage());

            return false;
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled && $this->httpClient instanceof HttpClientInterface && ($this->webhookUrl !== '' && $this->webhookUrl !== '0');
    }

    public function getName(): string
    {
        return 'webhook';
    }

    /**
     * Only https:// URLs to non-private hosts (SSRF mitigation for operator-configured webhooks).
     */
    private function isSafeWebhookUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https') {
            return false;
        }
        $host = $parts['host'] ?? '';
        if (!is_string($host) || $host === '') {
            return false;
        }
        $lower = strtolower($host);
        if ($lower === 'localhost' || str_ends_with($lower, '.localhost') || str_ends_with($lower, '.local')) {
            return false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        return true;
    }

    /**
     * Build the webhook payload based on format.
     *
     * @param PerformanceAlert $alert The alert
     * @param RouteMetricsSnapshot $route Route (with the metrics just recorded, when known)
     *
     * @return array<string, mixed> Payload array
     */
    private function buildPayload(PerformanceAlert $alert, RouteMetricsSnapshot $route): array
    {
        return match ($this->format) {
            'slack' => $this->buildSlackPayload($alert, $route),
            'teams' => $this->buildTeamsPayload($alert, $route),
            default => $this->buildJsonPayload($alert, $route),
        };
    }

    /**
     * Build JSON payload (generic format).
     *
     * @param PerformanceAlert $alert The alert
     * @param RouteMetricsSnapshot $route Route (with the metrics just recorded, when known)
     *
     * @return array<string, mixed>
     */
    private function buildJsonPayload(PerformanceAlert $alert, RouteMetricsSnapshot $route): array
    {
        return [
            'alert' => [
                'type'     => $alert->getType(),
                'severity' => $alert->getSeverity(),
                'message'  => $alert->getMessage(),
                'context'  => $alert->getContext(),
            ],
            'route' => [
                'name'             => $route->routeName,
                'env'              => $route->env,
                'http_method'      => $route->httpMethod,
                'request_time'     => $route->requestTime,
                'query_count'      => $route->totalQueries,
                'query_time'       => null,
                'memory_usage'     => $route->memoryUsage,
                'access_count'     => 1,
                'last_accessed_at' => $route->lastAccessedAt?->format('c'),
            ],
            'timestamp' => date('c'),
        ];
    }

    /**
     * Build Slack webhook payload.
     *
     * @param PerformanceAlert $alert The alert
     * @param RouteMetricsSnapshot $route Route (with the metrics just recorded, when known)
     *
     * @return array<string, mixed>
     */
    private function buildSlackPayload(PerformanceAlert $alert, RouteMetricsSnapshot $route): array
    {
        $color = $alert->isCritical() ? 'danger' : 'warning';
        $emoji = $alert->isCritical() ? '🚨' : '⚠️';

        return [
            'text'        => sprintf('%s Performance Alert: %s', $emoji, $alert->getMessage()),
            'attachments' => [
                [
                    'color'  => $color,
                    'title'  => sprintf('%s Alert - %s', ucfirst($alert->getSeverity()), $route->routeName),
                    'fields' => [
                        [
                            'title' => 'Route',
                            'value' => $route->routeName ?? 'Unknown',
                            'short' => true,
                        ],
                        [
                            'title' => 'Environment',
                            'value' => $route->env ?? 'Unknown',
                            'short' => true,
                        ],
                        [
                            'title' => 'Request Time',
                            'value' => $route->requestTime !== null ? number_format($route->requestTime, 4) . 's' : 'N/A',
                            'short' => true,
                        ],
                        [
                            'title' => 'Query Count',
                            'value' => (string) ($route->totalQueries ?? 'N/A'),
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
     * @param RouteMetricsSnapshot $route Route (with the metrics just recorded, when known)
     *
     * @return array<string, mixed>
     */
    private function buildTeamsPayload(PerformanceAlert $alert, RouteMetricsSnapshot $route): array
    {
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
                            'value' => $route->routeName ?? 'Unknown',
                        ],
                        [
                            'name'  => 'Environment',
                            'value' => $route->env ?? 'Unknown',
                        ],
                        [
                            'name'  => 'Request Time',
                            'value' => $route->requestTime !== null ? number_format($route->requestTime, 4) . 's' : 'N/A',
                        ],
                        [
                            'name'  => 'Query Count',
                            'value' => (string) ($route->totalQueries ?? 'N/A'),
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

    /**
     * Logs through the injected PSR-3 logger (REQ-OBS-001 / REQ-DI-001).
     *
     * @param string $level PSR-3 level
     * @param string $format sprintf() format (used verbatim when no arguments are given)
     * @param mixed ...$args sprintf() arguments
     */
    private function bundleLog(string $level, string $format, mixed ...$args): void
    {
        $this->logger?->log($level, $args === [] ? $format : sprintf($format, ...$args), ['bundle' => 'performance']);
    }
}
