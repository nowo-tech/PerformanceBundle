<?php

declare(strict_types=1);

namespace App\Service;

use Nowo\PerformanceBundle\Notification\NotificationChannelInterface;
use Nowo\PerformanceBundle\Service\NotificationService;

/**
 * Factory service to create NotificationService with dynamic channels.
 *
 * This approach is more flexible than the Compiler Pass because:
 * - It does not require the database to be available during compilation
 * - It allows changing configuration without recompiling the container
 * - Useful when credentials change frequently
 *
 * Usage:
 * ```php
 * $notificationService = $notificationFactory->createNotificationService();
 * ```
 */
class NotificationFactoryService
{
    /**
     * Creates a new instance.
     *
     * @param DynamicNotificationConfiguration $configService Service to obtain configuration from the database
     */
    public function __construct(
        private readonly DynamicNotificationConfiguration $configService,
    ) {
    }

    /**
     * Creates a NotificationService with channels configured from the database.
     *
     * This method creates a new NotificationService instance each time it is called,
     * allowing fully dynamic configuration.
     *
     * @return NotificationService
     */
    public function createNotificationService(): NotificationService
    {
        $channels = $this->configService->createChannelsFromDatabase();
        $enabled = $this->configService->areNotificationsEnabled();

        return new NotificationService($channels, $enabled);
    }

    /**
     * Creates a NotificationService with additional custom channels.
     *
     * Useful to add channels that are not in the database configuration.
     *
     * @param array<NotificationChannelInterface> $additionalChannels Additional channels
     *
     * @return NotificationService
     */
    public function createNotificationServiceWithAdditionalChannels(array $additionalChannels): NotificationService
    {
        $channels = $this->configService->createChannelsFromDatabase();
        $allChannels = array_merge($channels, $additionalChannels);
        $enabled = $this->configService->areNotificationsEnabled();

        return new NotificationService($allChannels, $enabled);
    }
}
