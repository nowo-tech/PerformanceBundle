<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\DependencyInjection\Compiler;

use LogicException;
use Nowo\PerformanceBundle\Notification\RouteMetricsNotificationChannelInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Throwable;

use function array_keys;
use function array_map;
use function class_exists;
use function in_array;
use function interface_exists;
use function is_string;
use function is_subclass_of;
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
 *   called: the notification service only calls sendRouteMetrics());
 * - an autoconfigured service whose class cannot be loaded because it still implements the removed interface. Such a
 *   class is never autoconfigured, so `#[AutoconfigureTag('nowo_performance.notification_channel')]` or an
 *   `_instanceof` rule never tags it; this also covers an explicit `services.yaml` override (to pass arguments) of a
 *   class first registered by `App\: resource: ../src/`, whose loading error the override discards;
 * - a decorator of a notification channel whose class still implements the removed interface (Symfony would only
 *   report a generic reflection error).
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

        $channels = array_map(strval(...), array_keys($container->findTaggedServiceIds(self::CHANNEL_TAG)));
        foreach ($channels as $id) {
            $class = $container->getDefinition($id)->getClass() ?? $id;
            if ($this->load($container, $class, sprintf('The notification channel "%s"', $id)) !== true) {
                continue;
            }
            $class = (string) $container->getParameterBag()->resolveValue($class);
            if (!is_subclass_of($class, RouteMetricsNotificationChannelInterface::class)) {
                throw new LogicException(sprintf('The service "%s" (class "%s") is tagged "%s" but does not implement "%s", the only notification channel port since 4.0. %s', $id, $class, self::CHANNEL_TAG, RouteMetricsNotificationChannelInterface::class, self::HINT));
            }
        }

        // A class that cannot be loaded is never autoconfigured, so #[AutoconfigureTag] / _instanceof never tag it: an
        // autoconfigured service (also an explicit override of an autodiscovered class) or a decorator of a channel is
        // loaded here, so a channel still written for the removed port fails instead of vanishing.
        foreach ($container->getDefinitions() as $id => $definition) {
            $id = (string) $id;
            if (in_array($id, $channels, true) || $definition->isSynthetic() || !is_string($definition->getClass())) {
                continue;
            }
            $decorated = $definition->getDecoratedService()[0] ?? null;
            if ($definition->isAutoconfigured()) {
                $this->load($container, $definition->getClass(), sprintf('The service "%s"', $id));
            } elseif (is_string($decorated) && in_array($decorated, $channels, true)) {
                $this->load($container, $definition->getClass(), sprintf('The service "%s" (decorator of the notification channel "%s")', $id, $decorated));
            }
        }
    }

    /**
     * Loads $class: a class still implementing the removed interface throws "… not found", which becomes the build
     * error. Returns whether the class exists, or null when it cannot be resolved or fails to load for another reason
     * (left to Symfony's own checks).
     *
     * @throws LogicException when loading fails on the removed interface
     */
    private function load(ContainerBuilder $container, string $class, string $subject): ?bool
    {
        try {
            $class = $container->getParameterBag()->resolveValue($class);
        } catch (ParameterNotFoundException) {
            return null; // reported by the pass that resolves parameters
        }

        if (!is_string($class) || $class === '') {
            return null;
        }

        try {
            return class_exists($class) || interface_exists($class);
        } catch (Throwable $e) {
            if (str_contains($e->getMessage(), '"' . self::REMOVED_CHANNEL_PORT . '"')) {
                throw new LogicException(sprintf('%s (class "%s") cannot be loaded: %s %s', $subject, $class, $e->getMessage(), self::HINT), 0, $e);
            }

            return null; // an unrelated loading problem is reported by Symfony
        }
    }
}
