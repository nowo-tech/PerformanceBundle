<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Entity;

use Nowo\PerformanceBundle\Entity\RouteData;
use PHPUnit\Framework\TestCase;

/**
 * Tests for RouteData::__toString() method.
 */
final class RouteDataToStringTest extends TestCase
{
    public function testToStringWithMethodNameAndEnv(): void
    {
        $routeData = new RouteData();
        $routeData->setHttpMethod('GET');
        $routeData->setName('app_home');
        $routeData->setEnv('dev');

        $result = (string) $routeData;

        $this->assertSame('GET app_home (dev)', $result);
    }

    public function testToStringWithOnlyName(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home');

        $result = (string) $routeData;

        $this->assertSame('app_home', $result);
    }

    public function testToStringWithOnlyMethod(): void
    {
        $routeData = new RouteData();
        $routeData->setHttpMethod('POST');

        $result = (string) $routeData;

        $this->assertSame('POST', $result);
    }

    public function testToStringWithOnlyEnv(): void
    {
        $routeData = new RouteData();
        $routeData->setEnv('prod');

        $result = (string) $routeData;

        $this->assertSame('(prod)', $result);
    }

    public function testToStringWithMethodAndName(): void
    {
        $routeData = new RouteData();
        $routeData->setHttpMethod('PUT');
        $routeData->setName('api_update');

        $result = (string) $routeData;

        $this->assertSame('PUT api_update', $result);
    }

    public function testToStringWithNameAndEnv(): void
    {
        $routeData = new RouteData();
        $routeData->setName('app_home');
        $routeData->setEnv('test');

        $result = (string) $routeData;

        $this->assertSame('app_home (test)', $result);
    }

    public function testToStringWithMethodAndEnv(): void
    {
        $routeData = new RouteData();
        $routeData->setHttpMethod('DELETE');
        $routeData->setEnv('prod');

        $result = (string) $routeData;

        $this->assertSame('DELETE (prod)', $result);
    }

    public function testToStringWithNoFieldsReturnsId(): void
    {
        $routeData = new RouteData();
        $reflection = new \ReflectionClass($routeData);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($routeData, 42);

        $result = (string) $routeData;

        $this->assertSame('RouteData#42', $result);
    }

    public function testToStringWithNoFieldsAndNoIdReturnsNew(): void
    {
        $routeData = new RouteData();

        $result = (string) $routeData;

        $this->assertSame('RouteData#new', $result);
    }
}
