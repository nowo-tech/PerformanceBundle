<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Notification\Channel;

use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Event\AfterMetricsRecordedEvent;
use Nowo\PerformanceBundle\Helper\LogHelper;
use Nowo\PerformanceBundle\Notification\NotificationChannelInterface;
use Nowo\PerformanceBundle\Notification\PerformanceAlert;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Email notification channel.
 *
 * Sends performance alerts via email using Symfony Mailer.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class EmailNotificationChannel implements NotificationChannelInterface
{
    /**
     * Creates a new instance.
     *
     * @param MailerInterface|null $mailer    Symfony Mailer service
     * @param string               $fromEmail Sender email address
     * @param array<string>        $toEmails  Recipient email addresses
     * @param bool                 $enabled   Whether this channel is enabled
     */
    public function __construct(
        private readonly ?MailerInterface $mailer,
        private readonly string $fromEmail = 'noreply@example.com',
        private readonly array $toEmails = [],
        private readonly bool $enabled = false,
    ) {
    }

    public function send(PerformanceAlert $alert, RouteData|AfterMetricsRecordedEvent $context): bool
    {
        if (!$this->isEnabled() || null === $this->mailer || empty($this->toEmails)) {
            return false;
        }

        $routeData = $context instanceof AfterMetricsRecordedEvent ? $context->getRouteData() : $context;

        try {
            $subject = \sprintf(
                '[Performance Alert] %s: %s - %s',
                strtoupper($alert->getSeverity()),
                $routeData->getName() ?? 'Unknown Route',
                $alert->getType()
            );

            $body = $this->buildEmailBody($alert, $context);

            $email = (new Email())
                ->from($this->fromEmail)
                ->to(...$this->toEmails)
                ->subject($subject)
                ->html($body)
                ->text(strip_tags($body));

            $this->mailer->send($email);

            return true;
        } catch (\Exception $e) {
            // Log error but don't throw (logging enabled by default for backward compatibility)
            LogHelper::logf('Failed to send email notification: %s', null, $e->getMessage());

            return false;
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled && null !== $this->mailer && !empty($this->toEmails);
    }

    public function getName(): string
    {
        return 'email';
    }

    /**
     * Build the email body HTML.
     *
     * @param PerformanceAlert                    $alert   The alert
     * @param RouteData|AfterMetricsRecordedEvent $context Route or event (event has just-recorded metrics)
     *
     * @return string HTML email body
     */
    private function buildEmailBody(PerformanceAlert $alert, RouteData|AfterMetricsRecordedEvent $context): string
    {
        $routeData = $context instanceof AfterMetricsRecordedEvent ? $context->getRouteData() : $context;
        $requestTime = $context instanceof AfterMetricsRecordedEvent ? $context->getRequestTime() : null;
        $totalQueries = $context instanceof AfterMetricsRecordedEvent ? $context->getTotalQueries() : null;
        $memoryUsage = $context instanceof AfterMetricsRecordedEvent ? $context->getMemoryUsage() : null;

        $severityColor = $alert->isCritical() ? '#dc3545' : '#ffc107';
        $severityLabel = ucfirst($alert->getSeverity());

        $httpMethod = $routeData->getHttpMethod() ?? 'N/A';
        $requestTimeStr = null !== $requestTime ? number_format($requestTime, 4).'s' : 'N/A';
        $queryCountStr = null !== $totalQueries ? (string) $totalQueries : 'N/A';
        $queryTimeStr = 'N/A';
        $memoryUsageStr = null !== $memoryUsage ? number_format($memoryUsage / 1024 / 1024, 2).' MB' : 'N/A';
        $lastAccessed = null !== $routeData->getLastAccessedAt() ? $routeData->getLastAccessedAt()->format('Y-m-d H:i:s') : 'N/A';
        $accessCountStr = $context instanceof AfterMetricsRecordedEvent ? '1' : 'N/A';

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .alert-box { border-left: 4px solid {$severityColor}; padding: 15px; margin: 20px 0; background-color: #f8f9fa; }
        .metric { margin: 10px 0; }
        .label { font-weight: bold; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <div class="alert-box">
        <h2 style="color: {$severityColor}; margin-top: 0;">{$severityLabel} Performance Alert</h2>
        <p><strong>Message:</strong> {$alert->getMessage()}</p>
    </div>

    <h3>Route Information</h3>
    <table>
        <tr><th>Property</th><th>Value</th></tr>
        <tr><td>Route Name</td><td><code>{$routeData->getName()}</code></td></tr>
        <tr><td>Environment</td><td>{$routeData->getEnv()}</td></tr>
        <tr><td>HTTP Method</td><td>{$httpMethod}</td></tr>
        <tr><td>Request Time</td><td>{$requestTimeStr}</td></tr>
        <tr><td>Query Count</td><td>{$queryCountStr}</td></tr>
        <tr><td>Query Time</td><td>{$queryTimeStr}</td></tr>
        <tr><td>Memory Usage</td><td>{$memoryUsageStr}</td></tr>
        <tr><td>Access Count</td><td>{$accessCountStr}</td></tr>
        <tr><td>Last Accessed</td><td>{$lastAccessed}</td></tr>
    </table>

    <h3>Alert Details</h3>
    <table>
        <tr><th>Property</th><th>Value</th></tr>
        <tr><td>Type</td><td>{$alert->getType()}</td></tr>
        <tr><td>Severity</td><td>{$severityLabel}</td></tr>
    </table>

    <p style="margin-top: 30px; color: #666; font-size: 12px;">
        This is an automated alert from the Performance Bundle.
    </p>
</body>
</html>
HTML;

        return $html;
    }
}
