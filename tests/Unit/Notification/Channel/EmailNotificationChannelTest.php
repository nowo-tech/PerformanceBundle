<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Notification\Channel;

use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Event\AfterMetricsRecordedEvent;
use Nowo\PerformanceBundle\Notification\Channel\EmailNotificationChannel;
use Nowo\PerformanceBundle\Notification\PerformanceAlert;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EmailNotificationChannelTest extends TestCase
{
    private const MAILER_INTERFACE = 'Symfony\Component\Mailer\MailerInterface';
    private const EMAIL_CLASS      = 'Symfony\Component\Mime\Email';

    protected function setUp(): void
    {
        parent::setUp();
        if (!interface_exists(self::MAILER_INTERFACE) && !class_exists(self::MAILER_INTERFACE)) {
            $this->markTestSkipped('symfony/mailer is not installed.');
        }
    }

    public function testGetName(): void
    {
        $channel = new EmailNotificationChannel(null, 'a@b.com', [], false);
        $this->assertSame('email', $channel->getName());
    }

    public function testIsEnabledWhenDisabled(): void
    {
        $channel = new EmailNotificationChannel(null, 'a@b.com', ['x@y.com'], false);
        $this->assertFalse($channel->isEnabled());
    }

    public function testIsEnabledWhenMailerNull(): void
    {
        $channel = new EmailNotificationChannel(null, 'a@b.com', ['x@y.com'], true);
        $this->assertFalse($channel->isEnabled());
    }

    public function testIsEnabledWhenToEmailsEmpty(): void
    {
        $mailer  = $this->createMock(self::MAILER_INTERFACE);
        $channel = new EmailNotificationChannel($mailer, 'a@b.com', [], true);
        $this->assertFalse($channel->isEnabled());
    }

    public function testIsEnabledWhenAllPresent(): void
    {
        $mailer  = $this->createMock(self::MAILER_INTERFACE);
        $channel = new EmailNotificationChannel($mailer, 'from@example.com', ['to@example.com'], true);
        $this->assertTrue($channel->isEnabled());
    }

    public function testSendReturnsFalseWhenNotEnabled(): void
    {
        $channel = new EmailNotificationChannel(null, 'a@b.com', [], false);
        $alert   = new PerformanceAlert(PerformanceAlert::TYPE_REQUEST_TIME, PerformanceAlert::SEVERITY_WARNING, 'msg');
        $route   = new RouteData();
        $route->setName('app_home')->setEnv('dev');

        $this->assertFalse($channel->send($alert, $route));
    }

    public function testSendReturnsFalseWhenToEmailsEmpty(): void
    {
        $mailer  = $this->createMock(self::MAILER_INTERFACE);
        $channel = new EmailNotificationChannel($mailer, 'from@x.com', [], true);
        $alert   = new PerformanceAlert(PerformanceAlert::TYPE_REQUEST_TIME, PerformanceAlert::SEVERITY_WARNING, 'msg');
        $route   = new RouteData();
        $route->setName('r')->setEnv('dev');

        $this->assertFalse($channel->send($alert, $route));
    }

    public function testSendWithRouteDataContextCallsMailer(): void
    {
        if (!class_exists(self::EMAIL_CLASS)) {
            $this->markTestSkipped('Symfony\Component\Mime\Email is not available.');
        }
        $mailer = $this->createMock(self::MAILER_INTERFACE);
        $mailer->expects($this->once())->method('send')->with($this->isInstanceOf(self::EMAIL_CLASS));

        $channel = new EmailNotificationChannel($mailer, 'from@example.com', ['to@example.com'], true);
        $alert   = new PerformanceAlert(PerformanceAlert::TYPE_REQUEST_TIME, PerformanceAlert::SEVERITY_WARNING, 'Test');
        $route   = new RouteData();
        $route->setName('app_home')->setEnv('dev');

        $this->assertTrue($channel->send($alert, $route));
    }

    public function testSendWithAfterMetricsRecordedEventContext(): void
    {
        if (!class_exists(self::EMAIL_CLASS)) {
            $this->markTestSkipped('Symfony\Component\Mime\Email is not available.');
        }
        $mailer = $this->createMock(self::MAILER_INTERFACE);
        $mailer->expects($this->once())->method('send')->with($this->isInstanceOf(self::EMAIL_CLASS));

        $channel = new EmailNotificationChannel($mailer, 'from@example.com', ['to@example.com'], true);
        $alert   = new PerformanceAlert(PerformanceAlert::TYPE_QUERY_COUNT, PerformanceAlert::SEVERITY_CRITICAL, 'High queries');
        $route   = new RouteData();
        $route->setName('api_foo')->setEnv('prod');
        $event = new AfterMetricsRecordedEvent($route, true, 0.5, 25, 1024 * 1024);

        $this->assertTrue($channel->send($alert, $event));
    }

    public function testSendReturnsFalseWhenMailerThrows(): void
    {
        $mailer = $this->createMock(self::MAILER_INTERFACE);
        $mailer->method('send')->willThrowException(new RuntimeException('SMTP error'));

        $channel = new EmailNotificationChannel($mailer, 'from@example.com', ['to@example.com'], true);
        $alert   = new PerformanceAlert(PerformanceAlert::TYPE_REQUEST_TIME, PerformanceAlert::SEVERITY_WARNING, 'Test');
        $route   = new RouteData();
        $route->setName('app_home')->setEnv('dev');

        $this->assertFalse($channel->send($alert, $route));
    }
}
