<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Notification;

use Nowo\PerformanceBundle\Event\AfterMetricsRecordedEvent;
use Nowo\PerformanceBundle\Event\AfterRecordReviewedEvent;
use Nowo\PerformanceBundle\Event\BeforeRecordDeletedEvent;
use Nowo\PerformanceBundle\Event\BeforeRecordReviewedEvent;
use Nowo\PerformanceBundle\Model\RouteDataWithAggregates;
use Nowo\PerformanceBundle\Model\RouteMetricsSnapshot;
use Nowo\PerformanceBundle\Notification\Channel\EmailNotificationChannel;
use Nowo\PerformanceBundle\Notification\Channel\WebhookNotificationChannel;
use Nowo\PerformanceBundle\Notification\PerformanceAlert;
use Nowo\PerformanceBundle\Notification\RouteMetricsNotificationChannelInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

use function class_exists;
use function in_array;
use function interface_exists;
use function sprintf;
use function str_starts_with;

/**
 * REQ-ARCH-001 rules 1 and 3 after 4.0: the notification port a channel implements, and every type it is handed (walked
 * recursively through their public instance API), names no Doctrine entity, no Doctrine `Collection` (no `Doctrine\`
 * type at all) and no bundle event. The 3.x port and the removed accessors are gone.
 *
 * Static factories (`RouteMetricsSnapshot::fromRouteData()` / `fromContext()`) are the bundle's own adapters from the
 * entity and are not part of what a channel receives.
 */
final class NotificationPortPublicApiTest extends TestCase
{
    public function testRemovedChannelInterfaceIsGone(): void
    {
        self::assertFalse(interface_exists('Nowo\\PerformanceBundle\\Notification\\NotificationChannelInterface'));
    }

    public function testThePortStandsAlone(): void
    {
        $port = new ReflectionClass(RouteMetricsNotificationChannelInterface::class);

        self::assertTrue($port->isInterface());
        self::assertSame([], $port->getInterfaceNames());
        self::assertSame(
            ['getName', 'isEnabled', 'sendRouteMetrics'],
            $this->sortedMethodNames($port),
        );
        self::assertFalse($port->hasMethod('send'));
    }

    /**
     * @param class-string $channel
     */
    #[DataProvider('builtInChannels')]
    public function testBuiltInChannelsImplementOnlyThePort(string $channel): void
    {
        $ref = new ReflectionClass($channel);

        self::assertTrue($ref->implementsInterface(RouteMetricsNotificationChannelInterface::class));
        self::assertSame([RouteMetricsNotificationChannelInterface::class], $ref->getInterfaceNames());
        self::assertFalse($ref->hasMethod('send'), $channel . '::send() still exists');
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function builtInChannels(): iterable
    {
        yield 'email' => [EmailNotificationChannel::class];
        yield 'webhook' => [WebhookNotificationChannel::class];
    }

    public function testNoTypeReachableFromThePortNamesAnEntityACollectionOrAnEvent(): void
    {
        $violations = [];
        $seen       = [];
        self::walk(RouteMetricsNotificationChannelInterface::class, $seen, $violations);

        self::assertSame([], $violations);
        // The walk really reached the value objects a channel receives.
        self::assertContains(PerformanceAlert::class, $seen);
        self::assertContains(RouteMetricsSnapshot::class, $seen);
    }

    /**
     * @param class-string $event
     */
    #[DataProvider('routeEvents')]
    public function testRouteEventsNoLongerExposeTheEntity(string $event): void
    {
        $ref = new ReflectionClass($event);

        self::assertFalse($ref->hasMethod('getRouteData'), $event . '::getRouteData() still exists');
        foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isConstructor() || $method->getDeclaringClass()->getName() !== $event) {
                continue;
            }
            self::assertSame([], self::forbiddenIn($method->getReturnType()), sprintf('%s::%s() returns a forbidden type', $event, $method->getName()));
        }
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function routeEvents(): iterable
    {
        yield 'after recorded' => [AfterMetricsRecordedEvent::class];
        yield 'before deleted' => [BeforeRecordDeletedEvent::class];
        yield 'before reviewed' => [BeforeRecordReviewedEvent::class];
        yield 'after reviewed' => [AfterRecordReviewedEvent::class];
    }

    public function testAccessRecordsAreAListNotACollection(): void
    {
        $type = (new ReflectionMethod(RouteDataWithAggregates::class, 'getAccessRecords'))->getReturnType();

        self::assertInstanceOf(ReflectionNamedType::class, $type);
        self::assertSame('array', $type->getName());
    }

    /**
     * @param list<string> $seen
     * @param list<string> $violations
     */
    private static function walk(string $class, array &$seen, array &$violations): void
    {
        if (in_array($class, $seen, true)) {
            return;
        }
        $seen[] = $class;
        $ref    = new ReflectionClass($class);

        foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic()) {
                continue;
            }
            $types = [$method->getReturnType()];
            foreach ($method->getParameters() as $parameter) {
                $types[] = $parameter->getType();
            }
            self::check(sprintf('%s::%s()', $class, $method->getName()), $types, $seen, $violations);
        }

        foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if (!$property->isStatic()) {
                self::check(sprintf('%s::$%s', $class, $property->getName()), [$property->getType()], $seen, $violations);
            }
        }
    }

    /**
     * @param list<ReflectionType|null> $types
     * @param list<string> $seen
     * @param list<string> $violations
     */
    private static function check(string $where, array $types, array &$seen, array &$violations): void
    {
        foreach ($types as $type) {
            foreach (self::forbiddenIn($type) as $name) {
                $violations[] = sprintf('%s names %s', $where, $name);
            }
            foreach (self::classNames($type) as $name) {
                if (str_starts_with($name, 'Nowo\\PerformanceBundle\\')) {
                    self::walk($name, $seen, $violations);
                }
            }
        }
    }

    /**
     * @return list<string> forbidden class names in $type
     */
    private static function forbiddenIn(?ReflectionType $type): array
    {
        $forbidden = [];
        foreach (self::classNames($type) as $name) {
            if (str_starts_with($name, 'Nowo\\PerformanceBundle\\Entity\\')
                || str_starts_with($name, 'Nowo\\PerformanceBundle\\Event\\')
                || str_starts_with($name, 'Doctrine\\')) {
                $forbidden[] = $name;
            }
        }

        return $forbidden;
    }

    /**
     * @return list<string> class / interface names in $type (builtins skipped)
     */
    private static function classNames(?ReflectionType $type): array
    {
        if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
            $names = [];
            foreach ($type->getTypes() as $inner) {
                $names = [...$names, ...self::classNames($inner)];
            }

            return $names;
        }

        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return [];
        }

        $name = $type->getName();

        return class_exists($name) || interface_exists($name) ? [$name] : [];
    }

    /**
     * @param ReflectionClass<object> $ref
     *
     * @return list<string>
     */
    private function sortedMethodNames(ReflectionClass $ref): array
    {
        $names = [];
        foreach ($ref->getMethods() as $method) {
            $names[] = $method->getName();
        }
        sort($names);

        return $names;
    }
}
