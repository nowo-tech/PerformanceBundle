<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Integration\Notification;

use Nowo\PerformanceBundle\Tests\Fixtures\Notification\NotAChannel;
use Nowo\PerformanceBundle\Tests\Fixtures\Notification\RecordingNotificationChannel;
use Nowo\PerformanceBundle\Tests\Fixtures\RemovedChannel\Tagged\LegacyTaggedChannel;
use Nowo\PerformanceBundle\Tests\Integration\TestKernel;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * TestKernel with notifications enabled and one host notification channel set-up per scenario (4.0 channel port,
 * REQ-ARCH-001 rules 1 and 3), built through the real NowoPerformanceBundle::build():
 *
 * - `recording`: a channel implementing only RouteMetricsNotificationChannelInterface, tagged (public for the test);
 * - `autodiscovered`: resource autodiscovery of a class still implementing the removed NotificationChannelInterface;
 * - `tagged`: the same kind of class registered explicitly and tagged nowo_performance.notification_channel;
 * - `not_a_channel`: a tagged class implementing no channel port;
 * - `unrelated`: resource autodiscovery of a class implementing an unrelated missing interface (must still compile).
 */
final class NotificationChannelKernel extends TestKernel
{
    public const TAG = 'nowo_performance.notification_channel';

    public function __construct(private readonly string $scenario)
    {
        parent::__construct('test', true);
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir() . '/var/cache/notification-' . $this->scenario;
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir() . '/var/log';
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        parent::registerContainerConfiguration($loader);

        $scenario = $this->scenario;
        $fixtures = __DIR__ . '/../../Fixtures';
        $loader->load(static function (ContainerBuilder $container) use ($scenario, $fixtures): void {
            $container->loadFromExtension('nowo_performance', [
                'notifications' => ['enabled' => true],
            ]);

            $prototype = (new Definition())->setAutowired(true)->setAutoconfigured(true);
            match ($scenario) {
                'recording' => $container->setDefinition(RecordingNotificationChannel::class, new Definition(RecordingNotificationChannel::class))
                    ->setPublic(true)
                    ->addTag(self::TAG),
                'tagged' => $container->setDefinition(LegacyTaggedChannel::class, new Definition(LegacyTaggedChannel::class))
                    ->addTag(self::TAG),
                'not_a_channel' => $container->setDefinition(NotAChannel::class, new Definition(NotAChannel::class))
                    ->addTag(self::TAG),
                'autodiscovered' => self::discover($container, $prototype, 'Nowo\\PerformanceBundle\\Tests\\Fixtures\\RemovedChannel\\Autodiscovered\\', $fixtures . '/RemovedChannel/Autodiscovered/*'),
                'unrelated'      => self::discover($container, $prototype, 'Nowo\\PerformanceBundle\\Tests\\Fixtures\\RemovedChannel\\Unrelated\\', $fixtures . '/RemovedChannel/Unrelated/*'),
            };
        });
    }

    /**
     * Resource autodiscovery, as a host `services.yaml` `App\: resource: ../src/*` does.
     */
    private static function discover(ContainerBuilder $container, Definition $prototype, string $namespace, string $resource): Definition
    {
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__));
        $loader->registerClasses($prototype, $namespace, $resource);

        return $prototype;
    }
}
