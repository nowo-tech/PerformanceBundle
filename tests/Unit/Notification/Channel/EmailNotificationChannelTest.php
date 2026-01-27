<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Notification\Channel;

use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Notification\Channel\EmailNotificationChannel;
use Nowo\PerformanceBundle\Notification\PerformanceAlert;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class EmailNotificationChannelTest extends TestCase
{
    public function testIsEnabledWhenMailerAndEmailsProvided(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        
        $channel = new EmailNotificationChannel(
            $mailer,
            'from@example.com',
            ['to@example.com'],
            true
        );
        
        $this->assertTrue($channel->isEnabled());
    }

    public function testIsDisabledWhenMailerIsNull(): void
    {
        $channel = new EmailNotificationChannel(
            null,
            'from@example.com',
            ['to@example.com'],
            true
        );
        
        $this->assertFalse($channel->isEnabled());
    }

    public function testIsDisabledWhenNoRecipients(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        
        $channel = new EmailNotificationChannel(
            $mailer,
            'from@example.com',
            [],
            true
        );
        
        $this->assertFalse($channel->isEnabled());
    }

    public function testIsDisabledWhenExplicitlyDisabled(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        
        $channel = new EmailNotificationChannel(
            $mailer,
            'from@example.com',
            ['to@example.com'],
            false
        );
        
        $this->assertFalse($channel->isEnabled());
    }

    public function testSendEmail(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->with($this->isInstanceOf(Email::class));
        
        $channel = new EmailNotificationChannel(
            $mailer,
            'from@example.com',
            ['to@example.com'],
            true
        );
        
        $alert = new PerformanceAlert(
            PerformanceAlert::TYPE_REQUEST_TIME,
            PerformanceAlert::SEVERITY_CRITICAL,
            'Test alert message',
            ['value' => 1.5, 'threshold' => 1.0]
        );
        
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('prod');
        $routeData->setRequestTime(1.5);
        
        $result = $channel->send($alert, $routeData);
        
        $this->assertTrue($result);
    }

    public function testSendEmailHandlesException(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->willThrowException(new \RuntimeException('Mailer error'));
        
        $channel = new EmailNotificationChannel(
            $mailer,
            'from@example.com',
            ['to@example.com'],
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
        $channel = new EmailNotificationChannel(
            $this->createMock(MailerInterface::class),
            'from@example.com',
            ['to@example.com'],
            true
        );
        
        $this->assertSame('email', $channel->getName());
    }

    public function testSendEmailWithTwig(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                return str_contains($email->getHtmlBody(), 'Performance Alert') &&
                       str_contains($email->getTextBody(), 'Performance Alert');
            }));

        $loader = new ArrayLoader([
            '@NowoPerformanceBundle/Notification/email_alert.html.twig' => '<!DOCTYPE html><html><body><h1>{{ severityLabel }} Performance Alert</h1><p>{{ alert.message }}</p><p>Route: {{ routeData.name }}</p></body></html>',
            '@NowoPerformanceBundle/Notification/email_alert.txt.twig' => '{{ severityLabel }} Performance Alert\n\nMessage: {{ alert.message }}\nRoute: {{ routeData.name }}',
        ]);
        $twig = new Environment($loader);

        $channel = new EmailNotificationChannel(
            $mailer,
            $twig,
            'from@example.com',
            ['to@example.com'],
            true
        );

        $alert = new PerformanceAlert(
            PerformanceAlert::TYPE_REQUEST_TIME,
            PerformanceAlert::SEVERITY_CRITICAL,
            'Test alert message'
        );

        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('prod');

        $result = $channel->send($alert, $routeData);

        $this->assertTrue($result);
    }

    public function testSendEmailWithTwigFallbackOnError(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->with($this->isInstanceOf(Email::class));

        // Twig that throws exception
        $twig = $this->createMock(Environment::class);
        $twig->method('render')
            ->willThrowException(new \Twig\Error\Error('Template error'));

        $channel = new EmailNotificationChannel(
            $mailer,
            $twig,
            'from@example.com',
            ['to@example.com'],
            true
        );

        $alert = new PerformanceAlert(
            PerformanceAlert::TYPE_REQUEST_TIME,
            PerformanceAlert::SEVERITY_WARNING,
            'Test alert'
        );

        $routeData = new RouteData();
        $routeData->setName('app_home');

        $result = $channel->send($alert, $routeData);

        // Should still send email using fallback
        $this->assertTrue($result);
    }

    public function testSendEmailWithoutTwigUsesFallback(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                $htmlBody = $email->getHtmlBody();
                $textBody = $email->getTextBody();
                return str_contains($htmlBody, 'Performance Alert') &&
                       str_contains($textBody, 'Performance Alert') &&
                       str_contains($htmlBody, 'app_home');
            }));

        $channel = new EmailNotificationChannel(
            $mailer,
            null, // No Twig
            'from@example.com',
            ['to@example.com'],
            true
        );

        $alert = new PerformanceAlert(
            PerformanceAlert::TYPE_REQUEST_TIME,
            PerformanceAlert::SEVERITY_CRITICAL,
            'Test alert message'
        );

        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('prod');
        $routeData->setRequestTime(1.5);

        $result = $channel->send($alert, $routeData);

        $this->assertTrue($result);
    }

    public function testSendEmailWithStatusCodes(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->with($this->isInstanceOf(Email::class));

        $loader = new ArrayLoader([
            '@NowoPerformanceBundle/Notification/email_alert.html.twig' => '<!DOCTYPE html><html><body><h1>{{ severityLabel }} Alert</h1><p>{{ alert.message }}</p><p>Route: {{ routeData.name }}</p><p>HTTP Method: {{ routeData.httpMethod }}</p></body></html>',
            '@NowoPerformanceBundle/Notification/email_alert.txt.twig' => '{{ severityLabel }} Alert\n\nMessage: {{ alert.message }}\nRoute: {{ routeData.name }}\nHTTP Method: {{ routeData.httpMethod }}',
        ]);
        $twig = new Environment($loader);

        $channel = new EmailNotificationChannel(
            $mailer,
            $twig,
            'from@example.com',
            ['to@example.com'],
            true
        );

        $alert = new PerformanceAlert(
            PerformanceAlert::TYPE_REQUEST_TIME,
            PerformanceAlert::SEVERITY_CRITICAL,
            'Test alert'
        );

        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setHttpMethod('POST');
        $routeData->incrementStatusCode(200);
        $routeData->incrementStatusCode(404);

        $result = $channel->send($alert, $routeData);

        $this->assertTrue($result);
    }
}
