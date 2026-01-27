<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Event;

use Nowo\PerformanceBundle\Event\BeforeRecordsClearedEvent;
use PHPUnit\Framework\TestCase;

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
}
