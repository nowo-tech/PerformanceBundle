<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\DependencyInjection\Compiler;

use LogicException;
use Nowo\PerformanceBundle\Notification\RouteMetricsNotificationChannelInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Throwable;

use function class_exists;
use function interface_exists;
use function is_string;
use function sprintf;
use function str_contains;

/**
 * Refuses, when the container is built, every notification channel still written for the channel port removed in 4.0
 * (`Notification\NotificationChannelInterface`, `send(PerformanceAlert, RouteData|AfterMetricsRecordedEvent)`;
 * deprecated since 3.19.0, REQ-ARCH-001 rules 1 and 3). Without it such a channel would vanish without a word and its
 * alerts would simply stop:
 *
 * - a service definition whose loading errors name the removed interface. A class registered by resource
 *   autodiscovery that still implements it cannot be loaded, so Symfony keeps the definition with only an error
 *   (`… "Nowo\PerformanceBundle\Notification\NotificationChannelInterface" not found while loading "App\…"`) and never
 *   reads its attributes; unused, the definition would later be removed silently. Only the exact class name, quoted as
 *   Symfony quotes it, matches: an unrelated missing class keeps its lazy error;
 * - a service tagged `nowo_performance.notification_channel` whose class cannot be loaded because it still implements
 *   the removed interface, or that does not implement {@see RouteMetricsNotificationChannelInterface} (it could never be
 *   called: the notification service only calls sendRouteMetrics()).
 *
 * Registered twice before optimization: at priority 1000 (loading errors, before any other pass looks at the
 * definitions) and at priority 0 (after the `_instanceof` / autoconfiguration tags are applied). Each message names the
 * service, the removed interface, the version that deprecated it and the replacement.
 *
 * @internal registered by the bundle
 */
final class RemovedNotificationChannelPass implements CompilerPassInterface
{
    /** Class name of the channel port deprecated in 3.19.0 and removed in 4.0. */
    public const REMOVED_CHANNEL_PORT = 'Nowo\\PerformanceBundle\\Notification\\NotificationChannelInterface';

    /** Tag of the notification channels. */
    public const CHANNEL_TAG = 'nowo_performance.notification_channel';

    /** Second sentence of every build error. */
    public const HINT = '"Nowo\PerformanceBundle\Notification\NotificationChannelInterface" (deprecated since 3.19.0) was removed in 4.0, so a class that still implements it no longer loads: implement "Nowo\PerformanceBundle\Notification\RouteMetricsNotificationChannelInterface" instead, move the body of send() to sendRouteMetrics(PerformanceAlert, RouteMetricsSnapshot) and delete send() (see docs/UPGRADING.md "To 4.0.0").';

    /**
     * @throws LogicException on the first channel written for the removed port
     */
    public function process(ContainerBuilder $container): void
    {
        $quoted = '"' . self::REMOVED_CHANNEL_PORT . '"';
        foreach ($container->getDefinitions() as $id => $definition) {
            foreach ($definition->getErrors() as $error) {
                if (str_contains((string) $error, $quoted)) {
                    throw new LogicException(sprintf('The service "%s" cannot be loaded: %s %s', $id, (string) $error, self::HINT));
                }
            }
        }

        foreach (array_keys($container->findTaggedServiceIds(self::CHANNEL_TAG)) as $id) {
            $this->assertChannel($container, (string) $id, $container->getDefinition($id)->getClass() ?? (string) $id);
        }
    }

    /**
     * Loads the class of a tagged channel: a class still implementing the removed interface throws "… not found"; a
     * loadable class must implement the channel port. A class that does not exist is left to Symfony's own checks.
     */
    private function assertChannel(ContainerBuilder $container, string $id, string $class): void
    {
        try {
            $class = $container->getParameterBag()->resolveValue($class);
        } catch (ParameterNotFoundException) {
            return; // reported by the pass that resolves parameters
        }

        if (!is_string($class) || $class === '') {
            return;
        }

        try {
            $exists = class_exists($class) || interface_exists($class);
        } catch (Throwable $e) {
            if (str_contains($e->getMessage(), '"' . self::REMOVED_CHANNEL_PORT . '"')) {
                throw new LogicException(sprintf('The notification channel "%s" (class "%s") cannot be loaded: %s %s', $id, $class, $e->getMessage(), self::HINT), 0, $e);
            }

            return; // an unrelated loading problem is reported by Symfony
        }

        if ($exists && !is_subclass_of($class, RouteMetricsNotificationChannelInterface::class)) {
            throw new LogicException(sprintf('The service "%s" (class "%s") is tagged "%s" but does not implement "%s", the only notification channel port since 4.0. %s', $id, $class, self::CHANNEL_TAG, RouteMetricsNotificationChannelInterface::class, self::HINT));
        }
    }
}
