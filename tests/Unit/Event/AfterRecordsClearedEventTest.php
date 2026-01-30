<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Event;

use Nowo\PerformanceBundle\Event\AfterRecordsClearedEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\Event;

final class AfterRecordsClearedEventTest extends TestCase
{
    public function testGettersReturnConstructorValues(): void
    {
        $event = new AfterRecordsClearedEvent(42, 'dev');

        $this->assertSame(42, $event->getDeletedCount());
        $this->assertSame('dev', $event->getEnv());
    }

    public function testGetEnvReturnsNullWhenNotProvided(): void
    {
        $event = new AfterRecordsClearedEvent(10, null);

        $this->assertSame(10, $event->getDeletedCount());
        $this->assertNull($event->getEnv());
    }

    public function testGetDeletedCountWithZero(): void
    {
        $event = new AfterRecordsClearedEvent(0, 'dev');

        $this->assertSame(0, $event->getDeletedCount());
        $this->assertSame('dev', $event->getEnv());
    }

    public function testGetEnvWithEmptyString(): void
    {
        $event = new AfterRecordsClearedEvent(5, '');

        $this->assertSame(5, $event->getDeletedCount());
        $this->assertSame('', $event->getEnv());
    }

    public function testExtendsSymfonyEvent(): void
    {
        $event = new AfterRecordsClearedEvent(5, 'dev');

        $this->assertInstanceOf(Event::class, $event);
    }

    public function testGetDeletedCountWithLargeNumber(): void
    {
        $event = new AfterRecordsClearedEvent(1000, 'prod');

        $this->assertSame(1000, $event->getDeletedCount());
        $this->assertSame('prod', $event->getEnv());
    }

    public function testGetEnvWithStageEnvironment(): void
    {
        $event = new AfterRecordsClearedEvent(7, 'stage');

        $this->assertSame(7, $event->getDeletedCount());
        $this->assertSame('stage', $event->getEnv());
    }

    public function testGetEnvWithTestEnvironment(): void
    {
        $event = new AfterRecordsClearedEvent(3, 'test');

        $this->assertSame(3, $event->getDeletedCount());
        $this->assertSame('test', $event->getEnv());
    }
}
