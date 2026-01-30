<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Event;

use Nowo\PerformanceBundle\Event\BeforeRecordsClearedEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\Event;

final class BeforeRecordsClearedEventTest extends TestCase
{
    public function testGettersReturnConstructorValues(): void
    {
        $event = new BeforeRecordsClearedEvent('dev');

        $this->assertSame('dev', $event->getEnv());
        $this->assertFalse($event->isClearingPrevented());
    }

    public function testGetEnvReturnsNullWhenNotProvided(): void
    {
        $event = new BeforeRecordsClearedEvent(null);

        $this->assertNull($event->getEnv());
    }

    public function testPreventClearing(): void
    {
        $event = new BeforeRecordsClearedEvent('dev');

        $this->assertFalse($event->isClearingPrevented());

        $event->preventClearing();

        $this->assertTrue($event->isClearingPrevented());
    }

    public function testPreventClearingCalledMultipleTimesRemainsPrevented(): void
    {
        $event = new BeforeRecordsClearedEvent(null);

        $event->preventClearing();
        $event->preventClearing();

        $this->assertTrue($event->isClearingPrevented());
    }

    public function testGetEnvWithEmptyString(): void
    {
        $event = new BeforeRecordsClearedEvent('');

        $this->assertSame('', $event->getEnv());
        $this->assertFalse($event->isClearingPrevented());
    }

    public function testExtendsSymfonyEvent(): void
    {
        $event = new BeforeRecordsClearedEvent('dev');

        $this->assertInstanceOf(Event::class, $event);
    }

    public function testGetEnvWithStageEnvironment(): void
    {
        $event = new BeforeRecordsClearedEvent('stage');

        $this->assertSame('stage', $event->getEnv());
        $this->assertFalse($event->isClearingPrevented());
    }

    public function testGetEnvWithTestEnvironment(): void
    {
        $event = new BeforeRecordsClearedEvent('test');

        $this->assertSame('test', $event->getEnv());
    }

    public function testPreventClearingWithStageEnv(): void
    {
        $event = new BeforeRecordsClearedEvent('stage');

        $this->assertSame('stage', $event->getEnv());
        $this->assertFalse($event->isClearingPrevented());

        $event->preventClearing();

        $this->assertTrue($event->isClearingPrevented());
    }
}
