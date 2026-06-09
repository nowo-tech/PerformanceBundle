<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\DependencyInjection\Compiler;

use Nowo\PerformanceBundle\Service\NotificationService;
use ReflectionMethod;
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
            ->setArgument('$channels', self::createTaggedIteratorArgument(self::CHANNEL_TAG));
    }

    private static function createTaggedIteratorArgument(string $tag): TaggedIteratorArgument
    {
        $thirdParameter = (new ReflectionMethod(TaggedIteratorArgument::class, '__construct'))
            ->getParameters()[2];

        if ('needsIndexes' === $thirdParameter->getName()) {
            // Symfony 8.1+: 3rd arg is $needsIndexes (bool|string|null), not defaultIndexMethod.
            return new TaggedIteratorArgument($tag, null, false, [], true);
        }

        return new TaggedIteratorArgument($tag, null, null, false, null, [], true);
    }
}
