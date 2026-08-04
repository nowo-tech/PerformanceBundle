<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\DependencyInjection\Compiler;

use Nowo\PerformanceBundle\Service\NotificationService;
use ReflectionClass;
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
            ->setArgument('$channels', $this->createTaggedIteratorArgument(self::CHANNEL_TAG));
    }

    private function createTaggedIteratorArgument(string $tag): TaggedIteratorArgument
    {
        $thirdParameter = (new ReflectionMethod(TaggedIteratorArgument::class, '__construct'))
            ->getParameters()[2];

        if ($thirdParameter->getName() === 'needsIndexes') {
            // Symfony 8.1+: 3rd arg is $needsIndexes. Use reflection so PHPStan (older stubs) does not
            // type-check positional args against the pre-8.1 constructor signature.
            return (new ReflectionClass(TaggedIteratorArgument::class))->newInstanceArgs([$tag, null, false, [], true]);
        }

        return new TaggedIteratorArgument($tag, null, null, false, null, [], true); // @codeCoverageIgnore – only reached on Symfony <8.1 (3rd param was defaultIndexMethod)
    }
}
