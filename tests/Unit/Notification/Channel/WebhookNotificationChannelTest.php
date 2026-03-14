<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Notification\Channel;

use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Event\AfterMetricsRecordedEvent;
use Nowo\PerformanceBundle\Notification\Channel\WebhookNotificationChannel;
use Nowo\PerformanceBundle\Notification\PerformanceAlert;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class WebhookNotificationChannelTest extends TestCase
{
    private const HTTP_CLIENT_INTERFACE = \Symfony\Contracts\HttpClient\HttpClientInterface::class;
    private const RESPONSE_INTERFACE    = \Symfony\Contracts\HttpClient\ResponseInterface::class;

    protected function setUp(): void
    {
        parent::setUp();
        if (!interface_exists(self::HTTP_CLIENT_INTERFACE) && !class_exists(self::HTTP_CLIENT_INTERFACE)) {
            $this->markTestSkipped('symfony/http-client is not installed.');
        }
    }

    public function testGetName(): void
    {
        $channel = new WebhookNotificationChannel(null, '', 'json', [], false);
        $this->assertSame('webhook', $channel->getName());
    }

    public function testIsEnabledWhenDisabled(): void
    {
        $client  = $this->createMock(self::HTTP_CLIENT_INTERFACE);
        $channel = new WebhookNotificationChannel($client, 'https://hooks.example.com', 'json', [], false);
        $this->assertFalse($channel->isEnabled());
    }

    public function testIsEnabledWhenHttpClientNull(): void
    {
        $channel = new WebhookNotificationChannel(null, 'https://hooks.example.com', 'json', [], true);
        $this->assertFalse($channel->isEnabled());
    }

    public function testIsEnabledWhenWebhookUrlEmpty(): void
    {
        $client  = $this->createMock(self::HTTP_CLIENT_INTERFACE);
        $channel = new WebhookNotificationChannel($client, '', 'json', [], true);
        $this->assertFalse($channel->isEnabled());
    }

    public function testIsEnabledWhenAllPresent(): void
    {
        $client  = $this->createMock(self::HTTP_CLIENT_INTERFACE);
        $channel = new WebhookNotificationChannel($client, 'https://hooks.example.com', 'json', [], true);
        $this->assertTrue($channel->isEnabled());
    }

    public function testSendReturnsFalseWhenNotEnabled(): void
    {
        $channel = new WebhookNotificationChannel(null, '', 'json', [], false);
        $alert   = new PerformanceAlert(PerformanceAlert::TYPE_REQUEST_TIME, PerformanceAlert::SEVERITY_WARNING, 'msg');
        $route   = new RouteData();
        $route->setName('app_home')->setEnv('dev');

        $this->assertFalse($channel->send($alert, $route));
    }

    public function testSendWithRouteDataContextCallsHttpClient(): void
    {
        if (!interface_exists(self::RESPONSE_INTERFACE) && !class_exists(self::RESPONSE_INTERFACE)) {
            $this->markTestSkipped('Symfony HttpClient ResponseInterface is not available.');
        }
        $response = $this->createMock(self::RESPONSE_INTERFACE);
        $client   = $this->createMock(self::HTTP_CLIENT_INTERFACE);
        $client->expects($this->once())
            ->method('request')
            ->with('POST', 'https://hooks.example.com', $this->callback(static fn (array $opts): bool => isset($opts['headers']['Content-Type']) && $opts['headers']['Content-Type'] === 'application/json'
                && isset($opts['json']['alert']) && isset($opts['json']['route'])))
            ->willReturn($response);

        $channel = new WebhookNotificationChannel($client, 'https://hooks.example.com', 'json', [], true);
        $alert   = new PerformanceAlert(PerformanceAlert::TYPE_REQUEST_TIME, PerformanceAlert::SEVERITY_WARNING, 'Test');
        $route   = new RouteData();
        $route->setName('app_home')->setEnv('dev');

        $this->assertTrue($channel->send($alert, $route));
    }

    public function testSendWithAfterMetricsRecordedEventContext(): void
    {
        if (!interface_exists(self::RESPONSE_INTERFACE) && !class_exists(self::RESPONSE_INTERFACE)) {
            $this->markTestSkipped('Symfony HttpClient ResponseInterface is not available.');
        }
        $response = $this->createMock(self::RESPONSE_INTERFACE);
        $client   = $this->createMock(self::HTTP_CLIENT_INTERFACE);
        $client->expects($this->once())->method('request')->with('POST', $this->anything(), $this->anything())->willReturn($response);

        $channel = new WebhookNotificationChannel($client, 'https://hooks.slack.com/x', 'json', [], true);
        $alert   = new PerformanceAlert(PerformanceAlert::TYPE_QUERY_COUNT, PerformanceAlert::SEVERITY_CRITICAL, 'High');
        $route   = new RouteData();
        $route->setName('api_foo')->setEnv('prod');
        $event = new AfterMetricsRecordedEvent($route, true, 0.3, 10);

        $this->assertTrue($channel->send($alert, $event));
    }

    public function testSendReturnsFalseWhenHttpClientThrows(): void
    {
        $client = $this->createMock(self::HTTP_CLIENT_INTERFACE);
        $client->method('request')->willThrowException(new RuntimeException('Connection refused'));

        $channel = new WebhookNotificationChannel($client, 'https://hooks.example.com', 'json', [], true);
        $alert   = new PerformanceAlert(PerformanceAlert::TYPE_REQUEST_TIME, PerformanceAlert::SEVERITY_WARNING, 'Test');
        $route   = new RouteData();
        $route->setName('app_home')->setEnv('dev');

        $this->assertFalse($channel->send($alert, $route));
    }

    public function testSendWithSlackFormatBuildsSlackPayload(): void
    {
        if (!interface_exists(self::RESPONSE_INTERFACE) && !class_exists(self::RESPONSE_INTERFACE)) {
            $this->markTestSkipped('Symfony HttpClient ResponseInterface is not available.');
        }
        $response = $this->createMock(self::RESPONSE_INTERFACE);
        $client   = $this->createMock(self::HTTP_CLIENT_INTERFACE);
        $client->expects($this->once())
            ->method('request')
            ->with('POST', 'https://hooks.slack.com/x', $this->callback(static function (array $opts): bool {
                if (!isset($opts['json'])) {
                    return false;
                }
                $json = $opts['json'];

                return isset($json['text']) || isset($json['attachments']);
            }))
            ->willReturn($response);

        $channel = new WebhookNotificationChannel($client, 'https://hooks.slack.com/x', 'slack', [], true);
        $alert   = new PerformanceAlert(PerformanceAlert::TYPE_QUERY_COUNT, PerformanceAlert::SEVERITY_CRITICAL, 'Slack alert');
        $route   = new RouteData();
        $route->setName('api')->setEnv('prod');

        $this->assertTrue($channel->send($alert, $route));
    }

    public function testSendWithTeamsFormatBuildsTeamsPayload(): void
    {
        if (!interface_exists(self::RESPONSE_INTERFACE) && !class_exists(self::RESPONSE_INTERFACE)) {
            $this->markTestSkipped('Symfony HttpClient ResponseInterface is not available.');
        }
        $response = $this->createMock(self::RESPONSE_INTERFACE);
        $client   = $this->createMock(self::HTTP_CLIENT_INTERFACE);
        $client->expects($this->once())
            ->method('request')
            ->with('POST', $this->anything(), $this->callback(static fn (array $opts): bool => isset($opts['json']) && (isset($opts['json']['@type']) || isset($opts['json']['text']))))
            ->willReturn($response);

        $channel = new WebhookNotificationChannel($client, 'https://outlook.office.com/webhook/x', 'teams', [], true);
        $alert   = new PerformanceAlert(PerformanceAlert::TYPE_MEMORY_USAGE, PerformanceAlert::SEVERITY_WARNING, 'Teams alert');
        $route   = new RouteData();
        $route->setName('admin')->setEnv('dev');

        $this->assertTrue($channel->send($alert, $route));
    }

    public function testSendReturnsFalseWhenWebhookUrlIsZero(): void
    {
        $client  = $this->createMock(self::HTTP_CLIENT_INTERFACE);
        $channel = new WebhookNotificationChannel($client, '0', 'json', [], true);
        $alert   = new PerformanceAlert(PerformanceAlert::TYPE_REQUEST_TIME, PerformanceAlert::SEVERITY_WARNING, 'Test');
        $route   = new RouteData();

        $this->assertFalse($channel->send($alert, $route));
    }
}
