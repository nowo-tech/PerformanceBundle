<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\DependencyInjection\Compiler;

use Nowo\PerformanceBundle\Service\NotificationService;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

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
    }

    private function createTaggedIteratorArgument(string $tag): TaggedIteratorArgument
    {
        // Named arguments only: the parameters before $needsIndexes changed in Symfony 8.1 ($defaultIndexMethod /
        // $defaultPriorityMethod removed), the names used here exist in 7.4 and 8.x alike.
        return new TaggedIteratorArgument($tag, needsIndexes: false, exclude: [], excludeSelf: true);
    }
}
