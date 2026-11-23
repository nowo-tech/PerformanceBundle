<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\DependencyInjection\Compiler;

use LogicException;
use Nowo\PerformanceBundle\DependencyInjection\Compiler\RemovedNotificationChannelPass;
use Nowo\PerformanceBundle\Notification\Channel\EmailNotificationChannel;
use Nowo\PerformanceBundle\Notification\Channel\WebhookNotificationChannel;
use Nowo\PerformanceBundle\Tests\Fixtures\Notification\NotAChannel;
use Nowo\PerformanceBundle\Tests\Fixtures\Notification\RecordingNotificationChannel;
use Nowo\PerformanceBundle\Tests\Fixtures\RemovedChannel\Attribute\LegacyAttributeTaggedChannel;
use Nowo\PerformanceBundle\Tests\Fixtures\RemovedChannel\Decorator\LegacyChannelDecorator;
use Nowo\PerformanceBundle\Tests\Fixtures\RemovedChannel\Override\LegacyOverriddenChannel;
use Nowo\PerformanceBundle\Tests\Fixtures\RemovedChannel\Tagged\LegacyTaggedChannel;
use Nowo\PerformanceBundle\Tests\Fixtures\RemovedChannel\Unrelated\UnrelatedBrokenService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

use function sprintf;

/**
 * W1-1: channels written for the NotificationChannelInterface removed in 4.0 fail the build; everything else compiles.
 */
final class RemovedNotificationChannelPassTest extends TestCase
{
    public function testLoadingErrorNamingTheRemovedInterfaceFailsTheBuild(): void
    {
        $container = new ContainerBuilder();
        $container->register('App\\Notification\\LegacyChannel')->addError(sprintf('Interface "%s" not found while loading "App\\Notification\\LegacyChannel".', RemovedNotificationChannelPass::REMOVED_CHANNEL_PORT));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The service "App\\Notification\\LegacyChannel" cannot be loaded: Interface "Nowo\\PerformanceBundle\\Notification\\NotificationChannelInterface" not found while loading "App\\Notification\\LegacyChannel". ' . RemovedNotificationChannelPass::HINT);

        (new RemovedNotificationChannelPass())->process($container);
    }

    public function testUnrelatedLoadingErrorsKeepTheirLazyError(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.other')->addError(sprintf('Interface "%sPolicy" not found while loading "App\\Other".', RemovedNotificationChannelPass::REMOVED_CHANNEL_PORT));
        $container->register('app.broken')->addError('Class "App\\Missing" not found while loading "App\\Broken".');

        (new RemovedNotificationChannelPass())->process($container);

        self::assertCount(1, $container->getDefinition('app.other')->getErrors());
        self::assertCount(1, $container->getDefinition('app.broken')->getErrors());
    }

    public function testTaggedClassStillImplementingTheRemovedInterfaceFailsTheBuild(): void
    {
        $container = $this->containerWith(['app.legacy' => LegacyTaggedChannel::class]);

        try {
            (new RemovedNotificationChannelPass())->process($container);
            self::fail('The build did not fail.');
        } catch (LogicException $e) {
            self::assertStringStartsWith(sprintf('The notification channel "app.legacy" (class "%s") cannot be loaded: ', LegacyTaggedChannel::class), $e->getMessage());
            self::assertStringContainsString('"' . RemovedNotificationChannelPass::REMOVED_CHANNEL_PORT . '"', $e->getMessage());
            self::assertStringEndsWith(RemovedNotificationChannelPass::HINT, $e->getMessage());
            self::assertNotNull($e->getPrevious());
        }
    }

    public function testTaggedClassWithoutTheChannelPortFailsTheBuild(): void
    {
        $container = $this->containerWith(['app.not_a_channel' => NotAChannel::class]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf('The service "app.not_a_channel" (class "%s") is tagged "nowo_performance.notification_channel" but does not implement "Nowo\\PerformanceBundle\\Notification\\RouteMetricsNotificationChannelInterface", the only notification channel port since 4.0.', NotAChannel::class));

        (new RemovedNotificationChannelPass())->process($container);
    }

    public function testChannelsOnTheNewPortCompile(): void
    {
        $container = $this->containerWith([
            'app.recording' => RecordingNotificationChannel::class,
            'bundle.email'  => EmailNotificationChannel::class,
            'bundle.slack'  => WebhookNotificationChannel::class,
        ]);
        // A tagged service without a class falls back to its id.
        $container->setDefinition(RecordingNotificationChannel::class, new Definition())->addTag(RemovedNotificationChannelPass::CHANNEL_TAG);

        (new RemovedNotificationChannelPass())->process($container);

        self::assertCount(4, $container->findTaggedServiceIds(RemovedNotificationChannelPass::CHANNEL_TAG));
    }

    public function testUnresolvableOrMissingClassesAreLeftToSymfony(): void
    {
        $container = $this->containerWith([
            'app.missing'        => 'App\\Missing\\Channel',
            'app.parameter'      => '%app.channel_class%',
            'app.no_parameter'   => '%app.undefined_parameter%',
            'app.empty'          => '%app.empty_class%',
            'app.unrelated_load' => UnrelatedBrokenService::class,
        ]);
        $container->setParameter('app.channel_class', ['not', 'a', 'class']);
        $container->setParameter('app.empty_class', '');

        (new RemovedNotificationChannelPass())->process($container);

        self::assertCount(5, $container->findTaggedServiceIds(RemovedNotificationChannelPass::CHANNEL_TAG));
    }

    public function testAutoconfiguredServiceStillImplementingTheRemovedInterfaceFailsTheBuild(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.attribute_channel', new Definition(LegacyAttributeTaggedChannel::class))->setAutoconfigured(true);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf('The service "app.attribute_channel" (class "%s") cannot be loaded: ', LegacyAttributeTaggedChannel::class));

        (new RemovedNotificationChannelPass())->process($container);
    }

    public function testDecoratorOfAChannelStillImplementingTheRemovedInterfaceFailsTheBuild(): void
    {
        $container = $this->containerWith(['bundle.slack' => WebhookNotificationChannel::class]);
        $container->setDefinition('app.decorator', new Definition(LegacyChannelDecorator::class))->setDecoratedService('bundle.slack');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The service "app.decorator" (decorator of the notification channel "bundle.slack")');

        (new RemovedNotificationChannelPass())->process($container);
    }

    public function testOtherServicesAreNotLoaded(): void
    {
        $container = new ContainerBuilder();
        // Not autoconfigured, not a channel decorator: never loaded (it would fail).
        $container->setDefinition('app.plain', new Definition(LegacyOverriddenChannel::class));
        $container->setDefinition('app.decorates_other', new Definition(LegacyChannelDecorator::class))->setDecoratedService('app.other');
        $container->setDefinition('app.synthetic', (new Definition(LegacyOverriddenChannel::class))->setSynthetic(true)->setAutoconfigured(true));
        $container->setDefinition('app.no_class', (new Definition())->setAutoconfigured(true));
        // Autoconfigured with an unrelated loading problem, or a class that does not exist: left to Symfony.
        $container->setDefinition('app.unrelated', new Definition(UnrelatedBrokenService::class))->setAutoconfigured(true);
        $container->setDefinition('app.missing', new Definition('App\\Missing\\Service'))->setAutoconfigured(true);
        $container->setDefinition('app.ok', new Definition(RecordingNotificationChannel::class))->setAutoconfigured(true);

        (new RemovedNotificationChannelPass())->process($container);

        self::assertTrue($container->hasDefinition('app.plain'));
    }

    /**
     * @param array<string, string> $channels service id => class
     */
    private function containerWith(array $channels): ContainerBuilder
    {
        $container = new ContainerBuilder();
        foreach ($channels as $id => $class) {
            $container->setDefinition($id, (new Definition($class))->addTag(RemovedNotificationChannelPass::CHANNEL_TAG));
        }

        return $container;
    }
}
