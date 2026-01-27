<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\PerformanceBundle\Notification\Channel\EmailNotificationChannel;
use Nowo\PerformanceBundle\Notification\Channel\WebhookNotificationChannel;
use Nowo\PerformanceBundle\Notification\NotificationChannelInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Ejemplo de servicio para configurar notificaciones dinámicamente desde la base de datos.
 *
 * Este servicio demuestra cómo configurar los canales de notificación cuando las credenciales
 * (webhook URLs, emails, etc.) se almacenan en la base de datos en lugar del archivo YAML.
 *
 * @example
 * Este servicio se puede usar de dos formas:
 *
 * 1. Como Compiler Pass (recomendado para configuración estática):
 *    - Crea un CompilerPass que registre los canales dinámicamente
 *    - Los canales se crean una vez al compilar el contenedor
 *
 * 2. Como Factory Service (para configuración completamente dinámica):
 *    - Crea los canales bajo demanda cuando se necesitan
 *    - Útil cuando las credenciales cambian frecuentemente
 *
 * @see https://symfony.com/doc/current/service_container/compiler_passes.html
 */
class DynamicNotificationConfiguration
{
    /**
     * Constructor.
     *
     * @param EntityManagerInterface   $entityManager Para acceder a la base de datos
     * @param MailerInterface|null     $mailer        Symfony Mailer (opcional)
     * @param HttpClientInterface|null $httpClient    Symfony HTTP Client (opcional)
     * @param Environment|null         $twig          Twig Environment para renderizar templates (opcional)
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ?MailerInterface $mailer = null,
        private readonly ?HttpClientInterface $httpClient = null,
        private readonly ?Environment $twig = null,
    ) {
    }

    /**
     * Obtiene la configuración de notificaciones desde la base de datos.
     *
     * Este método asume que tienes una entidad o tabla que almacena la configuración.
     * Ajusta según tu estructura de base de datos.
     *
     * @return array<string, mixed> Configuración de notificaciones
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
        // Ejemplo: Obtener configuración desde una entidad Settings
        // Ajusta según tu estructura de base de datos

        try {
            // Opción 1: Desde una entidad Settings
            $settings = $this->entityManager
                ->getRepository('App\Entity\NotificationSettings')
                ->findOneBy(['key' => 'performance_notifications']);

            if ($settings && $settings->getValue()) {
                return json_decode($settings->getValue(), true) ?? [];
            }

            // Opción 2: Desde una tabla de configuración simple
            // $connection = $this->entityManager->getConnection();
            // $stmt = $connection->prepare('SELECT * FROM notification_config WHERE type = :type');
            // $stmt->execute(['type' => 'performance']);
            // $config = $stmt->fetchAssociative();
            // return $config ? json_decode($config['value'], true) ?? [] : [];
        } catch (\Exception $e) {
            // Log error y retornar configuración por defecto
            error_log(\sprintf(
                'Error loading notification config from database: %s',
                $e->getMessage()
            ));
        }

        // Configuración por defecto si no hay datos en BD
        return [
            'enabled' => false,
            'email' => ['enabled' => false],
            'slack' => ['enabled' => false],
            'teams' => ['enabled' => false],
            'webhook' => ['enabled' => false],
        ];
    }

    /**
     * Crea los canales de notificación basados en la configuración de la base de datos.
     *
     * Este método crea instancias de los canales con las credenciales obtenidas de la BD.
     *
     * @return array<NotificationChannelInterface> Array de canales configurados
     */
    public function createChannelsFromDatabase(): array
    {
        $config = $this->getNotificationConfigFromDatabase();
        $channels = [];

        // Crear canal de Email si está habilitado
        if ($config['email']['enabled'] ?? false) {
            $channels[] = new EmailNotificationChannel(
                $this->mailer,
                $this->twig,
                $config['email']['from'] ?? 'noreply@example.com',
                $config['email']['to'] ?? [],
                true
            );
        }

        // Crear canal de Slack si está habilitado
        if ($config['slack']['enabled'] ?? false && !empty($config['slack']['webhook_url'] ?? '')) {
            $channels[] = new WebhookNotificationChannel(
                $this->httpClient,
                $config['slack']['webhook_url'],
                'slack',
                [],
                true
            );
        }

        // Crear canal de Teams si está habilitado
        if ($config['teams']['enabled'] ?? false && !empty($config['teams']['webhook_url'] ?? '')) {
            $channels[] = new WebhookNotificationChannel(
                $this->httpClient,
                $config['teams']['webhook_url'],
                'teams',
                [],
                true
            );
        }

        // Crear canal de Webhook genérico si está habilitado
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
     * Verifica si las notificaciones están habilitadas según la configuración de la BD.
     */
    public function areNotificationsEnabled(): bool
    {
        $config = $this->getNotificationConfigFromDatabase();

        return $config['enabled'] ?? false;
    }
}
