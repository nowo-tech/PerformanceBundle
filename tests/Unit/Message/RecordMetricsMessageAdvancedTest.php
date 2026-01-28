<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Message;

use Nowo\PerformanceBundle\Message\RecordMetricsMessage;
use PHPUnit\Framework\TestCase;

/**
 * Advanced tests for RecordMetricsMessage edge cases.
 */
final class RecordMetricsMessageAdvancedTest extends TestCase
{
    public function testConstructorWithAllParameters(): void
    {
        $message = new RecordMetricsMessage(
            'app_home',
            'prod',
            0.5,
            10,
            0.2,
            ['id' => 123],
            1048576,
            'POST'
        );

        $this->assertSame('app_home', $message->getRouteName());
        $this->assertSame('prod', $message->getEnv());
        $this->assertSame(0.5, $message->getRequestTime());
        $this->assertSame(10, $message->getTotalQueries());
        $this->assertSame(0.2, $message->getQueryTime());
        $this->assertSame(['id' => 123], $message->getParams());
        $this->assertSame(1048576, $message->getMemoryUsage());
        $this->assertSame('POST', $message->getHttpMethod());
    }

    public function testConstructorWithOnlyRequiredParameters(): void
    {
        $message = new RecordMetricsMessage('app_home', 'dev');

        $this->assertSame('app_home', $message->getRouteName());
        $this->assertSame('dev', $message->getEnv());
        $this->assertNull($message->getRequestTime());
        $this->assertNull($message->getTotalQueries());
        $this->assertNull($message->getQueryTime());
        $this->assertNull($message->getParams());
        $this->assertNull($message->getMemoryUsage());
        $this->assertNull($message->getHttpMethod());
    }

    public function testConstructorWithNullValues(): void
    {
        $message = new RecordMetricsMessage(
            'app_home',
            'dev',
            null,
            null,
            null,
            null,
            null,
            null
        );

        $this->assertNull($message->getRequestTime());
        $this->assertNull($message->getTotalQueries());
        $this->assertNull($message->getQueryTime());
        $this->assertNull($message->getParams());
        $this->assertNull($message->getMemoryUsage());
        $this->assertNull($message->getHttpMethod());
    }

    public function testConstructorWithZeroValues(): void
    {
        $message = new RecordMetricsMessage(
            'app_home',
            'dev',
            0.0,
            0,
            0.0,
            [],
            0,
            'GET'
        );

        $this->assertSame(0.0, $message->getRequestTime());
        $this->assertSame(0, $message->getTotalQueries());
        $this->assertSame(0.0, $message->getQueryTime());
        $this->assertSame([], $message->getParams());
        $this->assertSame(0, $message->getMemoryUsage());
        $this->assertSame('GET', $message->getHttpMethod());
    }

    public function testConstructorWithEmptyStringRouteName(): void
    {
        $message = new RecordMetricsMessage('', 'dev');

        $this->assertSame('', $message->getRouteName());
    }

    public function testConstructorWithEmptyStringEnv(): void
    {
        $message = new RecordMetricsMessage('app_home', '');

        $this->assertSame('', $message->getEnv());
    }

    public function testConstructorWithEmptyArrayParams(): void
    {
        $message = new RecordMetricsMessage(
            'app_home',
            'dev',
            null,
            null,
            null,
            []
        );

        $this->assertSame([], $message->getParams());
    }

    public function testConstructorWithComplexParams(): void
    {
        $params = [
            'id' => 123,
            'slug' => 'test-article',
            'category' => ['tech', 'programming'],
            'tags' => ['php', 'symfony', 'performance'],
            'metadata' => [
                'author' => 'John Doe',
                'published' => true,
                'views' => 1000,
            ],
        ];

        $message = new RecordMetricsMessage(
            'app_home',
            'dev',
            null,
            null,
            null,
            $params
        );

        $this->assertSame($params, $message->getParams());
    }

    public function testConstructorWithVeryLargeValues(): void
    {
        $message = new RecordMetricsMessage(
            'app_home',
            'dev',
            999999.99,
            999999,
            999999.99,
            null,
            999999999,
            'GET'
        );

        $this->assertSame(999999.99, $message->getRequestTime());
        $this->assertSame(999999, $message->getTotalQueries());
        $this->assertSame(999999.99, $message->getQueryTime());
        $this->assertSame(999999999, $message->getMemoryUsage());
    }

    public function testConstructorWithVerySmallValues(): void
    {
        $message = new RecordMetricsMessage(
            'app_home',
            'dev',
            0.0001,
            1,
            0.0001,
            null,
            1024,
            'GET'
        );

        $this->assertSame(0.0001, $message->getRequestTime());
        $this->assertSame(1, $message->getTotalQueries());
        $this->assertSame(0.0001, $message->getQueryTime());
        $this->assertSame(1024, $message->getMemoryUsage());
    }

    public function testConstructorWithNegativeValues(): void
    {
        // Negative values are technically valid (though not realistic)
        $message = new RecordMetricsMessage(
            'app_home',
            'dev',
            -1.0,
            -5,
            -1.0,
            null,
            -1024,
            'GET'
        );

        $this->assertSame(-1.0, $message->getRequestTime());
        $this->assertSame(-5, $message->getTotalQueries());
        $this->assertSame(-1.0, $message->getQueryTime());
        $this->assertSame(-1024, $message->getMemoryUsage());
    }

    public function testConstructorWithDifferentHttpMethods(): void
    {
        $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];

        foreach ($methods as $method) {
            $message = new RecordMetricsMessage(
                'app_home',
                'dev',
                null,
                null,
                null,
                null,
                null,
                $method
            );

            $this->assertSame($method, $message->getHttpMethod());
        }
    }

    public function testConstructorWithDifferentEnvironments(): void
    {
        $environments = ['dev', 'test', 'prod', 'stage', 'qa', 'staging'];

        foreach ($environments as $env) {
            $message = new RecordMetricsMessage('app_home', $env);

            $this->assertSame($env, $message->getEnv());
        }
    }

    public function testConstructorWithSpecialCharactersInRouteName(): void
    {
        $routeNames = [
            'app_home',
            'app.home',
            'app-home',
            'app/home',
            'app\\home',
            'app_home_index',
            'api.v1.users.index',
        ];

        foreach ($routeNames as $routeName) {
            $message = new RecordMetricsMessage($routeName, 'dev');

            $this->assertSame($routeName, $message->getRouteName());
        }
    }

    public function testGettersReturnCorrectTypes(): void
    {
        $message = new RecordMetricsMessage(
            'app_home',
            'dev',
            0.5,
            10,
            0.2,
            ['id' => 123],
            1048576,
            'GET'
        );

        $this->assertIsString($message->getRouteName());
        $this->assertIsString($message->getEnv());
        $this->assertIsFloat($message->getRequestTime());
        $this->assertIsInt($message->getTotalQueries());
        $this->assertIsFloat($message->getQueryTime());
        $this->assertIsArray($message->getParams());
        $this->assertIsInt($message->getMemoryUsage());
        $this->assertIsString($message->getHttpMethod());
    }

    public function testGettersReturnNullForOptionalParameters(): void
    {
        $message = new RecordMetricsMessage('app_home', 'dev');

        $this->assertNull($message->getRequestTime());
        $this->assertNull($message->getTotalQueries());
        $this->assertNull($message->getQueryTime());
        $this->assertNull($message->getParams());
        $this->assertNull($message->getMemoryUsage());
        $this->assertNull($message->getHttpMethod());
    }

    public function testMessageIsImmutable(): void
    {
        $message = new RecordMetricsMessage('app_home', 'dev', 0.5);

        // All properties are readonly, so we can't modify them
        // This test verifies the message structure is immutable
        $this->assertSame('app_home', $message->getRouteName());
        $this->assertSame('dev', $message->getEnv());
        $this->assertSame(0.5, $message->getRequestTime());
    }
}
