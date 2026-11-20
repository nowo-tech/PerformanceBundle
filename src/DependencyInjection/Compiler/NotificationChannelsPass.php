<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\DependencyInjection\Compiler;

use Nowo\PerformanceBundle\Notification\NotificationChannelInterface;
use Nowo\PerformanceBundle\Notification\RouteMetricsNotificationChannelInterface;
use Nowo\PerformanceBundle\Service\NotificationService;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function trigger_deprecation;

/**
 * Ensures NotificationService receives a Symfony 8.1-compatible tagged iterator argument.
 *
 * When services.yaml used {@code !tagged_iterator}, Symfony 8.1 loaders could build
 * {@see TaggedIteratorArgument} with a deprecated constructor signature.
 * This pass replaces that argument with a version-safe {@see TaggedIteratorArgument}.
 */
final class NotificationChannelsPass implements CompilerPassInterface
{
    private const CHANNEL_TAG = 'nowo_performance.notification_channel';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(NotificationService::class)) {
            return;
        }

        $container->getDefinition(NotificationService::class)
            ->setArgument('$channels', $this->createTaggedIteratorArgument(self::CHANNEL_TAG));

        $this->noticeLegacyChannels($container);
    }

    /**
     * Triggers one deprecation per host channel that implements only the deprecated NotificationChannelInterface
     * (3.19.0). Runs once per container build; the bundle's own channels are exempt.
     */
    private function noticeLegacyChannels(ContainerBuilder $container): void
    {
        foreach (array_keys($container->findTaggedServiceIds(self::CHANNEL_TAG)) as $id) {
            $class = $container->getDefinition($id)->getClass() ?? $id;
            $class = $container->getParameterBag()->resolveValue($class);

            if (!\is_string($class) || !class_exists($class)) {
                continue;
            }

            if (str_starts_with($class, 'Nowo\\PerformanceBundle\\Notification\\Channel\\')
                || !is_subclass_of($class, NotificationChannelInterface::class)
                || is_subclass_of($class, RouteMetricsNotificationChannelInterface::class)) {
                continue;
            }

            trigger_deprecation('nowo-tech/performance-bundle', '3.19.0', 'The notification channel "%s" (service "%s") implements only NotificationChannelInterface, whose send(PerformanceAlert, RouteData|AfterMetricsRecordedEvent) form is removed in 4.0. Implement Nowo\\PerformanceBundle\\Notification\\RouteMetricsNotificationChannelInterface and its sendRouteMetrics(PerformanceAlert, RouteMetricsSnapshot) instead; see docs/UPGRADING.md.', $class, $id);
        }
    }

    private function createTaggedIteratorArgument(string $tag): TaggedIteratorArgument
    {
        // Named arguments only: the parameters before $needsIndexes changed in Symfony 8.1 ($defaultIndexMethod /
        // $defaultPriorityMethod removed), the names used here exist in 7.4 and 8.x alike.
        return new TaggedIteratorArgument($tag, needsIndexes: false, exclude: [], excludeSelf: true);
    }
}
