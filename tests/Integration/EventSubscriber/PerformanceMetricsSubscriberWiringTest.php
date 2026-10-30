<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Integration\EventSubscriber;

use Nowo\PerformanceBundle\EventSubscriber\PerformanceMetricsSubscriber;
use Nowo\PerformanceBundle\Tests\Integration\TestKernel;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Real kernel wiring of the optional services of PerformanceMetricsSubscriber. Up to 3.14.0 they used
 * `#[Autowire(service: '?security.helper')]` / `'?kernel'`, which is not an optional reference (the id keeps the `?`)
 * and compiled to null: `track_user` never stored the user and the environment was never read from the kernel.
 */
final class PerformanceMetricsSubscriberWiringTest extends TestCase
{
    private TestKernel $kernel;

    protected function setUp(): void
    {
        $this->kernel = new TestKernel('test', true);
        $this->kernel->boot();
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
    }

    public function testSecurityHelperAndKernelAreInjected(): void
    {
        $subscriber = $this->subscriber();

        self::assertInstanceOf(Security::class, $this->property($subscriber, 'security'));
        self::assertSame($this->kernel, $this->property($subscriber, 'kernel'));
    }

    public function testDebugStopwatchIsNotInjected(): void
    {
        // The query-time fallback resets the stopwatch it receives; the shared debug stopwatch (profiler timeline)
        // must never be handed to it.
        self::assertNull($this->property($this->subscriber(), 'stopwatch'));
    }

    private function subscriber(): PerformanceMetricsSubscriber
    {
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        $subscriber = $container->get(PerformanceMetricsSubscriber::class);
        self::assertInstanceOf(PerformanceMetricsSubscriber::class, $subscriber);

        return $subscriber;
    }

    private function property(object $object, string $name): mixed
    {
        return (new ReflectionProperty($object, $name))->getValue($object);
    }
}
