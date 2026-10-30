<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Notification;

use Nowo\PerformanceBundle\Notification\Channel\WebhookNotificationChannel;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * REQ-TEST-003: SSRF guard of the webhook channel — https URLs without a host and local host names are refused.
 */
final class WebhookNotificationChannelUrlTest extends TestCase
{
    public function testUrlsWithoutHostOrWithLocalHostNamesAreUnsafe(): void
    {
        $channel = new WebhookNotificationChannel(null, '', 'json', [], false);
        $method  = new ReflectionMethod(WebhookNotificationChannel::class, 'isSafeWebhookUrl');

        self::assertFalse($method->invoke($channel, 'https:hooks'));
        self::assertFalse($method->invoke($channel, 'https://localhost/hooks'));
        self::assertFalse($method->invoke($channel, 'https://printer.local/hooks'));
        self::assertTrue($method->invoke($channel, 'https://hooks.example.com/x'));
    }
}
