<?php

declare(strict_types=1);

namespace App\Service;

use Nowo\PerformanceBundle\Notification\NotificationChannelInterface;
use Nowo\PerformanceBundle\Service\NotificationService;

/**
 * Factory Service para crear NotificationService con canales dinámicos.
 *
 * Este enfoque es más flexible que el Compiler Pass porque:
 * - No requiere que la BD esté disponible durante la compilación
 * - Permite cambiar la configuración sin recompilar el contenedor
 * - Útil cuando las credenciales cambian frecuentemente
 *
 * Uso:
 * ```php
 * $notificationService = $notificationFactory->createNotificationService();
 * ```
 */
class NotificationFactoryService
{
    /**
     * Constructor.
     *
     * @param DynamicNotificationConfiguration $configService Servicio para obtener configuración de BD
     */
    public function __construct(
        private readonly DynamicNotificationConfiguration $configService,
    ) {
    }

    /**
     * Crea un NotificationService con canales configurados desde la base de datos.
     *
     * Este método crea una nueva instancia de NotificationService cada vez que se llama,
     * lo que permite tener configuración completamente dinámica.
     */
    public function createNotificationService(): NotificationService
    {
        $channels = $this->configService->createChannelsFromDatabase();
        $enabled = $this->configService->areNotificationsEnabled();

        return new NotificationService($channels, $enabled);
    }

    /**
     * Crea canales adicionales personalizados.
     *
     * Útil para añadir canales que no están en la configuración de BD.
     *
     * @param array<NotificationChannelInterface> $additionalChannels Canales adicionales
     */
    public function createNotificationServiceWithAdditionalChannels(array $additionalChannels): NotificationService
    {
        $channels = $this->configService->createChannelsFromDatabase();
        $allChannels = array_merge($channels, $additionalChannels);
        $enabled = $this->configService->areNotificationsEnabled();

        return new NotificationService($allChannels, $enabled);
    }
}
