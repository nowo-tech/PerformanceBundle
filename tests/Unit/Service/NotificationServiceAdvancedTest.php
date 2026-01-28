<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Service;

use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Notification\NotificationChannelInterface;
use Nowo\PerformanceBundle\Notification\PerformanceAlert;
use Nowo\PerformanceBundle\Service\NotificationService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Advanced tests for NotificationService.
 */
final class NotificationServiceAdvancedTest extends TestCase
{
    public function testSendAlertWhenDisabled(): void
    {
        $service = new NotificationService([], false);

        $alert = $this->createMock(PerformanceAlert::class);
        $routeData = $this->createMock(RouteData::class);

        $result = $service->sendAlert($alert, $routeData);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testSendAlertWithSingleChannel(): void
    {
        $channel = $this->createMock(NotificationChannelInterface::class);
        $channel->method('isEnabled')->willReturn(true);
        $channel->method('getName')->willReturn('email');
        $channel->method('send')->willReturn(true);

        $service = new NotificationService([$channel], true);

        $alert = $this->createMock(PerformanceAlert::class);
        $routeData = $this->createMock(RouteData::class);

        $result = $service->sendAlert($alert, $routeData);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('email', $result);
        $this->assertTrue($result['email']);
    }

    public function testSendAlertWithMultipleChannels(): void
    {
        $channel1 = $this->createMock(NotificationChannelInterface::class);
        $channel1->method('isEnabled')->willReturn(true);
        $channel1->method('getName')->willReturn('email');
        $channel1->method('send')->willReturn(true);

        $channel2 = $this->createMock(NotificationChannelInterface::class);
        $channel2->method('isEnabled')->willReturn(true);
        $channel2->method('getName')->willReturn('webhook');
        $channel2->method('send')->willReturn(true);

        $service = new NotificationService([$channel1, $channel2], true);

        $alert = $this->createMock(PerformanceAlert::class);
        $routeData = $this->createMock(RouteData::class);

        $result = $service->sendAlert($alert, $routeData);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertArrayHasKey('email', $result);
        $this->assertArrayHasKey('webhook', $result);
        $this->assertTrue($result['email']);
        $this->assertTrue($result['webhook']);
    }

    public function testSendAlertWithDisabledChannel(): void
    {
        $channel = $this->createMock(NotificationChannelInterface::class);
        $channel->method('isEnabled')->willReturn(false);
        $channel->method('getName')->willReturn('email');

        $service = new NotificationService([$channel], true);

        $alert = $this->createMock(PerformanceAlert::class);
        $routeData = $this->createMock(RouteData::class);

        $result = $service->sendAlert($alert, $routeData);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testSendAlertWithChannelException(): void
    {
        $channel = $this->createMock(NotificationChannelInterface::class);
        $channel->method('isEnabled')->willReturn(true);
        $channel->method('getName')->willReturn('email');
        $channel->method('send')->willThrowException(new \Exception('Channel error'));

        $service = new NotificationService([$channel], true);

        $alert = $this->createMock(PerformanceAlert::class);
        $routeData = $this->createMock(RouteData::class);

        $result = $service->sendAlert($alert, $routeData);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('email', $result);
        $this->assertFalse($result['email']);
    }

    public function testSendAlertWithMixedChannels(): void
    {
        $channel1 = $this->createMock(NotificationChannelInterface::class);
        $channel1->method('isEnabled')->willReturn(true);
        $channel1->method('getName')->willReturn('email');
        $channel1->method('send')->willReturn(true);

        $channel2 = $this->createMock(NotificationChannelInterface::class);
        $channel2->method('isEnabled')->willReturn(false);
        $channel2->method('getName')->willReturn('webhook');

        $channel3 = $this->createMock(NotificationChannelInterface::class);
        $channel3->method('isEnabled')->willReturn(true);
        $channel3->method('getName')->willReturn('slack');
        $channel3->method('send')->willReturn(false);

        $service = new NotificationService([$channel1, $channel2, $channel3], true);

        $alert = $this->createMock(PerformanceAlert::class);
        $routeData = $this->createMock(RouteData::class);

        $result = $service->sendAlert($alert, $routeData);

        $this->assertIsArray($result);
        $this->assertCount(2, $result); // Only enabled channels
        $this->assertArrayHasKey('email', $result);
        $this->assertArrayHasKey('slack', $result);
        $this->assertTrue($result['email']);
        $this->assertFalse($result['slack']);
    }

    public function testIsEnabled(): void
    {
        $service1 = new NotificationService([], true);
        $this->assertTrue($service1->isEnabled());

        $service2 = new NotificationService([], false);
        $this->assertFalse($service2->isEnabled());
    }

    public function testGetEnabledChannelsWithNoChannels(): void
    {
        $service = new NotificationService([], true);

        $result = $service->getEnabledChannels();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetEnabledChannelsWithAllEnabled(): void
    {
        $channel1 = $this->createMock(NotificationChannelInterface::class);
        $channel1->method('isEnabled')->willReturn(true);
        $channel1->method('getName')->willReturn('email');

        $channel2 = $this->createMock(NotificationChannelInterface::class);
        $channel2->method('isEnabled')->willReturn(true);
        $channel2->method('getName')->willReturn('webhook');

        $service = new NotificationService([$channel1, $channel2], true);

        $result = $service->getEnabledChannels();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertContains('email', $result);
        $this->assertContains('webhook', $result);
    }

    public function testGetEnabledChannelsWithSomeDisabled(): void
    {
        $channel1 = $this->createMock(NotificationChannelInterface::class);
        $channel1->method('isEnabled')->willReturn(true);
        $channel1->method('getName')->willReturn('email');

        $channel2 = $this->createMock(NotificationChannelInterface::class);
        $channel2->method('isEnabled')->willReturn(false);
        $channel2->method('getName')->willReturn('webhook');

        $channel3 = $this->createMock(NotificationChannelInterface::class);
        $channel3->method('isEnabled')->willReturn(true);
        $channel3->method('getName')->willReturn('slack');

        $service = new NotificationService([$channel1, $channel2, $channel3], true);

        $result = $service->getEnabledChannels();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertContains('email', $result);
        $this->assertContains('slack', $result);
        $this->assertNotContains('webhook', $result);
    }

    public function testGetEnabledChannelsWithAllDisabled(): void
    {
        $channel1 = $this->createMock(NotificationChannelInterface::class);
        $channel1->method('isEnabled')->willReturn(false);
        $channel1->method('getName')->willReturn('email');

        $channel2 = $this->createMock(NotificationChannelInterface::class);
        $channel2->method('isEnabled')->willReturn(false);
        $channel2->method('getName')->willReturn('webhook');

        $service = new NotificationService([$channel1, $channel2], true);

        $result = $service->getEnabledChannels();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
