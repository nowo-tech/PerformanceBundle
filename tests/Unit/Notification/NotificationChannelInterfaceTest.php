<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Notification;

use Nowo\PerformanceBundle\Notification\Channel\EmailNotificationChannel;
use Nowo\PerformanceBundle\Notification\Channel\WebhookNotificationChannel;
use Nowo\PerformanceBundle\Notification\NotificationChannelInterface;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that built-in notification channels implement NotificationChannelInterface.
 */
final class NotificationChannelInterfaceTest extends TestCase
{
    public function testEmailNotificationChannelImplementsInterface(): void
    {
        if (!interface_exists('Symfony\Component\Mailer\MailerInterface')) {
            $this->markTestSkipped('symfony/mailer is not installed.');
        }
        $channel = new EmailNotificationChannel(null, 'from@example.com', [], false);
        $this->assertInstanceOf(NotificationChannelInterface::class, $channel);
    }

    public function testWebhookNotificationChannelImplementsInterface(): void
    {
        if (!interface_exists('Symfony\Contracts\HttpClient\HttpClientInterface')) {
            $this->markTestSkipped('symfony/http-client is not installed.');
        }
        $channel = new WebhookNotificationChannel(null, '', 'json', [], false);
        $this->assertInstanceOf(NotificationChannelInterface::class, $channel);
    }

    public function testInterfaceHasRequiredMethods(): void
    {
        $ref = new \ReflectionClass(NotificationChannelInterface::class);
        $this->assertTrue($ref->hasMethod('send'));
        $this->assertTrue($ref->hasMethod('isEnabled'));
        $this->assertTrue($ref->hasMethod('getName'));
    }
}
