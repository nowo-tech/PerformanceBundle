<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\PerformanceBundle\DependencyInjection\Compiler\NotificationChannelsPass;
use Nowo\PerformanceBundle\Service\NotificationService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class NotificationChannelsPassTest extends TestCase
{
    public function testProcessSetsSymfony81CompatibleTaggedIteratorArgument(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(NotificationService::class, new Definition(NotificationService::class));

        (new NotificationChannelsPass())->process($container);

        $argument = $container->getDefinition(NotificationService::class)->getArgument('$channels');
        $this->assertInstanceOf(TaggedIteratorArgument::class, $argument);
        $this->assertSame('nowo_performance.notification_channel', $argument->getTag());
        $this->assertNull($argument->getIndexAttribute());
        $this->assertFalse($argument->needsIndexes());
        $this->assertSame([], $argument->getExclude());
        $this->assertTrue($argument->excludeSelf());

        $thirdParameter = (new ReflectionMethod(TaggedIteratorArgument::class, '__construct'))
            ->getParameters()[2];
        if ('needsIndexes' === $thirdParameter->getName()) {
            $this->assertNull($argument->getDefaultIndexMethod(false));
            $this->assertNull($argument->getDefaultPriorityMethod(false));
        } else {
            $this->assertNull($argument->getDefaultIndexMethod());
            $this->assertNull($argument->getDefaultPriorityMethod());
        }
    }

    public function testProcessDoesNothingWhenNotificationServiceIsMissing(): void
    {
        $container = new ContainerBuilder();

        (new NotificationChannelsPass())->process($container);

        $this->assertFalse($container->hasDefinition(NotificationService::class));
    }
}
