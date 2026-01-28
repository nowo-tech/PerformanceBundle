<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Event;

use Nowo\PerformanceBundle\Event\AfterRecordDeletedEvent;
use PHPUnit\Framework\TestCase;

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
}
