<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\PerformanceBundle\Notification\Channel\EmailNotificationChannel;
use Nowo\PerformanceBundle\Notification\Channel\WebhookNotificationChannel;
use Nowo\PerformanceBundle\Notification\NotificationChannelInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Twig\Environment;

/**
 * Example service to configure notifications dynamically from the database.
 *
 * This service demonstrates how to configure notification channels when credentials
 * (webhook URLs, emails, etc.) are stored in the database instead of the YAML file.
 *
 * @example
 * This service can be used in two ways:
 *
 * 1. As a Compiler Pass (recommended for static configuration):
 *    - Create a CompilerPass that registers channels dynamically
 *    - Channels are created once when the container is compiled
 *
 * 2. As a Factory Service (for fully dynamic configuration):
 *    - Creates channels on demand when needed
 *    - Useful when credentials change frequently
 *
 * @see https://symfony.com/doc/current/service_container/compiler_passes.html
 */
class DynamicNotificationConfiguration
{
    /**
     * Creates a new instance.
     *
     * @param EntityManagerInterface   $entityManager Entity manager to access the database
     * @param MailerInterface|null     $mailer        Symfony Mailer (optional)
     * @param HttpClientInterface|null $httpClient    Symfony HTTP Client (optional)
     * @param Environment|null         $twig          Twig environment for rendering templates (optional)
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ?MailerInterface $mailer = null,
        private readonly ?HttpClientInterface $httpClient = null,
        private readonly ?Environment $twig = null,
    ) {
    }

    /**
     * Returns the notification configuration from the database.
     *
     * This method assumes you have an entity or table that stores the configuration.
     * Adjust according to your database structure.
     *
     * @return array<string, mixed> Notification configuration
     *
     * @example
     * [
     *     'enabled' => true,
     *     'email' => [
     *         'enabled' => true,
     *         'from' => 'noreply@example.com',
     *         'to' => ['admin@example.com'],
     *     ],
     *     'slack' => [
     *         'enabled' => true,
     *         'webhook_url' => 'https://hooks.slack.com/services/...',
     *     ],
     *     'teams' => [
     *         'enabled' => false,
     *         'webhook_url' => '',
     *     ],
     *     'webhook' => [
     *         'enabled' => true,
     *         'url' => 'https://api.example.com/webhook',
     *         'format' => 'json',
     *         'headers' => ['X-API-Key' => 'secret-key'],
     *     ],
     * ]
     */
    private function getNotificationConfigFromDatabase(): array
    {
        // Example: get configuration from a Settings entity
        // Adjust according to your database structure

        try {
            // Option 1: From a Settings entity
            $settings = $this->entityManager
                ->getRepository('App\Entity\NotificationSettings')
                ->findOneBy(['key' => 'performance_notifications']);

            if ($settings && $settings->getValue()) {
                return json_decode($settings->getValue(), true) ?? [];
            }

            // Option 2: From a simple config table
            // $connection = $this->entityManager->getConnection();
            // $stmt = $connection->prepare('SELECT * FROM notification_config WHERE type = :type');
            // $stmt->execute(['type' => 'performance']);
            // $config = $stmt->fetchAssociative();
            // return $config ? json_decode($config['value'], true) ?? [] : [];
        } catch (\Exception $e) {
            // Log error and return default configuration
            error_log(\sprintf(
                'Error loading notification config from database: %s',
                $e->getMessage()
            ));
        }

        // Default configuration when no data in DB
        return [
            'enabled' => false,
            'email' => ['enabled' => false],
            'slack' => ['enabled' => false],
            'teams' => ['enabled' => false],
            'webhook' => ['enabled' => false],
        ];
    }

    /**
     * Creates notification channels from the database configuration.
     *
     * This method creates channel instances with credentials fetched from the database.
     *
     * @return array<NotificationChannelInterface> Array of configured channels
     */
    public function createChannelsFromDatabase(): array
    {
        $config = $this->getNotificationConfigFromDatabase();
        $channels = [];

        // Create Email channel if enabled
        if ($config['email']['enabled'] ?? false) {
            $channels[] = new EmailNotificationChannel(
                $this->mailer,
                $this->twig,
                $config['email']['from'] ?? 'noreply@example.com',
                $config['email']['to'] ?? [],
                true
            );
        }

        // Create Slack channel if enabled
        if ($config['slack']['enabled'] ?? false && !empty($config['slack']['webhook_url'] ?? '')) {
            $channels[] = new WebhookNotificationChannel(
                $this->httpClient,
                $config['slack']['webhook_url'],
                'slack',
                [],
                true
            );
        }

        // Create Teams channel if enabled
        if ($config['teams']['enabled'] ?? false && !empty($config['teams']['webhook_url'] ?? '')) {
            $channels[] = new WebhookNotificationChannel(
                $this->httpClient,
                $config['teams']['webhook_url'],
                'teams',
                [],
                true
            );
        }

        // Create generic Webhook channel if enabled
        if ($config['webhook']['enabled'] ?? false && !empty($config['webhook']['url'] ?? '')) {
            $channels[] = new WebhookNotificationChannel(
                $this->httpClient,
                $config['webhook']['url'],
                $config['webhook']['format'] ?? 'json',
                $config['webhook']['headers'] ?? [],
                true
            );
        }

        return $channels;
    }

    /**
     * Returns whether notifications are enabled according to the database configuration.
     */
    public function areNotificationsEnabled(): bool
    {
        $config = $this->getNotificationConfigFromDatabase();

        return $config['enabled'] ?? false;
    }
}
