<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Entity;

use Nowo\PerformanceBundle\Entity\RouteData;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

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
        $routeData  = new RouteData();
        $reflection = new ReflectionClass($routeData);
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

    public function testToStringWithPATCHMethod(): void
    {
        $routeData = new RouteData();
        $routeData->setHttpMethod('PATCH');
        $routeData->setName('api_patch');
        $routeData->setEnv('dev');

        $result = (string) $routeData;

        $this->assertSame('PATCH api_patch (dev)', $result);
    }

    public function testToStringWithHEADMethod(): void
    {
        $routeData = new RouteData();
        $routeData->setHttpMethod('HEAD');
        $routeData->setName('app_resource');
        $routeData->setEnv('prod');

        $result = (string) $routeData;

        $this->assertSame('HEAD app_resource (prod)', $result);
    }

    public function testToStringWithOPTIONSMethod(): void
    {
        $routeData = new RouteData();
        $routeData->setHttpMethod('OPTIONS');
        $routeData->setName('api_cors');
        $routeData->setEnv('dev');

        $result = (string) $routeData;

        $this->assertSame('OPTIONS api_cors (dev)', $result);
    }

    public function testToStringWithStageEnv(): void
    {
        $routeData = new RouteData();
        $routeData->setName('api_dashboard');
        $routeData->setEnv('stage');

        $result = (string) $routeData;

        $this->assertSame('api_dashboard (stage)', $result);
    }

    public function testToStringWithMethodNameAndTestEnv(): void
    {
        $routeData = new RouteData();
        $routeData->setHttpMethod('GET');
        $routeData->setName('api_health');
        $routeData->setEnv('test');

        $result = (string) $routeData;

        $this->assertSame('GET api_health (test)', $result);
    }

    public function testToStringWithEmptyNameIncludesEmptyInParts(): void
    {
        $routeData = new RouteData();
        $routeData->setHttpMethod('GET');
        $routeData->setName('');
        $routeData->setEnv('dev');

        $result = (string) $routeData;

        $this->assertSame('GET  (dev)', $result);
    }

    public function testToStringWithCONNECTMethod(): void
    {
        $routeData = new RouteData();
        $routeData->setHttpMethod('CONNECT');
        $routeData->setName('api_proxy');
        $routeData->setEnv('prod');

        $result = (string) $routeData;

        $this->assertSame('CONNECT api_proxy (prod)', $result);
    }
}
