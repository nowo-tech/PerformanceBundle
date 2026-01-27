<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Event;

use Nowo\PerformanceBundle\Event\AfterRecordsClearedEvent;
use PHPUnit\Framework\TestCase;

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
}
