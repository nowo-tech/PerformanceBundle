<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Notification\Channel;

use LogicException;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Notification\Channel\WebhookNotificationChannel;
use Nowo\PerformanceBundle\Notification\PerformanceAlert;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;
use Symfony\Component\HttpClient\Exception\TimeoutException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * REQ-RUNTIME-001 / REQ-OBS-001: the webhook call carries the configured timeout as both idle and total
 * deadline, and a timed-out request is swallowed (false) and logged as a warning without the URL.
 */
final class WebhookNotificationChannelTimeoutTest extends TestCase
{
    public function testTimeoutIsPassedAndTimedOutRequestIsLoggedAsWarning(): void
    {
        $client = new class implements HttpClientInterface {
            /** @var array<string, mixed> */
            public array $options = [];

            public function request(string $method, string $url, array $options = []): ResponseInterface
            {
                $this->options = $options;

                throw new TimeoutException('Idle timeout reached for "https://hooks.example.com/secret-path".');
            }

            public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
            {
                throw new LogicException('not used');
            }

            public function withOptions(array $options): static
            {
                return $this;
            }
        };
        $logger = new class extends AbstractLogger {
            /** @var list<array{level: mixed, message: string}> */
            public array $records = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => (string) $message];
            }
        };

        $channel = new WebhookNotificationChannel($client, 'https://hooks.example.com/secret-path', 'json', [], true, 2.5, $logger);
        $alert   = new PerformanceAlert(PerformanceAlert::TYPE_REQUEST_TIME, PerformanceAlert::SEVERITY_WARNING, 'slow');

        self::assertFalse($channel->send($alert, new RouteData()));
        self::assertSame(2.5, $client->options['timeout']);
        self::assertSame(2.5, $client->options['max_duration']);
        self::assertSame(0, $client->options['max_redirects']);
        self::assertSame('warning', $logger->records[0]['level']);
        self::assertStringStartsWith('Failed to send webhook notification: Idle timeout reached', $logger->records[0]['message']);
    }

    public function testNonPositiveTimeoutIsClampedToAMinimumDeadline(): void
    {
        $client = new class implements HttpClientInterface {
            /** @var array<string, mixed> */
            public array $options = [];

            public function request(string $method, string $url, array $options = []): ResponseInterface
            {
                $this->options = $options;

                throw new TimeoutException('timeout');
            }

            public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
            {
                throw new LogicException('not used');
            }

            public function withOptions(array $options): static
            {
                return $this;
            }
        };

        $channel = new WebhookNotificationChannel($client, 'https://hooks.example.com', 'json', [], true, 0.0);
        $channel->send(new PerformanceAlert(PerformanceAlert::TYPE_REQUEST_TIME, PerformanceAlert::SEVERITY_WARNING, 'slow'), new RouteData());

        self::assertSame(0.1, $client->options['timeout']);
    }
}
