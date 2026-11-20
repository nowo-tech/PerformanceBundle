<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\PerformanceBundle\DependencyInjection\Compiler\NotificationChannelsPass;
use Nowo\PerformanceBundle\Entity\RouteData;
use Nowo\PerformanceBundle\Event\AfterMetricsRecordedEvent;
use Nowo\PerformanceBundle\Model\RouteMetricsSnapshot;
use Nowo\PerformanceBundle\Notification\Channel\EmailNotificationChannel;
use Nowo\PerformanceBundle\Notification\NotificationChannelInterface;
use Nowo\PerformanceBundle\Notification\PerformanceAlert;
use Nowo\PerformanceBundle\Notification\RouteMetricsNotificationChannelInterface;
use Nowo\PerformanceBundle\Service\NotificationService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

use const E_USER_DEPRECATED;

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
        }, E_USER_DEPRECATED);

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
            'bundle'  => EmailNotificationChannel::class,
        ]);

        $messages = [];
        set_error_handler(static function (int $no, string $msg) use (&$messages): bool {
            $messages[] = $msg;

            return true;
        }, E_USER_DEPRECATED);

        try {
            (new NotificationChannelsPass())->process($container);
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $messages);
    }

    public function testChannelsWithoutAResolvableClassAreSkipped(): void
    {
        $container = $this->containerWith([]);
        $container->setParameter('app.channel_class', ['not', 'a', 'class']);
        $container->setDefinition('app.missing', (new Definition('App\\Missing\\Channel'))->addTag('nowo_performance.notification_channel'));
        $container->setDefinition('app.parameter', (new Definition('%app.channel_class%'))->addTag('nowo_performance.notification_channel'));

        $messages = [];
        set_error_handler(static function (int $no, string $msg) use (&$messages): bool {
            $messages[] = $msg;

            return true;
        }, E_USER_DEPRECATED);

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

final class LegacyHostChannel implements NotificationChannelInterface
{
    public function send(PerformanceAlert $alert, RouteData|AfterMetricsRecordedEvent $context): bool
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

final class SnapshotHostChannel implements RouteMetricsNotificationChannelInterface
{
    public function send(PerformanceAlert $alert, RouteData|AfterMetricsRecordedEvent $context): bool
    {
        return true;
    }

    public function sendRouteMetrics(PerformanceAlert $alert, RouteMetricsSnapshot $route): bool
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
