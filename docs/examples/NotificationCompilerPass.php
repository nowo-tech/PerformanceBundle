<?php

declare(strict_types=1);

namespace App\DependencyInjection\Compiler;

use App\Service\DynamicNotificationConfiguration;
use Nowo\PerformanceBundle\Notification\Channel\EmailNotificationChannel;
use Nowo\PerformanceBundle\Notification\Channel\WebhookNotificationChannel;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Compiler pass to register notification channels dynamically from the database.
 *
 * This compiler pass runs during container compilation and registers notification
 * channels based on configuration stored in the database.
 *
 * IMPORTANT: This approach requires the database to be available during container
 * compilation. If that is not possible, use the Factory Service approach instead.
 *
 * @see https://symfony.com/doc/current/service_container/compiler_passes.html
 */
class NotificationCompilerPass implements CompilerPassInterface
{
    /**
     * Processes the container to register dynamic notification channels.
     *
     * @param ContainerBuilder $container The container builder
     */
    public function process(ContainerBuilder $container): void
    {
        // Only process if the notifications bundle is enabled
        if (!$container->hasParameter('nowo_performance.notifications.enabled')) {
            return;
        }

        // Get configuration from the database
        // NOTE: This requires the DB to be available during compilation.
        // If not possible, consider using a Factory Service instead.

        try {
            // Create a temporary instance of the config service to fetch DB config
            $configService = new DynamicNotificationConfiguration(
                $container->get('doctrine.orm.entity_manager'),
                $container->has('mailer.mailer') ? $container->get('mailer.mailer') : null,
                $container->has('http_client') ? $container->get('http_client') : null,
                $container->has('twig') ? $container->get('twig') : null
            );

            $config = $this->getNotificationConfig($configService);
            $channels = $configService->createChannelsFromDatabase();

            // Register each channel as a service
            foreach ($channels as $index => $channel) {
                $channelName = $channel->getName();
                $serviceId = \sprintf('nowo_performance.notification.channel.dynamic.%s', $channelName);

                // Create service definition
                $definition = new Definition($channel::class);

                // Set arguments according to channel type
                if ($channel instanceof EmailNotificationChannel) {
                    $definition->setArguments([
                        new Reference('?mailer.mailer'),
                        new Reference('?twig'),
                        $config['email']['from'] ?? 'noreply@example.com',
                        $config['email']['to'] ?? [],
                        true,
                    ]);
                } elseif ($channel instanceof WebhookNotificationChannel) {
                    $webhookConfig = match ($channelName) {
                        'slack' => $config['slack'] ?? [],
                        'teams' => $config['teams'] ?? [],
                        'webhook' => $config['webhook'] ?? [],
                        default => [],
                    };

                    $definition->setArguments([
                        new Reference('?http_client'),
                        $webhookConfig['webhook_url'] ?? $webhookConfig['url'] ?? '',
                        $webhookConfig['format'] ?? ('slack' === $channelName ? 'slack' : ('teams' === $channelName ? 'teams' : 'json')),
                        $webhookConfig['headers'] ?? [],
                        true,
                    ]);
                }

                // Make the service public and tag it
                $definition->setPublic(true);
                $definition->addTag('nowo_performance.notification_channel', ['alias' => $channelName]);

                // Register the service
                $container->setDefinition($serviceId, $definition);
            }

            // Update the enabled parameter if it comes from the DB
            if (isset($config['enabled'])) {
                $container->setParameter('nowo_performance.notifications.enabled', $config['enabled']);
            }
        } catch (\Exception $e) {
            // On error, fall back to default YAML config so the app works even if DB is unavailable
            error_log(\sprintf(
                'Error loading notification config from database in CompilerPass: %s. Using YAML config instead.',
                $e->getMessage()
            ));
        }
    }

    /**
     * Returns the notification configuration.
     *
     * @param DynamicNotificationConfiguration $configService The dynamic config service
     *
     * @return array<string, mixed>
     */
    private function getNotificationConfig(DynamicNotificationConfiguration $configService): array
    {
        // Use reflection to access the private method
        $reflection = new \ReflectionClass($configService);
        $method = $reflection->getMethod('getNotificationConfigFromDatabase');
        $method->setAccessible(true);

        return $method->invoke($configService);
    }
}
