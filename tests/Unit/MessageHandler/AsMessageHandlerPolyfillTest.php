<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\MessageHandler;

use PHPUnit\Framework\TestCase;

/**
 * Tests for AsMessageHandlerPolyfill.
 *
 * This test verifies that the polyfill works correctly when Symfony Messenger
 * is not installed, allowing the bundle to work without Messenger as an optional dependency.
 */
final class AsMessageHandlerPolyfillTest extends TestCase
{
    public function testPolyfillDefinesAsMessageHandlerWhenMessengerNotAvailable(): void
    {
        // Check if the polyfill would be defined
        // Since we can't easily test the eval() behavior without actually removing Messenger,
        // we test that the polyfill file exists and can be loaded

        $polyfillFile = __DIR__.'/../../../src/MessageHandler/AsMessageHandlerPolyfill.php';
        $this->assertFileExists($polyfillFile);

        // The polyfill should be safe to include multiple times
        require_once $polyfillFile;

        // If Messenger is available, the polyfill won't define the class
        // If Messenger is not available, the polyfill will define it
        $messengerAvailable = class_exists('Symfony\Component\Messenger\Attribute\AsMessageHandler', false);

        if (!$messengerAvailable) {
            // If Messenger is not available, the polyfill should define the class
            $this->assertTrue(
                class_exists('Symfony\Component\Messenger\Attribute\AsMessageHandler', false),
                'AsMessageHandler polyfill should be defined when Messenger is not available'
            );
        } else {
            // If Messenger is available, the polyfill should not interfere
            $this->assertTrue(
                class_exists('Symfony\Component\Messenger\Attribute\AsMessageHandler', false),
                'AsMessageHandler should be available from Symfony Messenger'
            );
        }
    }

    public function testPolyfillFileIsValidPhp(): void
    {
        $polyfillFile = __DIR__.'/../../../src/MessageHandler/AsMessageHandlerPolyfill.php';
        $this->assertFileExists($polyfillFile);

        // Check that the file is valid PHP
        $output = [];
        $returnCode = 0;
        exec("php -l {$polyfillFile} 2>&1", $output, $returnCode);

        $this->assertEquals(0, $returnCode, 'Polyfill file should be valid PHP: '.implode("\n", $output));
    }

    public function testPolyfillConditionalDefinition(): void
    {
        // The polyfill should only define the class if Messenger is not available
        $polyfillFile = __DIR__.'/../../../src/MessageHandler/AsMessageHandlerPolyfill.php';
        $content = file_get_contents($polyfillFile);

        // Check that the polyfill checks for Messenger existence
        $this->assertStringContainsString(
            'class_exists',
            $content,
            'Polyfill should check if Messenger is available'
        );

        // Check that it uses eval to define the class
        $this->assertStringContainsString(
            'eval',
            $content,
            'Polyfill should use eval to define the class'
        );
    }

    public function testPolyfillAttributeTargets(): void
    {
        // If the polyfill is active, verify it has the correct attribute targets
        $messengerAvailable = class_exists('Symfony\Component\Messenger\Attribute\AsMessageHandler', false);

        if (!$messengerAvailable) {
            // Load the polyfill
            require_once __DIR__.'/../../../src/MessageHandler/AsMessageHandlerPolyfill.php';

            $reflection = new \ReflectionClass('Symfony\Component\Messenger\Attribute\AsMessageHandler');
            $attributes = $reflection->getAttributes();

            // The polyfill should define an Attribute with TARGET_CLASS | TARGET_METHOD
            $this->assertNotEmpty($attributes, 'AsMessageHandler should have attribute metadata');
        } else {
            $this->markTestSkipped('Symfony Messenger is available, polyfill is not active');
        }
    }

    public function testPolyfillConstructorParameters(): void
    {
        // If the polyfill is active, verify it accepts the same parameters as the real AsMessageHandler
        $messengerAvailable = class_exists('Symfony\Component\Messenger\Attribute\AsMessageHandler', false);

        if (!$messengerAvailable) {
            // Load the polyfill
            require_once __DIR__.'/../../../src/MessageHandler/AsMessageHandlerPolyfill.php';

            $reflection = new \ReflectionClass('Symfony\Component\Messenger\Attribute\AsMessageHandler');
            $constructor = $reflection->getConstructor();

            $this->assertNotNull($constructor, 'AsMessageHandler should have a constructor');

            $parameters = $constructor->getParameters();
            $this->assertCount(4, $parameters, 'AsMessageHandler should have 4 constructor parameters');

            $expectedParams = ['fromTransport', 'handles', 'priority', 'method'];
            foreach ($expectedParams as $index => $paramName) {
                $this->assertEquals($paramName, $parameters[$index]->getName());
            }
        } else {
            $this->markTestSkipped('Symfony Messenger is available, polyfill is not active');
        }
    }
}
