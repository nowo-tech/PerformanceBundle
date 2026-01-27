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
 * Compiler Pass para registrar canales de notificación dinámicamente desde la base de datos.
 *
 * Este Compiler Pass se ejecuta durante la compilación del contenedor y registra
 * los canales de notificación basándose en la configuración almacenada en la base de datos.
 *
 * IMPORTANTE: Este enfoque requiere que la base de datos esté disponible durante
 * la compilación del contenedor. Si esto no es posible, usa el enfoque de Factory Service.
 *
 * @see https://symfony.com/doc/current/service_container/compiler_passes.html
 */
class NotificationCompilerPass implements CompilerPassInterface
{
    /**
     * Procesa el contenedor para registrar canales dinámicos.
     */
    public function process(ContainerBuilder $container): void
    {
        // Solo procesar si el bundle de notificaciones está habilitado
        if (!$container->hasParameter('nowo_performance.notifications.enabled')) {
            return;
        }

        // Obtener configuración desde la base de datos
        // NOTA: Esto requiere que la BD esté disponible durante la compilación
        // Si no es posible, considera usar un Factory Service en su lugar

        try {
            // Crear una instancia temporal del servicio de configuración
            // para obtener la configuración de la BD
            $configService = new DynamicNotificationConfiguration(
                $container->get('doctrine.orm.entity_manager'),
                $container->has('mailer.mailer') ? $container->get('mailer.mailer') : null,
                $container->has('http_client') ? $container->get('http_client') : null,
                $container->has('twig') ? $container->get('twig') : null
            );

            $config = $this->getNotificationConfig($configService);
            $channels = $configService->createChannelsFromDatabase();

            // Registrar cada canal como un servicio
            foreach ($channels as $index => $channel) {
                $channelName = $channel->getName();
                $serviceId = \sprintf('nowo_performance.notification.channel.dynamic.%s', $channelName);

                // Crear definición del servicio
                $definition = new Definition($channel::class);

                // Configurar argumentos según el tipo de canal
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

                // Hacer el servicio público y etiquetarlo
                $definition->setPublic(true);
                $definition->addTag('nowo_performance.notification_channel', ['alias' => $channelName]);

                // Registrar el servicio
                $container->setDefinition($serviceId, $definition);
            }

            // Actualizar el parámetro de habilitación si viene de la BD
            if (isset($config['enabled'])) {
                $container->setParameter('nowo_performance.notifications.enabled', $config['enabled']);
            }
        } catch (\Exception $e) {
            // Si hay un error, usar configuración por defecto del YAML
            // Esto permite que la aplicación funcione incluso si la BD no está disponible
            error_log(\sprintf(
                'Error loading notification config from database in CompilerPass: %s. Using YAML config instead.',
                $e->getMessage()
            ));
        }
    }

    /**
     * Obtiene la configuración de notificaciones.
     *
     * @return array<string, mixed>
     */
    private function getNotificationConfig(DynamicNotificationConfiguration $configService): array
    {
        // Usar reflexión para acceder al método privado
        $reflection = new \ReflectionClass($configService);
        $method = $reflection->getMethod('getNotificationConfigFromDatabase');
        $method->setAccessible(true);

        return $method->invoke($configService);
    }
}
