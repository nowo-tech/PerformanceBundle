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
        (new ReflectionMethod(TaggedIteratorArgument::class, '__construct'))
            ->getParameters();
        $this->assertNull($argument->getDefaultIndexMethod());
        $this->assertNull($argument->getDefaultPriorityMethod());
    }

    public function testProcessDoesNothingWhenNotificationServiceIsMissing(): void
    {
        $container = new ContainerBuilder();

        (new NotificationChannelsPass())->process($container);

        $this->assertFalse($container->hasDefinition(NotificationService::class));
    }

    public function testLegacyHostChannelTriggersOneDeprecation(): void
    {
        $container = $this->containerWith([
            'app.legacy' => LegacyHostChannel::class,
            'app.new'    => SnapshotHostChannel::class,
        ]);

        $messages = [];
        set_error_handler(static function (int $no, string $msg) use (&$messages): bool {
            $messages[] = $msg;

            return true;
        }, \E_USER_DEPRECATED);

        try {
            (new NotificationChannelsPass())->process($container);
        } finally {
            restore_error_handler();
        }

        $this->assertCount(1, $messages);
        $this->assertMatchesRegularExpression('/LegacyHostChannel.*app\.legacy.*RouteMetricsNotificationChannelInterface/', $messages[0]);
    }

    public function testSnapshotAndBundleChannelsTriggerNothing(): void
    {
        $container = $this->containerWith([
            'app.new' => SnapshotHostChannel::class,
            'bundle'  => \Nowo\PerformanceBundle\Notification\Channel\EmailNotificationChannel::class,
        ]);

        $messages = [];
        set_error_handler(static function (int $no, string $msg) use (&$messages): bool {
            $messages[] = $msg;

            return true;
        }, \E_USER_DEPRECATED);

        try {
            (new NotificationChannelsPass())->process($container);
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $messages);
    }

    /**
     * @param array<string, class-string> $channels
     */
    private function containerWith(array $channels): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition(NotificationService::class, new Definition(NotificationService::class));
        foreach ($channels as $id => $class) {
            $container->setDefinition($id, (new Definition($class))->addTag('nowo_performance.notification_channel'));
        }

        return $container;
    }
}

final class LegacyHostChannel implements \Nowo\PerformanceBundle\Notification\NotificationChannelInterface
{
    public function send(\Nowo\PerformanceBundle\Notification\PerformanceAlert $alert, \Nowo\PerformanceBundle\Entity\RouteData|\Nowo\PerformanceBundle\Event\AfterMetricsRecordedEvent $context): bool
    {
        return true;
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function getName(): string
    {
        return 'legacy';
    }
}

final class SnapshotHostChannel implements \Nowo\PerformanceBundle\Notification\RouteMetricsNotificationChannelInterface
{
    public function send(\Nowo\PerformanceBundle\Notification\PerformanceAlert $alert, \Nowo\PerformanceBundle\Entity\RouteData|\Nowo\PerformanceBundle\Event\AfterMetricsRecordedEvent $context): bool
    {
        return true;
    }

    public function sendRouteMetrics(\Nowo\PerformanceBundle\Notification\PerformanceAlert $alert, \Nowo\PerformanceBundle\Model\RouteMetricsSnapshot $route): bool
    {
        return true;
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function getName(): string
    {
        return 'snapshot';
    }
}
