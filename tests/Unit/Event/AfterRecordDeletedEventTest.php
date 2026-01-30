<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Event;

use Nowo\PerformanceBundle\Event\AfterRecordDeletedEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\Event;

final class AfterRecordDeletedEventTest extends TestCase
{
    public function testGettersReturnConstructorValues(): void
    {
        $event = new AfterRecordDeletedEvent(42, 'app_home', 'dev');

        $this->assertSame(42, $event->getRecordId());
        $this->assertSame('app_home', $event->getRouteName());
        $this->assertSame('dev', $event->getEnv());
    }

    public function testDifferentEnvironments(): void
    {
        $event = new AfterRecordDeletedEvent(1, 'api_foo', 'prod');
        $this->assertSame('prod', $event->getEnv());
    }

    public function testZeroRecordId(): void
    {
        $event = new AfterRecordDeletedEvent(0, 'route', 'test');
        $this->assertSame(0, $event->getRecordId());
    }

    public function testGettersReturnSameValueOnMultipleCalls(): void
    {
        $event = new AfterRecordDeletedEvent(10, 'api_foo', 'prod');

        $this->assertSame($event->getRecordId(), $event->getRecordId());
        $this->assertSame($event->getRouteName(), $event->getRouteName());
        $this->assertSame($event->getEnv(), $event->getEnv());
        $this->assertSame(10, $event->getRecordId());
        $this->assertSame('api_foo', $event->getRouteName());
        $this->assertSame('prod', $event->getEnv());
    }

    public function testGetEnvWithEmptyString(): void
    {
        $event = new AfterRecordDeletedEvent(1, 'app_home', '');

        $this->assertSame(1, $event->getRecordId());
        $this->assertSame('app_home', $event->getRouteName());
        $this->assertSame('', $event->getEnv());
    }

    public function testExtendsSymfonyEvent(): void
    {
        $event = new AfterRecordDeletedEvent(1, 'app_home', 'dev');

        $this->assertInstanceOf(Event::class, $event);
    }

    public function testGetRouteNameWithEmptyString(): void
    {
        $event = new AfterRecordDeletedEvent(5, '', 'dev');

        $this->assertSame(5, $event->getRecordId());
        $this->assertSame('', $event->getRouteName());
        $this->assertSame('dev', $event->getEnv());
    }

    public function testGetRecordIdWithLargeNumber(): void
    {
        $event = new AfterRecordDeletedEvent(999999, 'api_foo', 'prod');

        $this->assertSame(999999, $event->getRecordId());
        $this->assertSame('api_foo', $event->getRouteName());
        $this->assertSame('prod', $event->getEnv());
    }

    public function testGetEnvWithStageEnvironment(): void
    {
        $event = new AfterRecordDeletedEvent(1, 'api_dashboard', 'stage');

        $this->assertSame(1, $event->getRecordId());
        $this->assertSame('api_dashboard', $event->getRouteName());
        $this->assertSame('stage', $event->getEnv());
    }

    public function testGetEnvWithTestEnvironment(): void
    {
        $event = new AfterRecordDeletedEvent(2, 'api_health', 'test');

        $this->assertSame(2, $event->getRecordId());
        $this->assertSame('api_health', $event->getRouteName());
        $this->assertSame('test', $event->getEnv());
    }
}
