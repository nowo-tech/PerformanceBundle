<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Notification\Channel;

use Nowo\PerformanceBundle\Entity\RouteData;
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
 * @copyright 2025 Nowo.tech
 */
final class EmailNotificationChannel implements NotificationChannelInterface
{
    /**
     * Constructor.
     *
     * @param MailerInterface|null $mailer Symfony Mailer service
     * @param string $fromEmail Sender email address
     * @param array<string> $toEmails Recipient email addresses
     * @param bool $enabled Whether this channel is enabled
     */
    public function __construct(
        private readonly ?MailerInterface $mailer,
        private readonly string $fromEmail = 'noreply@example.com',
        private readonly array $toEmails = [],
        private readonly bool $enabled = false
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function send(PerformanceAlert $alert, RouteData $routeData): bool
    {
        if (!$this->isEnabled() || $this->mailer === null || empty($this->toEmails)) {
            return false;
        }

        try {
            $subject = sprintf(
                '[Performance Alert] %s: %s - %s',
                strtoupper($alert->getSeverity()),
                $routeData->getName() ?? 'Unknown Route',
                $alert->getType()
            );

            $body = $this->buildEmailBody($alert, $routeData);

            $email = (new Email())
                ->from($this->fromEmail)
                ->to(...$this->toEmails)
                ->subject($subject)
                ->html($body)
                ->text(strip_tags($body));

            $this->mailer->send($email);

            return true;
        } catch (\Exception $e) {
            // Log error but don't throw
            error_log(sprintf('Failed to send email notification: %s', $e->getMessage()));
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled(): bool
    {
        return $this->enabled && $this->mailer !== null && !empty($this->toEmails);
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'email';
    }

    /**
     * Build the email body HTML.
     *
     * @param PerformanceAlert $alert The alert
     * @param RouteData $routeData The route data
     * @return string HTML email body
     */
    private function buildEmailBody(PerformanceAlert $alert, RouteData $routeData): string
    {
        $severityColor = $alert->isCritical() ? '#dc3545' : '#ffc107';
        $severityLabel = ucfirst($alert->getSeverity());

        // Extract values to avoid parsing issues in heredoc
        $httpMethod = $routeData->getHttpMethod() ?? 'N/A';
        $requestTime = $routeData->getRequestTime() !== null ? number_format($routeData->getRequestTime(), 4) . 's' : 'N/A';
        $queryCount = $routeData->getTotalQueries() ?? 'N/A';
        $queryTime = $routeData->getQueryTime() !== null ? number_format($routeData->getQueryTime(), 4) . 's' : 'N/A';
        $memoryUsage = $routeData->getMemoryUsage() !== null ? number_format($routeData->getMemoryUsage() / 1024 / 1024, 2) . ' MB' : 'N/A';
        $lastAccessed = $routeData->getLastAccessedAt() !== null ? $routeData->getLastAccessedAt()->format('Y-m-d H:i:s') : 'N/A';

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
        <tr><td>Request Time</td><td>{$requestTime}</td></tr>
        <tr><td>Query Count</td><td>{$queryCount}</td></tr>
        <tr><td>Query Time</td><td>{$queryTime}</td></tr>
        <tr><td>Memory Usage</td><td>{$memoryUsage}</td></tr>
        <tr><td>Access Count</td><td>{$routeData->getAccessCount()}</td></tr>
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
