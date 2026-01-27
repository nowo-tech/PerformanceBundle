<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Notification\Channel;

use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Notification\Channel\WebhookNotificationChannel;
use Nowo\PerformanceBundle\Notification\PerformanceAlert;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class WebhookNotificationChannelTest extends TestCase
{
    public function testIsEnabledWhenHttpClientAndUrlProvided(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        
        $channel = new WebhookNotificationChannel(
            $httpClient,
            'https://example.com/webhook',
            'json',
            [],
            true
        );
        
        $this->assertTrue($channel->isEnabled());
    }

    public function testIsDisabledWhenHttpClientIsNull(): void
    {
        $channel = new WebhookNotificationChannel(
            null,
            'https://example.com/webhook',
            'json',
            [],
            true
        );
        
        $this->assertFalse($channel->isEnabled());
    }

    public function testIsDisabledWhenUrlIsEmpty(): void
        {
        $httpClient = $this->createMock(HttpClientInterface::class);
        
        $channel = new WebhookNotificationChannel(
            $httpClient,
            '',
            'json',
            [],
            true
        );
        
        $this->assertFalse($channel->isEnabled());
    }

    public function testIsDisabledWhenExplicitlyDisabled(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        
        $channel = new WebhookNotificationChannel(
            $httpClient,
            'https://example.com/webhook',
            'json',
            [],
            false
        );
        
        $this->assertFalse($channel->isEnabled());
    }

    public function testSendWebhookWithJsonFormat(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://example.com/webhook',
                $this->callback(function ($options) {
                    return isset($options['headers']['Content-Type']) &&
                           $options['headers']['Content-Type'] === 'application/json' &&
                           isset($options['json']['alert']) &&
                           isset($options['json']['route']);
                })
            )
            ->willReturn($response);
        
        $channel = new WebhookNotificationChannel(
            $httpClient,
            'https://example.com/webhook',
            'json',
            [],
            true
        );
        
        $alert = new PerformanceAlert(
            PerformanceAlert::TYPE_REQUEST_TIME,
            PerformanceAlert::SEVERITY_CRITICAL,
            'Test alert',
            ['value' => 1.5]
        );
        
        $routeData = new RouteData();
        $routeData->setName('app_home');
        
        $result = $channel->send($alert, $routeData);
        
        $this->assertTrue($result);
    }

    public function testSendWebhookWithSlackFormat(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://hooks.slack.com/webhook',
                $this->callback(function ($options) {
                    return isset($options['json']['text']) &&
                           isset($options['json']['attachments']);
                })
            )
            ->willReturn($response);
        
        $channel = new WebhookNotificationChannel(
            $httpClient,
            'https://hooks.slack.com/webhook',
            'slack',
            [],
            true
        );
        
        $alert = new PerformanceAlert(
            PerformanceAlert::TYPE_QUERY_COUNT,
            PerformanceAlert::SEVERITY_WARNING,
            'Warning alert'
        );
        
        $routeData = new RouteData();
        
        $result = $channel->send($alert, $routeData);
        
        $this->assertTrue($result);
    }

    public function testSendWebhookWithTeamsFormat(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://outlook.office.com/webhook',
                $this->callback(function ($options) {
                    return isset($options['json']['@type']) &&
                           $options['json']['@type'] === 'MessageCard' &&
                           isset($options['json']['sections']);
                })
            )
            ->willReturn($response);
        
        $channel = new WebhookNotificationChannel(
            $httpClient,
            'https://outlook.office.com/webhook',
            'teams',
            [],
            true
        );
        
        $alert = new PerformanceAlert(
            PerformanceAlert::TYPE_MEMORY_USAGE,
            PerformanceAlert::SEVERITY_CRITICAL,
            'Memory alert'
        );
        
        $routeData = new RouteData();
        
        $result = $channel->send($alert, $routeData);
        
        $this->assertTrue($result);
    }

    public function testSendWebhookHandlesException(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->willThrowException(new \RuntimeException('HTTP error'));
        
        $channel = new WebhookNotificationChannel(
            $httpClient,
            'https://example.com/webhook',
            'json',
            [],
            true
        );
        
        $alert = new PerformanceAlert(
            PerformanceAlert::TYPE_REQUEST_TIME,
            PerformanceAlert::SEVERITY_WARNING,
            'Test alert'
        );
        
        $routeData = new RouteData();
        
        $result = $channel->send($alert, $routeData);
        
        $this->assertFalse($result);
    }

    public function testGetName(): void
    {
        $channel = new WebhookNotificationChannel(
            $this->createMock(HttpClientInterface::class),
            'https://example.com/webhook',
            'json',
            [],
            true
        );
        
        $this->assertSame('webhook', $channel->getName());
    }
}
