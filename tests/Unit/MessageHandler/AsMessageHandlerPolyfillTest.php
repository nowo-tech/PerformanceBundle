<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\MessageHandler;

use Nowo\PerformanceBundle\Message\RecordMetricsMessage;
use PHPUnit\Framework\TestCase;

/**
 * Ensures AsMessageHandler (polyfill or Symfony) exists and can be instantiated with default constructor.
 */
final class AsMessageHandlerPolyfillTest extends TestCase
{
    public function testAsMessageHandlerClassExists(): void
    {
        require_once __DIR__ . '/../../../src/MessageHandler/AsMessageHandlerPolyfill.php';

        $this->assertTrue(
            class_exists('Symfony\Component\Messenger\Attribute\AsMessageHandler', true),
            'AsMessageHandler (polyfill or Symfony) must exist after loading polyfill.',
        );
    }

    public function testAsMessageHandlerInstantiationWithDefaults(): void
    {
        require_once __DIR__ . '/../../../src/MessageHandler/AsMessageHandlerPolyfill.php';

        $class = 'Symfony\Component\Messenger\Attribute\AsMessageHandler';
        $this->assertTrue(class_exists($class, true));

        $attr = new $class();
        $r = new \ReflectionClass($class);

        foreach (['fromTransport', 'handles', 'priority', 'method'] as $name) {
            $p = $r->getProperty($name);
            $p->setAccessible(true);
            $this->assertNull($p->getValue($attr), "{$name} should be null by default");
        }
    }

    public function testAsMessageHandlerInstantiationWithCustomParameters(): void
    {
        require_once __DIR__ . '/../../../src/MessageHandler/AsMessageHandlerPolyfill.php';

        $class = 'Symfony\Component\Messenger\Attribute\AsMessageHandler';
        if (!class_exists($class, true)) {
            $this->markTestSkipped('AsMessageHandler not available.');
        }

        $attr = new $class(fromTransport: 'async', handles: RecordMetricsMessage::class, priority: 10, method: 'handle');
        $r = new \ReflectionClass($class);

        $this->assertSame('async', $r->getProperty('fromTransport')->getValue($attr));
        $this->assertSame(RecordMetricsMessage::class, $r->getProperty('handles')->getValue($attr));
        $this->assertSame(10, $r->getProperty('priority')->getValue($attr));
        $this->assertSame('handle', $r->getProperty('method')->getValue($attr));
    }
}
